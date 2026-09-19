@extends('layouts.app')
@section('title', 'Facturación Electrónica')

@section('content')
<div class="page-head">
    <div><h1>Facturación Electrónica</h1><div class="breadcrumb-mini">Comprobantes de Pago Electrónicos · SUNAT Perú</div></div>
    <div class="d-flex gap-2">
        <span class="fe-chip-mini {{ $settings->enabled ? 'is-on' : 'is-off' }}"><i class="bi bi-{{ $settings->enabled ? 'check-circle-fill' : 'slash-circle' }}"></i> {{ $settings->enabled ? 'Emisión activa' : 'Deshabilitada' }}</span>
        <a href="{{ route('facturacion.resumenes') }}" class="btn btn-outline-secondary btn-icon"><i class="bi bi-calendar-week"></i> Resúmenes</a>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('facturacion.configuracion') }}" class="btn btn-brand btn-icon"><i class="bi bi-gear"></i> Configuración</a>
        @endif
    </div>
</div>

<div class="stats-row">
    <div class="stat-card bg-green"><div class="label">Aceptados</div><div class="value">{{ $summary['aceptados'] }}</div><i class="bi bi-check-circle icon"></i></div>
    <div class="stat-card bg-orange"><div class="label">Pendientes</div><div class="value">{{ $summary['pendientes'] }}</div><i class="bi bi-hourglass-split icon"></i></div>
    <div class="stat-card bg-red"><div class="label">Rechazados</div><div class="value">{{ $summary['rechazados'] }}</div><i class="bi bi-x-octagon icon"></i></div>
    <div class="stat-card bg-dark"><div class="label">Facturado (aceptado)</div><div class="value">{{ $settings->moneda ?: 'PEN' }} {{ number_format($summary['total'],0) }}</div><i class="bi bi-wallet2 icon"></i></div>
</div>

<div class="card"><div class="card-body">
    <form class="row g-2 mb-3">
        <div class="col-md-5"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Buscar por número, cliente o documento..."></div>
        <div class="col-md-3"><select name="tipo" class="form-select"><option value="">Todo tipo</option>@foreach(\App\Models\ElectronicInvoice::TIPOS as $k=>$v)<option value="{{ $k }}" @selected(request('tipo')==$k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="estado" class="form-select"><option value="">Todo estado</option>@foreach(['pendiente','aceptado','observado','rechazado','anulado','error'] as $st)<option value="{{ $st }}" @selected(request('estado')==$st)>{{ ucfirst($st) }}</option>@endforeach</select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary btn-icon"><i class="bi bi-funnel"></i> Filtrar</button></div>
    </form>

    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr>
            <th class="ps-3">Comprobante</th><th>Tipo</th><th>Cliente</th><th>Fecha</th><th>Total</th><th>Estado</th><th class="text-end pe-3">Acciones</th>
        </tr></thead>
        <tbody>
        @forelse($invoices as $inv)
            <tr>
                <td class="ps-3"><strong>{{ $inv->full_number }}</strong>@if($inv->sunat_code)<div class="text-muted small">SUNAT: {{ $inv->sunat_code }}</div>@endif</td>
                <td><span class="text-muted small">{{ $inv->tipoLabel() }}</span></td>
                <td>{{ $inv->client_razon_social }}<div class="text-muted small">{{ $inv->client_num_doc ?: '—' }}</div></td>
                <td>{{ optional($inv->fecha_emision)->format('d/m/Y') }}</td>
                <td><strong>{{ $inv->moneda }} {{ number_format($inv->total,2) }}</strong></td>
                <td><span class="badge-soft {{ $inv->badgeClass() }}">{{ ucfirst($inv->estado) }}</span></td>
                <td class="text-end pe-3">
                    <a href="{{ route('facturacion.show', $inv) }}" class="btn btn-sm btn-light" title="Detalle"><i class="bi bi-eye"></i></a>
                    <a href="{{ route('facturacion.pdf', $inv) }}" class="btn btn-sm btn-light" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                    @if($inv->xml_path)<a href="{{ route('facturacion.xml', $inv) }}" class="btn btn-sm btn-light" title="XML"><i class="bi bi-filetype-xml"></i></a>@endif
                    @if($inv->cdr_path)<a href="{{ route('facturacion.cdr', $inv) }}" class="btn btn-sm btn-light" title="CDR"><i class="bi bi-file-earmark-zip"></i></a>@endif
                    @if(in_array($inv->estado, ['pendiente','rechazado','error']))
                        <form action="{{ route('facturacion.reenviar', $inv) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-success btn-icon" title="Enviar a SUNAT"><i class="bi bi-send"></i></button></form>
                    @endif
                    @if($inv->estado === 'aceptado')
                        <form action="{{ route('facturacion.anular', $inv) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Anular este comprobante?')">@csrf<button class="btn btn-sm btn-light text-danger" title="Anular"><i class="bi bi-x-circle"></i></button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><i class="bi bi-receipt-cutoff"></i><p>Sin comprobantes emitidos. Emite uno desde el módulo de Pagos.</p></div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $invoices->links() }}
</div></div>

<style>
.fe-chip-mini{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:8px 14px;border-radius:30px}
.fe-chip-mini.is-on{background:var(--brand-soft);color:var(--brand-ink)}
.fe-chip-mini.is-off{background:#fee2e2;color:#991b1b}
</style>
@endsection
