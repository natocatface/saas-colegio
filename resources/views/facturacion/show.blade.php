@extends('layouts.app')
@section('title', 'Comprobante '.$invoice->full_number)

@section('content')
<div class="page-head">
    <div><h1>{{ $invoice->full_number }}</h1><div class="breadcrumb-mini">{{ $invoice->tipoLabel() }} · {{ optional($invoice->fecha_emision)->format('d/m/Y H:i') }}</div></div>
    <a href="{{ route('facturacion.index') }}" class="btn btn-light btn-icon"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<div class="grid-2">
    <div class="card card-accent">
        <div class="card-header"><span class="title"><i class="bi bi-receipt"></i> Datos del comprobante</span>
            <span class="badge-soft {{ $invoice->badgeClass() }} ms-auto">{{ ucfirst($invoice->estado) }}</span>
        </div>
        <div class="card-body">
            <table class="table table-sm mb-0">
                <tr><th class="text-muted" style="width:42%">Tipo</th><td>{{ $invoice->tipoLabel() }} ({{ $invoice->tipo_doc }})</td></tr>
                <tr><th class="text-muted">Serie · Correlativo</th><td>{{ $invoice->serie }} · {{ $invoice->correlativo }}</td></tr>
                <tr><th class="text-muted">Cliente</th><td>{{ $invoice->client_razon_social }}</td></tr>
                <tr><th class="text-muted">Documento</th><td>{{ ['6'=>'RUC','1'=>'DNI','0'=>'Sin doc.'][$invoice->client_tipo_doc] ?? $invoice->client_tipo_doc }} {{ $invoice->client_num_doc }}</td></tr>
                <tr><th class="text-muted">Moneda</th><td>{{ $invoice->moneda }}</td></tr>
                <tr><th class="text-muted">Op. gravada</th><td>{{ number_format($invoice->op_gravadas,2) }}</td></tr>
                <tr><th class="text-muted">IGV</th><td>{{ number_format($invoice->igv,2) }}</td></tr>
                <tr><th class="text-muted">Total</th><td><strong>{{ $invoice->moneda }} {{ number_format($invoice->total,2) }}</strong></td></tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="title"><i class="bi bi-cloud-check"></i> Respuesta SUNAT</span></div>
        <div class="card-body">
            <table class="table table-sm">
                <tr><th class="text-muted" style="width:42%">Estado</th><td><span class="badge-soft {{ $invoice->badgeClass() }}">{{ ucfirst($invoice->estado) }}</span></td></tr>
                <tr><th class="text-muted">Código SUNAT</th><td>{{ $invoice->sunat_code ?: '—' }}</td></tr>
                <tr><th class="text-muted">Descripción</th><td>{{ $invoice->sunat_description ?: '—' }}</td></tr>
                <tr><th class="text-muted">Hash (DigestValue)</th><td class="text-break small">{{ $invoice->hash ?: '—' }}</td></tr>
            </table>
            @if($invoice->error_message)<div class="alert alert-danger py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i>{{ $invoice->error_message }}</div>@endif
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('facturacion.pdf', $invoice) }}" class="btn btn-sm btn-brand btn-icon"><i class="bi bi-file-earmark-pdf"></i> Descargar PDF</a>
                <a href="{{ route('facturacion.pdf', ['invoice'=>$invoice,'inline'=>1]) }}" target="_blank" class="btn btn-sm btn-outline-secondary btn-icon"><i class="bi bi-eye"></i> Ver PDF</a>
                @if($invoice->xml_path)<a href="{{ route('facturacion.xml', $invoice) }}" class="btn btn-sm btn-outline-secondary btn-icon"><i class="bi bi-filetype-xml"></i> XML firmado</a>@endif
                @if($invoice->cdr_path)<a href="{{ route('facturacion.cdr', $invoice) }}" class="btn btn-sm btn-outline-secondary btn-icon"><i class="bi bi-file-earmark-zip"></i> CDR</a>@endif
                @if(in_array($invoice->estado, ['pendiente','rechazado','error']))
                    <form action="{{ route('facturacion.reenviar', $invoice) }}" method="POST">@csrf<button class="btn btn-sm btn-brand btn-icon"><i class="bi bi-send"></i> Enviar a SUNAT</button></form>
                @endif
                @if(in_array($invoice->tipo_doc, ['01','03']) && $invoice->estado === 'aceptado')
                    <button class="btn btn-sm btn-outline-danger btn-icon" data-bs-toggle="modal" data-bs-target="#mNC"><i class="bi bi-file-earmark-minus"></i> Nota de crédito</button>
                @endif
            </div>

            @if($invoice->isNota() && $invoice->ref_full_number)
                <div class="alert alert-info py-2 small mt-3 mb-0"><i class="bi bi-link-45deg me-1"></i>Nota de crédito del comprobante <strong>{{ $invoice->ref_full_number }}</strong> · Motivo: {{ $invoice->nota_motivo }}</div>
            @endif
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><span class="title"><i class="bi bi-list-ul"></i> Detalle</span></div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th class="ps-3">Descripción</th><th>Cant.</th><th>Valor unit.</th></tr></thead>
            <tbody>
            @foreach(($invoice->items ?? []) as $it)
                <tr><td class="ps-3">{{ $it['descripcion'] ?? '' }}</td><td>{{ $it['cantidad'] ?? 1 }}</td><td>{{ number_format((float)($it['valor_unit'] ?? 0),2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if(in_array($invoice->tipo_doc, ['01','03']) && $invoice->estado === 'aceptado')
<div class="modal fade" id="mNC" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form action="{{ route('facturacion.nc', $invoice) }}" method="POST">@csrf
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-file-earmark-minus me-2"></i>Nota de crédito · {{ $invoice->full_number }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="text-muted small">Se emitirá una nota de crédito ({{ $invoice->tipo_doc === '01' ? 'serie FC01' : 'serie BC01' }}) que referencia a este comprobante. Con motivo de anulación o devolución total, el comprobante original quedará <strong>anulado</strong>.</p>
            <div class="mb-3">
                <label class="form-label">Motivo <span class="text-danger">*</span></label>
                <select name="cod_motivo" class="form-select" required>
                    @foreach(\App\Models\ElectronicInvoice::MOTIVOS_NC as $k=>$v)
                        <option value="{{ $k }}">{{ $k }} · {{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-1"><label class="form-label">Descripción (opcional)</label><input name="motivo" class="form-control" maxlength="250" placeholder="Detalle del motivo"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger btn-icon"><i class="bi bi-file-earmark-minus"></i> Emitir nota de crédito</button></div>
    </form>
</div></div></div>
@endif
@endsection
