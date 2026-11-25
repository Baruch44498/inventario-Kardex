@extends('layouts.app')

@section('title', 'Empleados')
@section('page-kicker', 'Administración')
@section('page-title', 'Empleados')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Catálogo interno</p>
            <h1>Lista de empleados</h1>
            <p>
                Administra los nombres y DNI que después se utilizarán para identificar
                a quien recibe materiales de Almacén.
            </p>
        </div>

        <a href="{{ route('empleados.create') }}" class="button button--primary">
            <x-ui.icon name="plus" :size="18" />
            Nuevo empleado
        </a>
    </section>

    <section class="summary-strip summary-strip--three">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral">
                <x-ui.icon name="users" :size="21" />
            </span>
            <div><span>Total</span><strong>{{ (int) ($resumen->total ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="check-circle" :size="21" />
            </span>
            <div><span>Activos</span><strong>{{ (int) ($resumen->activos ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--danger">
                <x-ui.icon name="power" :size="21" />
            </span>
            <div><span>Inactivos</span><strong>{{ (int) ($resumen->inactivos ?? 0) }}</strong></div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('empleados.index') }}" class="user-filter-grid">
            <label class="form-field user-filter-grid__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="search" :size="17" />
                    </span>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Nombre o DNI"
                    >
                </div>
            </label>

            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="1" @selected(request('estado') === '1')>Activo</option>
                    <option value="0" @selected(request('estado') === '0')>Inactivo</option>
                </select>
            </label>

            <div class="filter-actions">
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="filter" :size="17" />
                    Filtrar
                </button>
                <a href="{{ route('empleados.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <section class="panel {{ $empleados->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($empleados->count() > 0)
            <div class="table-wrap table-wrap--responsive table-wrap--cards">
                <table class="data-table data-table--actions user-list-table data-table--cards" role="table">
                    <thead role="rowgroup">
                        <tr role="row">
                            <th role="columnheader" scope="col">Empleado</th>
                            <th role="columnheader" scope="col">DNI</th>
                            <th role="columnheader" scope="col">Acceso al sistema</th>
                            <th role="columnheader" scope="col">Estado</th>
                            <th role="columnheader" scope="col">Actualización</th>
                            <th role="columnheader" scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        @foreach ($empleados as $empleado)
                            <tr role="row">
                                <td class="table-card__wide" role="cell" data-label="Empleado"><strong>{{ $empleado->nombre_completo }}</strong></td>
                                <td role="cell" data-label="DNI">{{ $empleado->dni }}</td>
                                <td role="cell" data-label="Acceso al sistema">
                                    @if ($empleado->usuario)
                                        <strong>{{ $empleado->usuario->username }}</strong>
                                        <span>
                                            {{ $empleado->usuario->esAdministradorPrincipal()
                                                ? 'Administrador principal'
                                                : ($empleado->usuario->role?->nombre ?? 'Sin rol') }}
                                        </span>
                                        <x-ui.status-badge :tone="$empleado->usuario->estado ? 'success' : 'neutral'">
                                            {{ $empleado->usuario->estado ? 'ACCESO ACTIVO' : 'ACCESO INACTIVO' }}
                                        </x-ui.status-badge>
                                    @else
                                        <span class="badge badge--neutral">SIN USUARIO</span>
                                    @endif
                                </td>
                                <td role="cell" data-label="Estado">
                                    <x-ui.status-badge :tone="$empleado->estado ? 'success' : 'neutral'">
                                        {{ $empleado->estado ? 'ACTIVO' : 'INACTIVO' }}
                                    </x-ui.status-badge>
                                </td>
                                <td role="cell" data-label="Actualización">{{ $empleado->updated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="table-card__wide" role="cell" data-label="Acciones">
                                    @php
                                        $actor = auth()->user();
                                        $usuarioVinculado = $empleado->usuario;
                                        $esEmpleadoAdministrativo = $usuarioVinculado?->esAdministrador() ?? false;
                                        $puedeEditar = ! $esEmpleadoAdministrativo
                                            || $actor->esAdministradorPrincipal();
                                        $puedeCambiarEstado = $puedeEditar
                                            && ! ($usuarioVinculado?->esAdministradorPrincipal() ?? false);
                                    @endphp
                                    <div class="table-actions">
                                        @if ($puedeEditar)
                                            <a
                                                href="{{ route('empleados.edit', $empleado) }}"
                                                class="icon-button"
                                                title="Editar a {{ $empleado->nombre_completo }}, DNI {{ $empleado->dni }}"
                                                aria-label="Editar a {{ $empleado->nombre_completo }}, DNI {{ $empleado->dni }}"
                                            >
                                                <x-ui.icon name="edit" :size="17" />
                                            </a>
                                        @endif

                                        @if ($puedeCambiarEstado)
                                            <form
                                                method="POST"
                                                action="{{ route('empleados.toggle', $empleado) }}"
                                                data-confirm-title="{{ $empleado->estado ? 'Desactivar empleado' : 'Activar empleado' }}"
                                                data-confirm="{{ $empleado->estado
                                                    ? '¿Desactivar a '.$empleado->nombre_completo.' (DNI '.$empleado->dni.')? '.($usuarioVinculado
                                                        ? 'También se desactivará su usuario y reactivar al empleado no restaurará su acceso automáticamente.'
                                                        : 'Dejará de estar disponible para nuevas operaciones.')
                                                    : '¿Activar a '.$empleado->nombre_completo.' (DNI '.$empleado->dni.')? '.($usuarioVinculado
                                                        ? 'Su usuario seguirá inactivo hasta una reactivación manual.'
                                                        : 'Volverá a estar disponible para nuevas operaciones.') }}"
                                                data-confirm-label="{{ $empleado->estado ? 'Desactivar empleado' : 'Activar empleado' }}"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    class="icon-button icon-button--{{ $empleado->estado ? 'danger' : 'success' }}"
                                                    title="{{ ($empleado->estado ? 'Desactivar' : 'Activar').' a '.$empleado->nombre_completo.', DNI '.$empleado->dni }}"
                                                    aria-label="{{ ($empleado->estado ? 'Desactivar' : 'Activar').' a '.$empleado->nombre_completo.', DNI '.$empleado->dni }}"
                                                >
                                                    <x-ui.icon name="power" :size="17" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$empleados" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon">
                    <x-ui.icon name="users" :size="30" />
                </span>
                <strong>No hay empleados para mostrar</strong>
                <span>Ajusta los filtros o registra un empleado nuevo.</span>
            </div>
        @endif
    </section>
@endsection
