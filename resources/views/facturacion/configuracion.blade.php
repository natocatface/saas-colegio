@extends('layouts.app')
@section('title', 'Facturación Electrónica')

@section('content')
<div class="page-head">
    <div>
        <h1>Facturación Electrónica</h1>
        <div class="breadcrumb-mini">Configuración de emisión de comprobantes ante SUNAT · Perú</div>
    </div>
    <a href="{{ route('facturacion.index') }}" class="btn btn-outline-secondary btn-icon"><i class="bi bi-receipt"></i> Ver comprobantes</a>
</div>

{{-- ===== Banner de estado ===== --}}
<div class="fe-banner mb-4">
    <div class="fe-banner-main">
        <div class="fe-banner-icon"><i class="bi bi-file-earmark-text"></i></div>
        <div>
            <div class="fe-banner-title">
                Facturación Electrónica <span class="fe-flag">🇵🇪 Perú</span>
            </div>
            <div class="fe-banner-sub">Emisión de comprobantes electrónicos ante <strong>SUNAT</strong> · UBL 2.1 · Boletas, facturas y notas de crédito</div>
        </div>
    </div>
    <div class="fe-banner-right">
        <div class="fe-sunat-tag">SUNAT</div>
        <div class="fe-sunat-caption">Comprobantes de Pago Electrónicos</div>
    </div>
</div>

{{-- ===== Chips de estado ===== --}}
<div class="fe-chips mb-4">
    <span class="fe-chip {{ $settings->enabled ? 'is-on' : 'is-off' }}"><i class="bi bi-{{ $settings->enabled ? 'check-circle-fill' : 'slash-circle' }}"></i> {{ $settings->enabled ? 'Habilitada' : 'Deshabilitada' }}</span>
    <span class="fe-chip is-neutral"><i class="bi bi-cpu"></i> Driver: {{ $settings->driver === 'none' ? 'ninguno' : $settings->driver }}</span>
    <span class="fe-chip {{ $settings->environment === 'produccion' ? 'is-prod' : 'is-neutral' }}"><i class="bi bi-diagram-3"></i> Modo: {{ $settings->environment === 'produccion' ? 'producción' : 'beta' }}</span>
    @php $certOk = filled($settings->certificate_path) && @is_file($settings->certificate_path); @endphp
    <span class="fe-chip {{ $certOk ? 'is-on' : 'is-off' }}"><i class="bi bi-{{ $certOk ? 'shield-check' : 'shield-x' }}"></i> {{ $certOk ? 'Certificado detectado' : 'Certificado no encontrado' }}</span>
    <form action="{{ route('facturacion.probar') }}" method="POST" class="ms-auto">@csrf
        <button class="btn btn-sm btn-brand btn-icon"><i class="bi bi-lightning-charge"></i> Probar conexión con SUNAT</button>
    </form>
</div>

@unless($greenterAvailable)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>
        Para la <strong>emisión real</strong> instala la librería Greenter en la raíz del proyecto:
        <code>composer require greenter/greenter</code>. Mientras tanto los comprobantes quedarán en estado <strong>pendiente</strong>.
    </div>
@endunless

<form action="{{ route('facturacion.guardar') }}" method="POST">@csrf

    {{-- ===== Estado y modo ===== --}}
    <div class="card card-accent mb-4">
        <div class="card-header"><span class="title"><i class="bi bi-lightning-charge"></i> Estado y modo</span><span class="text-muted small">Activación, forma de emisión y entorno de SUNAT</span></div>
        <div class="card-body">
            <div class="fe-switch">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" @checked($settings->enabled)>
                </div>
                <label for="enabled" class="fe-switch-label">
                    <strong>Habilitar facturación electrónica</strong>
                    <span>Si está desactivada, los pagos no generan comprobante ante SUNAT.</span>
                </label>
            </div>
            <div class="fe-switch mt-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="auto_emit" name="auto_emit" value="1" @checked($settings->auto_emit)>
                </div>
                <label for="auto_emit" class="fe-switch-label">
                    <strong>Emitir automáticamente al registrar el pago</strong>
                    <span>Cada boleta o factura se envía apenas se marca el pago como pagado.</span>
                </label>
            </div>
            <div class="fe-switch mt-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="boletas_por_resumen" name="boletas_por_resumen" value="1" @checked($settings->boletas_por_resumen)>
                </div>
                <label for="boletas_por_resumen" class="fe-switch-label">
                    <strong>Boletas por Resumen Diario (recomendado en producción)</strong>
                    <span>Las boletas no se envían una por una: quedan pendientes y se informan agrupadas por día en <em>Facturación → Resúmenes</em>.</span>
                </label>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label">Driver de emisión</label>
                    <select name="driver" class="form-select">
                        <option value="none" @selected($settings->driver === 'none')>Ninguno (no emite, deja pendiente)</option>
                        <option value="greenter" @selected($settings->driver === 'greenter')>Greenter (emisión real a SUNAT)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Entorno SUNAT</label>
                    <select name="environment" class="form-select">
                        <option value="beta" @selected($settings->environment === 'beta')>Beta (homologación / pruebas)</option>
                        <option value="produccion" @selected($settings->environment === 'produccion')>Producción</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Datos del emisor ===== --}}
    <div class="card mb-4">
        <div class="card-header"><span class="title"><i class="bi bi-building"></i> Datos del emisor</span><span class="text-muted small">Aparecen en el comprobante electrónico</span></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">RUC <span class="text-danger">*</span></label><input name="ruc" value="{{ old('ruc', $settings->ruc) }}" class="form-control" maxlength="11" placeholder="20000000001"></div>
                <div class="col-md-8"><label class="form-label">Razón social <span class="text-danger">*</span></label><input name="razon_social" value="{{ old('razon_social', $settings->razon_social) }}" class="form-control" placeholder="COLEGIO DEMO S.A.C."></div>
                <div class="col-md-6"><label class="form-label">Nombre comercial</label><input name="nombre_comercial" value="{{ old('nombre_comercial', $settings->nombre_comercial) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Dirección fiscal</label><input name="direccion_fiscal" value="{{ old('direccion_fiscal', $settings->direccion_fiscal) }}" class="form-control" placeholder="Av. Principal 123"></div>
                <div class="col-md-3"><label class="form-label">Ubigeo</label><input name="ubigeo" value="{{ old('ubigeo', $settings->ubigeo) }}" class="form-control" maxlength="6" placeholder="150101"></div>
                <div class="col-md-3"><label class="form-label">Departamento</label><input name="departamento" value="{{ old('departamento', $settings->departamento) }}" class="form-control" placeholder="LIMA"></div>
                <div class="col-md-3"><label class="form-label">Provincia</label><input name="provincia" value="{{ old('provincia', $settings->provincia) }}" class="form-control" placeholder="LIMA"></div>
                <div class="col-md-3"><label class="form-label">Distrito</label><input name="distrito" value="{{ old('distrito', $settings->distrito) }}" class="form-control" placeholder="LIMA"></div>
                <div class="col-12"><label class="form-label">Urbanización</label><input name="urbanizacion" value="{{ old('urbanizacion', $settings->urbanizacion) }}" class="form-control"></div>
            </div>
        </div>
    </div>

    {{-- ===== Credenciales SUNAT ===== --}}
    <div class="card mb-4">
        <div class="card-header"><span class="title"><i class="bi bi-key"></i> Credenciales SUNAT</span><span class="text-muted small">Clave SOL y certificado digital</span></div>
        <div class="card-body">
            <div class="alert alert-info py-2"><i class="bi bi-info-circle me-2"></i>En <strong>beta</strong> puedes usar RUC <code>20000000001</code> con usuario <code>MODDATOS</code> y clave <code>MODDATOS</code>.</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Usuario Clave SOL</label><input name="sol_user" value="{{ old('sol_user', $settings->sol_user) }}" class="form-control" placeholder="MODDATOS"></div>
                <div class="col-md-6"><label class="form-label">Clave SOL</label><input type="password" name="sol_pass" class="form-control" placeholder="{{ $settings->sol_pass ? '•••••••• (sin cambios)' : 'Clave SOL' }}"></div>
                <div class="col-md-8"><label class="form-label">Ruta del certificado (.pem)</label><input name="certificate_path" value="{{ old('certificate_path', $settings->certificate_path) }}" class="form-control" placeholder="C:\SAAS\saas_colegio\storage\facturacion\pe\certificate.pem">
                    @if(filled($settings->certificate_path) && !$certOk)<div class="text-danger small mt-1"><i class="bi bi-exclamation-circle"></i> No se encontró el certificado en la ruta indicada.</div>@endif
                </div>
                <div class="col-md-4"><label class="form-label">Clave del certificado</label><input type="password" name="certificate_password" class="form-control" placeholder="{{ $settings->certificate_password ? '•••••••• (sin cambios)' : 'Opcional' }}"></div>
            </div>
        </div>
    </div>

    {{-- ===== Series y parámetros ===== --}}
    <div class="card mb-4">
        <div class="card-header"><span class="title"><i class="bi bi-hash"></i> Series y parámetros</span><span class="text-muted small">Numeración de comprobantes e impuestos</span></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Serie factura</label><input name="serie_factura" value="{{ old('serie_factura', $settings->serie_factura ?: 'F001') }}" class="form-control" maxlength="4"></div>
                <div class="col-md-3"><label class="form-label">Serie boleta</label><input name="serie_boleta" value="{{ old('serie_boleta', $settings->serie_boleta ?: 'B001') }}" class="form-control" maxlength="4"></div>
                <div class="col-md-3"><label class="form-label">Serie N/C factura</label><input name="serie_nc_factura" value="{{ old('serie_nc_factura', $settings->serie_nc_factura ?: 'FC01') }}" class="form-control" maxlength="4"></div>
                <div class="col-md-3"><label class="form-label">Serie N/C boleta</label><input name="serie_nc_boleta" value="{{ old('serie_nc_boleta', $settings->serie_nc_boleta ?: 'BC01') }}" class="form-control" maxlength="4"></div>
                <div class="col-md-4"><label class="form-label">IGV (%)</label><input type="number" step="0.01" name="igv_percent" value="{{ old('igv_percent', $settings->igv_percent ?: 18) }}" class="form-control"></div>
                <div class="col-md-4">
                    <label class="form-label">Moneda</label>
                    <select name="moneda" class="form-select">
                        @foreach(['PEN'=>'Sol (PEN)','USD'=>'Dólar (USD)','EUR'=>'Euro (EUR)'] as $c=>$lbl)
                            <option value="{{ $c }}" @selected(($settings->moneda ?: 'PEN')===$c)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="fe-footer">
        <a href="{{ route('facturacion.index') }}" class="btn btn-light btn-icon"><i class="bi bi-arrow-left"></i> Volver</a>
        <button class="btn btn-brand btn-icon"><i class="bi bi-check-lg"></i> Guardar configuración</button>
    </div>
</form>
@endsection

@push('scripts')
<style>
.fe-banner{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;
    background:linear-gradient(135deg,var(--brand-3),var(--brand));color:#fff;border-radius:var(--radius);
    padding:22px 26px;box-shadow:var(--shadow)}
.fe-banner-main{display:flex;align-items:center;gap:18px}
.fe-banner-icon{width:60px;height:60px;border-radius:16px;background:rgba(255,255,255,.16);
    display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0}
.fe-banner-title{font-size:22px;font-weight:800;letter-spacing:-.3px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.fe-flag{font-size:13px;font-weight:600;background:rgba(255,255,255,.2);padding:3px 10px;border-radius:20px}
.fe-banner-sub{opacity:.92;font-size:13px;margin-top:4px;max-width:640px}
.fe-banner-right{text-align:right}
.fe-sunat-tag{background:#fff;color:var(--brand-3);font-weight:800;font-size:16px;padding:6px 16px;border-radius:10px;letter-spacing:1px;display:inline-block}
.fe-sunat-caption{font-size:11px;opacity:.9;margin-top:6px}
.fe-chips{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.fe-chip{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:600;padding:7px 14px;border-radius:30px;border:1px solid var(--line);background:#fff}
.fe-chip.is-on{background:var(--brand-soft);color:var(--brand-ink);border-color:transparent}
.fe-chip.is-off{background:#fee2e2;color:#991b1b;border-color:transparent}
.fe-chip.is-neutral{background:#eef1f4;color:#475467}
.fe-chip.is-prod{background:#fef3c7;color:#92400e}
.fe-switch{display:flex;align-items:flex-start;gap:14px;padding:14px 16px;border:1px solid var(--line);border-radius:12px;background:#f9fbfa}
.fe-switch .form-check-input{width:2.6em;height:1.4em;margin-top:2px;cursor:pointer}
.fe-switch .form-check-input:checked{background-color:var(--brand);border-color:var(--brand)}
.fe-switch-label{cursor:pointer;line-height:1.35}
.fe-switch-label span{display:block;color:var(--ink-2);font-size:12.5px;margin-top:2px}
.card-header .title{display:inline-flex;align-items:center}
.card-header{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap}
.fe-footer{position:sticky;bottom:0;background:linear-gradient(180deg,rgba(238,243,241,0),var(--body-bg) 40%);
    display:flex;justify-content:space-between;gap:12px;padding:16px 0 8px}
</style>
@endpush
