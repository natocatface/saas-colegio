@extends('layouts.app')
@section('title', 'Colegios')

@section('content')
<div class="page-head">
    <div><h1>Colegios</h1><div class="breadcrumb-mini">Gestión global de instituciones (tenants)</div></div>
</div>

<div class="card"><div class="card-body">
    <form class="row g-2 mb-3">
        <div class="col-md-6"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Buscar colegio..."></div>
        <div class="col-md-3"><select name="status" class="form-select"><option value="">Todos</option><option value="activo" @selected(request('status')=='activo')>Activos</option><option value="suspendido" @selected(request('status')=='suspendido')>Suspendidos</option></select></div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary btn-icon"><i class="bi bi-search"></i> Filtrar</button></div>
    </form>
    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><th class="ps-3">Colegio</th><th>Plan</th><th>Usuarios</th><th>Estudiantes</th><th>Prueba hasta</th><th>Estado</th><th class="text-end pe-3"></th></tr></thead>
        <tbody>
        @forelse($schools as $s)
            <tr>
                <td class="ps-3"><strong>{{ $s->name }}</strong><div class="small text-muted">{{ $s->email ?? $s->slug }}</div></td>
                <td><span class="badge bg-light text-dark border text-capitalize">{{ $s->plan }}</span></td>
                <td>{{ $s->users_count }}</td>
                <td>{{ $s->students_count }}</td>
                <td class="small text-muted">{{ optional($s->trial_ends_at)->format('d/m/Y') ?? '—' }}</td>
                <td><span class="badge-soft {{ $s->status=='activo' ? 'badge-activo':'badge-vencido' }}">{{ ucfirst($s->status) }}</span></td>
                <td class="text-end pe-3"><a href="{{ route('schools.show', $s) }}" class="btn btn-sm btn-light"><i class="bi bi-eye"></i> Ver</a></td>
            </tr>
        @empty<tr><td colspan="7"><div class="empty-state"><i class="bi bi-buildings"></i><p>No hay colegios registrados</p></div></td></tr>@endforelse
        </tbody>
    </table></div>
    {{ $schools->links() }}
</div></div>
@endsection
