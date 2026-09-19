<?php

namespace App\Http\Controllers;

use App\Models\ElectronicBillingSetting;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicSummary;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Facturacion\SunatBilling;
use App\Services\Facturacion\Support\QrGenerator;
use App\Services\Tenancy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Módulo de Facturación Electrónica (SUNAT · Perú):
 * configuración del emisor, prueba de conexión, emisión de comprobantes
 * a partir de los pagos y consulta/descarga de comprobantes emitidos.
 */
class ElectronicBillingController extends Controller
{
    public function __construct(private SunatBilling $billing)
    {
    }

    /** Listado de comprobantes electrónicos emitidos. */
    public function index(Request $request): View
    {
        $settings = ElectronicBillingSetting::current();

        $invoices = ElectronicInvoice::with('payment.student')
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->tipo, fn ($q, $t) => $q->where('tipo_doc', $t))
            ->when($request->search, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('full_number', 'like', "%{$s}%")
                    ->orWhere('client_razon_social', 'like', "%{$s}%")
                    ->orWhere('client_num_doc', 'like', "%{$s}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'aceptados' => ElectronicInvoice::where('estado', 'aceptado')->count(),
            'pendientes' => ElectronicInvoice::whereIn('estado', ['pendiente', 'enviando'])->count(),
            'rechazados' => ElectronicInvoice::whereIn('estado', ['rechazado', 'error'])->count(),
            'total' => (float) ElectronicInvoice::where('estado', 'aceptado')->sum('total'),
        ];

        return view('facturacion.index', compact('invoices', 'summary', 'settings'));
    }

    /** Pantalla de configuración del emisor y credenciales SUNAT. */
    public function configuracion(): View
    {
        $settings = ElectronicBillingSetting::current();
        $greenterAvailable = class_exists(\Greenter\See::class);

        return view('facturacion.configuracion', compact('settings', 'greenterAvailable'));
    }

    /** Guarda la configuración de facturación electrónica. */
    public function guardar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'auto_emit' => ['nullable', 'boolean'],
            'boletas_por_resumen' => ['nullable', 'boolean'],
            'driver' => ['required', 'in:none,greenter'],
            'environment' => ['required', 'in:beta,produccion'],
            'ruc' => ['nullable', 'digits:11'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion_fiscal' => ['nullable', 'string', 'max:255'],
            'ubigeo' => ['nullable', 'string', 'max:6'],
            'departamento' => ['nullable', 'string', 'max:60'],
            'provincia' => ['nullable', 'string', 'max:60'],
            'distrito' => ['nullable', 'string', 'max:60'],
            'urbanizacion' => ['nullable', 'string', 'max:120'],
            'sol_user' => ['nullable', 'string', 'max:60'],
            'sol_pass' => ['nullable', 'string', 'max:60'],
            'certificate_path' => ['nullable', 'string', 'max:255'],
            'certificate_password' => ['nullable', 'string', 'max:120'],
            'serie_factura' => ['required', 'string', 'max:4'],
            'serie_boleta' => ['required', 'string', 'max:4'],
            'serie_nc_factura' => ['nullable', 'string', 'max:4'],
            'serie_nc_boleta' => ['nullable', 'string', 'max:4'],
            'igv_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'moneda' => ['required', 'string', 'max:3'],
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $data['auto_emit'] = $request->boolean('auto_emit');
        $data['boletas_por_resumen'] = $request->boolean('boletas_por_resumen');

        $settings = $this->currentOrNew();

        // No sobrescribir credenciales sensibles si el campo se deja vacío.
        foreach (['sol_pass', 'certificate_password'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $settings->fill($data)->save();

        return redirect()->route('facturacion.configuracion')
            ->with('success', 'Configuración de facturación electrónica guardada correctamente.');
    }

    /** Prueba la conexión con SUNAT. */
    public function probar(): RedirectResponse
    {
        $result = $this->billing->testConnection();

        return redirect()->route('facturacion.configuracion')
            ->with($result->success ? 'success' : 'error',
                ($result->success ? '✓ ' : '✗ ').$result->message);
    }

    /** Emite un comprobante a partir de un pago. */
    public function emitirDesdePago(Request $request, Payment $payment): RedirectResponse
    {
        $request->validate(['tipo_doc' => ['required', 'in:01,03']]);

        $settings = ElectronicBillingSetting::current();
        if (! $settings->enabled) {
            return back()->with('error', 'La facturación electrónica está deshabilitada. Actívala en Configuración.');
        }

        // Evitar duplicar un comprobante aceptado/pendiente para el mismo pago.
        $existing = ElectronicInvoice::where('payment_id', $payment->id)
            ->whereNotIn('estado', ['rechazado', 'error', 'anulado'])
            ->first();
        if ($existing) {
            return back()->with('error', 'Este pago ya tiene el comprobante '.$existing->full_number.' ('.$existing->estado.').');
        }

        $invoice = $this->billing->emitForPayment($payment, $request->tipo_doc);

        $msg = 'Comprobante '.$invoice->full_number.': '.$invoice->estado
            .($invoice->sunat_description ? ' — '.$invoice->sunat_description : '');

        return back()->with($invoice->estado === 'aceptado' ? 'success' : ($invoice->estado === 'pendiente' ? 'success' : 'error'), $msg);
    }

    /** Reintenta el envío de un comprobante pendiente/rechazado. */
    public function reenviar(ElectronicInvoice $invoice): RedirectResponse
    {
        $invoice = $this->billing->send($invoice);

        return back()->with($invoice->estado === 'aceptado' ? 'success' : 'error',
            'Comprobante '.$invoice->full_number.': '.$invoice->estado
            .($invoice->sunat_description ? ' — '.$invoice->sunat_description : ''));
    }

    /** Anula un comprobante. */
    public function anular(ElectronicInvoice $invoice): RedirectResponse
    {
        $this->billing->void($invoice);

        return back()->with('success', 'Comprobante '.$invoice->full_number.' anulado.');
    }

    public function show(ElectronicInvoice $invoice): View
    {
        $invoice->load('payment.student');

        return view('facturacion.show', compact('invoice'));
    }

    /** Emite una nota de crédito a partir de un comprobante aceptado. */
    public function notaCredito(Request $request, ElectronicInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'cod_motivo' => ['required', 'in:'.implode(',', array_keys(ElectronicInvoice::MOTIVOS_NC))],
            'motivo' => ['nullable', 'string', 'max:250'],
        ]);

        if (! in_array($invoice->tipo_doc, ['01', '03'], true) || $invoice->estado !== 'aceptado') {
            return back()->with('error', 'Solo se puede emitir una nota de crédito sobre una factura o boleta aceptada.');
        }

        $note = $this->billing->createCreditNote($invoice, $data['cod_motivo'], $data['motivo'] ?? null);

        return back()->with($note->estado === 'aceptado' ? 'success' : 'error',
            'Nota de crédito '.$note->full_number.': '.$note->estado
            .($note->sunat_description ? ' — '.$note->sunat_description : ''));
    }

    /** Listado y gestión de Resúmenes Diarios de boletas. */
    public function resumenes(): View
    {
        $settings = ElectronicBillingSetting::current();

        $summaries = ElectronicSummary::withCount('invoices')->latest()->paginate(15);

        // Boletas pendientes agrupadas por fecha (candidatas a resumen).
        $pendientes = ElectronicInvoice::where('tipo_doc', '03')
            ->whereNull('summary_id')
            ->where('estado', 'pendiente')
            ->selectRaw('DATE(fecha_emision) as dia, COUNT(*) as total, SUM(total) as monto')
            ->groupByRaw('DATE(fecha_emision)')
            ->orderByDesc('dia')
            ->get();

        return view('facturacion.resumenes', compact('summaries', 'pendientes', 'settings'));
    }

    /** Genera y envía el resumen diario de una fecha. */
    public function generarResumen(Request $request): RedirectResponse
    {
        $data = $request->validate(['fecha' => ['required', 'date']]);

        $summary = $this->billing->generateDailySummary($data['fecha']);

        return redirect()->route('facturacion.resumenes')->with(
            in_array($summary->estado, ['ticket', 'aceptado'], true) ? 'success' : 'error',
            'Resumen '.$summary->identifier.': '.$summary->estado
            .($summary->sunat_description ? ' — '.$summary->sunat_description : '')
        );
    }

    /** Consulta el estado del ticket de un resumen. */
    public function consultarResumen(ElectronicSummary $summary): RedirectResponse
    {
        $summary = $this->billing->checkSummary($summary);

        return back()->with($summary->estado === 'aceptado' ? 'success' : 'error',
            'Resumen '.$summary->identifier.': '.$summary->estado
            .($summary->sunat_description ? ' — '.$summary->sunat_description : ''));
    }

    /** Representación impresa (PDF) del comprobante con QR y hash. */
    public function pdf(ElectronicInvoice $invoice, Request $request)
    {
        $invoice->load('payment.student');
        $settings = ElectronicBillingSetting::current();
        $school = Setting::current();
        $qr = QrGenerator::dataUri($invoice, $settings);
        $qrContent = QrGenerator::content($invoice, $settings);

        $pdf = Pdf::loadView('documents.comprobante', compact('invoice', 'settings', 'school', 'qr', 'qrContent'))
            ->setPaper('a5', 'portrait');

        $name = $invoice->full_number.'.pdf';

        return $request->get('inline')
            ? $pdf->stream($name)
            : $pdf->download($name);
    }

    public function descargarXml(ElectronicInvoice $invoice)
    {
        abort_unless($invoice->xml_path && Storage::disk('local')->exists($invoice->xml_path), 404, 'XML no disponible.');

        return Storage::disk('local')->download($invoice->xml_path, $invoice->full_number.'.xml');
    }

    public function descargarCdr(ElectronicInvoice $invoice)
    {
        abort_unless($invoice->cdr_path && Storage::disk('local')->exists($invoice->cdr_path), 404, 'CDR no disponible.');

        return Storage::disk('local')->download($invoice->cdr_path, 'R-'.$invoice->full_number.'.zip');
    }

    /** Devuelve la configuración del colegio activo o una instancia nueva ligada al tenant. */
    private function currentOrNew(): ElectronicBillingSetting
    {
        $tenancy = app(Tenancy::class);
        $settings = ElectronicBillingSetting::first();

        if (! $settings) {
            $settings = new ElectronicBillingSetting();
            if ($tenancy->check()) {
                $settings->school_id = $tenancy->id();
            }
        }

        return $settings;
    }
}
