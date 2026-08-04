@extends('layouts.app')

@section('title', 'Clientes')
@section('page-kicker', 'Comercial y logística')
@section('page-title', 'Clientes')

@section('content')
    <x-ui.page-header
        kicker="Cartera comercial"
        title="Clientes"
        description="Administra datos comerciales, direcciones de entrega y vehículos vinculados a las órdenes de operación."
    >
        <x-slot:actions>
            <a href="{{ route('tipos-cliente.index') }}" class="button button--ghost">
                <x-ui.icon name="tag" :size="17" />
                Tipos de cliente
            </a>
            <a href="{{ route('clientes.create') }}" class="button button--primary">
                <x-ui.icon name="plus" :size="18" />
                Nuevo cliente
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="summary-strip summary-strip--four client-summary-strip">
        @foreach ([
            ['Total', 'users', 'neutral', $resumen['total']],
            ['Activos', 'check-circle', 'success', $resumen['activos']],
            ['Con vehículos', 'car', 'info', $resumen['con_vehiculos']],
            ['Con órdenes', 'orders', 'warning', $resumen['con_ordenes']],
        ] as [$titulo, $icono, $tono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}">
                    <x-ui.icon :name="$icono" :size="21" />
                </span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('clientes.index') }}" class="client-filter-grid">
            <label class="form-field client-filter-grid__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="search" :size="17" />
                    </span>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Nombre, razón social, documento o contacto"
                    >
                </div>
            </label>

            <label class="form-field">
                <span>Tipo de cliente</span>
                <select name="tipo_cliente_id">
                    <option value="">Todos</option>
                    @foreach ($tipos as $tipo)
                        <option
                            value="{{ $tipo->id }}"
                            @selected((int) request('tipo_cliente_id') === $tipo->id)
                        >
                            {{ $tipo->nombre }}
                        </option>
                    @endforeach
                </select>
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
                <a href="{{ route('clientes.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <section class="panel {{ $clientes->isEmpty() ? 'panel--empty-list' : '' }}">
        @if ($clientes->isNotEmpty())
            <div class="table-wrap table-wrap--responsive">
                <table class="data-table data-table--actions client-list-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Contacto</th>
                            <th class="text-center">Direcciones</th>
                            <th class="text-center">Vehículos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clientes as $cliente)
                            <tr>
                                <td>
                                    <a
                                        href="{{ route('clientes.show', $cliente->id) }}"
                                        class="table-primary-link"
                                    >
                                        {{ $cliente->nombreVisible() }}
                                    </a>
                                    <span>
                                        {{ $cliente->documentoVisible() }}
                                        @if ($cliente->es_mostrador)
                                            · Registro del sistema
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <span class="client-type-chip">
                                        {{ $cliente->tipoCliente?->nombre ?? 'Sin tipo' }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $cliente->contacto ?: 'Sin contacto' }}</strong>
                                    <span>{{ $cliente->telefono ?: ($cliente->correo ?: 'Sin datos') }}</span>
                                </td>
                                <td class="text-center">{{ $cliente->direcciones_count }}</td>
                                <td class="text-center">{{ $cliente->vehiculos_count }}</td>
                                <td>
                                    <x-ui.status-badge :tone="$cliente->estado ? 'success' : 'danger'">
                                        {{ $cliente->estado ? 'ACTIVO' : 'INACTIVO' }}
                                    </x-ui.status-badge>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a
                                            href="{{ route('clientes.show', $cliente->id) }}"
                                            class="icon-button"
                                            title="Ver cliente"
                                            aria-label="Ver cliente {{ $cliente->nombreVisible() }}"
                                        >
                                            <x-ui.icon name="eye" :size="17" />
                                        </a>
                                        <a
                                            href="{{ route('clientes.edit', $cliente->id) }}"
                                            class="icon-button"
                                            title="Editar cliente"
                                            aria-label="Editar cliente {{ $cliente->nombreVisible() }}"
                                        >
                                            <x-ui.icon name="edit" :size="17" />
                                        </a>
                                        @unless ($cliente->es_mostrador)
                                            <form
                                                method="POST"
                                                action="{{ route('clientes.toggle', $cliente->id) }}"
                                                data-confirm="¿Confirmas cambiar el estado de este cliente?"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    class="icon-button icon-button--{{ $cliente->estado ? 'danger' : 'success' }}"
                                                    title="{{ $cliente->estado ? 'Desactivar' : 'Activar' }}"
                                                    aria-label="{{ $cliente->estado ? 'Desactivar' : 'Activar' }} cliente {{ $cliente->nombreVisible() }}"
                                                >
                                                    <x-ui.icon name="power" :size="17" />
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$clientes" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon">
                    <x-ui.icon name="users" :size="30" />
                </span>
                <strong>No se encontraron clientes</strong>
                <span>Ajusta los filtros o registra el primer cliente.</span>
                <div class="empty-table-state__actions">
                    <a href="{{ route('clientes.create') }}" class="button button--primary button--small">
                        <x-ui.icon name="plus" :size="16" />
                        Nuevo cliente
                    </a>
                </div>
            </div>
        @endif
    </section>
@endsection
