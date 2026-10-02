@extends('layouts.app')

@section('title', 'Auditoría de operaciones')
@section('page-kicker', 'Administración')
@section('page-title', 'Auditoría de operaciones')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Trazabilidad</p>
            <h1>Auditoría de operaciones</h1>
            <p>Creaciones, cambios y eliminaciones registrados desde la activación del módulo. Muestra nombres de campos, sin contraseñas ni datos anteriores de los formularios.</p>
        </div>
        <a href="{{ route('auditoria.csv', request()->only(['entidad', 'accion', 'desde', 'hasta'])) }}" class="button button--ghost" data-file-download>Descargar CSV</a>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('auditoria.index') }}" class="supplier-invoice-filter">
            <label class="form-field"><span>Módulo</span><select name="entidad"><option value="">Todos</option>@foreach ($entidades as $clave => $nombre)<option value="{{ $clave }}" @selected(request('entidad') === $clave)>{{ $nombre }}</option>@endforeach</select></label>
            <label class="form-field"><span>Acción</span><select name="accion"><option value="">Todas</option><option value="CREADO" @selected(request('accion') === 'CREADO')>Creado</option><option value="ACTUALIZADO" @selected(request('accion') === 'ACTUALIZADO')>Actualizado</option><option value="ELIMINADO" @selected(request('accion') === 'ELIMINADO')>Eliminado</option></select></label>
            <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
            <div class="filter-actions"><button type="submit" class="button button--primary">Filtrar</button><a href="{{ route('auditoria.index') }}" class="button button--ghost">Limpiar</a></div>
        </form>
        @error('desde') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        @error('hasta') <p class="field-error" role="alert">{{ $message }}</p> @enderror
    </section>

    <section class="panel" aria-label="Eventos registrados">
        @if ($eventos->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-list-table">
                    <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Módulo</th><th>Registro</th><th>Acción</th><th>Detalle</th></tr></thead>
                    <tbody>
                        @foreach ($eventos as $evento)
                            <tr>
                                <td data-label="Fecha y hora"><time datetime="{{ $evento->created_at?->toIso8601String() }}">{{ $evento->created_at?->format('d/m/Y H:i:s') }}</time></td>
                                <td data-label="Usuario">{{ $evento->usuario?->username ?? 'Sistema o usuario retirado' }}</td>
                                <td data-label="Módulo">{{ $entidades[$evento->entidad] ?? $evento->entidad }}</td>
                                <td data-label="Registro">{{ $evento->etiqueta ?: '#'.$evento->entidad_id }} <small>#{{ $evento->entidad_id }}</small></td>
                                <td data-label="Acción"><span class="badge badge--{{ $evento->accion === 'ELIMINADO' ? 'danger' : ($evento->accion === 'CREADO' ? 'success' : 'info') }}">{{ ucfirst(mb_strtolower($evento->accion)) }}</span></td>
                                <td data-label="Detalle">
                                    @if ($evento->estado_anterior !== null && $evento->estado_nuevo !== null)
                                        Estado: {{ $evento->estado_anterior }} → {{ $evento->estado_nuevo }}
                                    @elseif ($evento->campos)
                                        Campos: {{ implode(', ', array_map(fn ($campo) => str_replace('_', ' ', $campo), $evento->campos)) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$eventos" />
        @else
            <div class="empty-table-state"><strong>No hay eventos registrados con estos filtros</strong><span>Los nuevos cambios aparecerán al operar los módulos supervisados.</span></div>
        @endif
    </section>
@endsection
