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
            <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}" @if ($errors->has('desde')) aria-invalid="true" @endif>@error('desde') <small class="field-error" role="alert">{{ $message }}</small> @enderror</label>
            <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}" @if ($errors->has('hasta')) aria-invalid="true" @endif>@error('hasta') <small class="field-error" role="alert">{{ $message }}</small> @enderror</label>
            <div class="filter-actions"><button type="submit" class="button button--primary">Filtrar</button><a href="{{ route('auditoria.index') }}" class="button button--ghost">Limpiar</a></div>
        </form>
    </section>

    <section class="panel" aria-label="Eventos registrados">
        @if ($eventos->isNotEmpty())
            <div class="table-wrap table-wrap--responsive admin-audit-table-wrap" data-responsive-table>
                <table class="data-table data-table--responsive admin-audit-table">
                    <thead>
                        <tr><th>Fecha y hora</th><th>Actor</th><th>Módulo y registro</th><th>Acción</th><th>Resumen</th><th>Detalles</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($eventos as $evento)
                            @php($detailsId = 'auditoria-detalles-'.$evento->id)
                            <tr class="admin-audit-row">
                                <td data-label="Fecha y hora"><time datetime="{{ $evento->created_at?->toIso8601String() }}">{{ $evento->created_at?->format('d/m/Y H:i:s') }}</time></td>
                                <td data-label="Actor">{{ $evento->usuario?->username ?? 'Sistema o usuario retirado' }}</td>
                                <td data-label="Módulo y registro" class="admin-audit-record">
                                    <strong>{{ $entidades[$evento->entidad] ?? $evento->entidad }}</strong>
                                    <span>{{ $evento->etiqueta ?: '#'.$evento->entidad_id }} <small>#{{ $evento->entidad_id }}</small></span>
                                </td>
                                <td data-label="Acción">
                                    <x-ui.status-badge :tone="$evento->accion === 'ELIMINADO' ? 'danger' : ($evento->accion === 'CREADO' ? 'success' : 'info')">
                                        {{ ucfirst(mb_strtolower($evento->accion)) }}
                                    </x-ui.status-badge>
                                </td>
                                <td data-label="Resumen">
                                    @if ($evento->estado_anterior !== null && $evento->estado_nuevo !== null)
                                        Cambio de estado
                                    @elseif ($evento->campos)
                                        {{ count($evento->campos) }} {{ count($evento->campos) === 1 ? 'campo registrado' : 'campos registrados' }}
                                    @else
                                        Sin datos adicionales
                                    @endif
                                </td>
                                <td data-label="Detalles">
                                    <x-ui.table-details-toggle :target="$detailsId" :label="'Ver detalles del evento '.$evento->id" />
                                </td>
                            </tr>
                            <x-ui.table-row-details :id="$detailsId" :colspan="6">
                                <dl class="table-details-grid admin-audit-details">
                                    <div><dt>Registro</dt><dd>{{ $evento->etiqueta ?: '#'.$evento->entidad_id }} · #{{ $evento->entidad_id }}</dd></div>
                                    @if ($evento->estado_anterior !== null && $evento->estado_nuevo !== null)
                                        <div><dt>Estado anterior</dt><dd>{{ $evento->estado_anterior }}</dd></div>
                                        <div><dt>Estado nuevo</dt><dd>{{ $evento->estado_nuevo }}</dd></div>
                                    @elseif ($evento->campos)
                                        <div><dt>Campos registrados</dt><dd>{{ implode(', ', array_map(fn ($campo) => str_replace('_', ' ', $campo), $evento->campos)) }}</dd></div>
                                    @else
                                        <div><dt>Detalle</dt><dd>No se registraron datos adicionales para este evento.</dd></div>
                                    @endif
                                </dl>
                            </x-ui.table-row-details>
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
