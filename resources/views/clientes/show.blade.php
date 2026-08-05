@extends('layouts.app')

@section('title', $cliente->nombreVisible())
@section('page-kicker', 'Clientes')
@section('page-title', 'Detalle del cliente')

@section('content')
    @php
        $faltaDireccionFiscal = $cliente->requiereDireccionFiscal()
            && ! $cliente->tieneDireccionFiscalActiva();
    @endphp

    <a href="{{ route('clientes.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a clientes
    </a>

    <section class="client-detail-hero">
        <div class="client-detail-hero__main">
            <span class="client-detail-hero__icon">
                <x-ui.icon name="suppliers" :size="28" />
            </span>
            <div>
                <div class="client-detail-hero__badges">
                    <x-ui.status-badge :tone="$cliente->estado ? 'success' : 'neutral'">
                        {{ $cliente->estado ? 'ACTIVO' : 'INACTIVO' }}
                    </x-ui.status-badge>
                    <span class="client-type-chip">{{ $cliente->tipoCliente?->nombre ?? 'Sin tipo' }}</span>
                    @if ($cliente->es_mostrador)
                        <span class="badge badge--info">SISTEMA</span>
                    @endif
                </div>
                <h1>{{ $cliente->nombreVisible() }}</h1>
                <p>
                    @if (
                        $cliente->tipo_documento === 'RUC'
                        && filled($cliente->nombre_comercial)
                    )
                        {{ $cliente->razon_social }}
                    @else
                        {{ $cliente->documentoVisible() }}
                    @endif
                </p>
            </div>
        </div>

        <div class="client-detail-hero__actions">
            <a href="{{ route('clientes.edit', $cliente->id) }}" class="button button--ghost">
                <x-ui.icon name="edit" :size="17" />
                Editar
            </a>
            @unless ($cliente->es_mostrador)
                <form
                    method="POST"
                    action="{{ route('clientes.toggle', $cliente->id) }}"
                    data-confirm="¿Confirmas cambiar el estado del cliente?"
                >
                    @csrf
                    @method('PATCH')
                    <button class="button button--{{ $cliente->estado ? 'danger' : 'primary' }}">
                        <x-ui.icon name="power" :size="17" />
                        {{ $cliente->estado ? 'Desactivar' : 'Activar' }}
                    </button>
                </form>
            @endunless
        </div>
    </section>

    <section class="client-detail-grid">
        <article class="panel client-info-panel">
            <header class="panel-heading">
                <div>
                    <p class="eyebrow">Información principal</p>
                    <h2>Datos comerciales</h2>
                </div>
            </header>

            <dl class="client-info-grid">
                <div>
                    <dt>Documento</dt>
                    <dd>{{ $cliente->documentoVisible() }}</dd>
                </div>
                <div>
                    <dt>Nombre registrado</dt>
                    <dd>{{ $cliente->nombreFacturacion() }}</dd>
                </div>
                <div>
                    <dt>Tipo de cliente</dt>
                    <dd>{{ $cliente->tipoCliente?->nombre ?? 'Sin tipo' }}</dd>
                </div>
                <div>
                    <dt>Contacto</dt>
                    <dd>{{ $cliente->contacto ?: 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ $cliente->telefono ?: 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Correo</dt>
                    <dd>{{ $cliente->correo ?: 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Órdenes vinculadas</dt>
                    <dd>{{ $cliente->ordenesOperacion->count() }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel client-stat-panel">
            <div class="client-stat-panel__item">
                <span><x-ui.icon name="map-pin" :size="22" /></span>
                <div><strong>{{ $cliente->direcciones->count() }}</strong><small>Direcciones</small></div>
            </div>
            <div class="client-stat-panel__item">
                <span><x-ui.icon name="car" :size="22" /></span>
                <div><strong>{{ $cliente->vehiculos->count() }}</strong><small>Vehículos</small></div>
            </div>
            <div class="client-stat-panel__item">
                <span><x-ui.icon name="orders" :size="22" /></span>
                <div><strong>{{ $cliente->ordenesOperacion->whereIn('estado', ['ABIERTA', 'EN_PROCESO'])->count() }}</strong><small>Órdenes activas</small></div>
            </div>
        </article>
    </section>

    <section class="panel client-related-panel">
        <header class="panel-heading client-related-panel__heading">
            <div>
                <p class="eyebrow">Puntos de atención</p>
                <h2>Direcciones</h2>
            </div>
            <a href="{{ route('clientes.direcciones.create', $cliente->id) }}" class="button button--primary button--small">
                <x-ui.icon name="plus" :size="16" />
                Nueva dirección
            </a>
        </header>

        @if ($faltaDireccionFiscal)
            <div class="notice notice--warning notice--block client-fiscal-warning">
                <x-ui.icon name="warning" :size="19" />
                <div>
                    <strong>Dirección fiscal pendiente</strong>
                    <span>
                        Esta empresa con RUC no tiene una dirección fiscal activa.
                        Los registros existentes no se reasignaron automáticamente;
                        identifica la dirección correcta antes de usar este dato comercialmente.
                    </span>
                </div>
            </div>
        @endif

        @if ($cliente->direcciones->isNotEmpty())
            <div class="client-card-grid">
                @foreach ($cliente->direcciones as $direccion)
                    <article class="client-related-card {{ ! $direccion->estado ? 'client-related-card--inactive' : '' }}">
                        <div class="client-related-card__top">
                            <span class="client-related-card__icon"><x-ui.icon name="map-pin" :size="20" /></span>
                            <div>
                                <div class="client-related-card__badges">
                                    @if ($direccion->es_fiscal)
                                        <x-ui.status-badge tone="accent">
                                            DIRECCIÓN FISCAL
                                        </x-ui.status-badge>
                                    @else
                                        <x-ui.status-badge tone="neutral">
                                            ADICIONAL
                                        </x-ui.status-badge>
                                    @endif
                                    @if ($direccion->es_principal)
                                        <span class="badge badge--info">PRINCIPAL</span>
                                    @endif
                                    <span class="badge badge--{{ $direccion->estado ? 'success' : 'neutral' }}">
                                        {{ $direccion->estado ? 'ACTIVA' : 'INACTIVA' }}
                                    </span>
                                </div>
                                <strong>
                                    {{ $direccion->destino
                                        ?: ($direccion->es_fiscal
                                            ? 'Dirección fiscal'
                                            : 'Dirección adicional') }}
                                </strong>
                            </div>
                        </div>

                        <p>{{ $direccion->direccion }}</p>
                        <small>{{ $direccion->ubicacionVisible() ?: 'Ubicación no especificada' }}</small>
                        @if ($direccion->referencia)
                            <small>Referencia: {{ $direccion->referencia }}</small>
                        @endif

                        <div class="client-related-card__actions">
                            <a
                                href="{{ route('clientes.direcciones.edit', [$cliente->id, $direccion->id]) }}"
                                class="button button--ghost button--small"
                            >
                                <x-ui.icon name="edit" :size="15" /> Editar
                            </a>
                            @unless ($direccion->es_principal)
                                <form method="POST" action="{{ route('clientes.direcciones.principal', [$cliente->id, $direccion->id]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="button button--ghost button--small">Hacer principal</button>
                                </form>
                            @endunless
                            <form
                                method="POST"
                                action="{{ route('clientes.direcciones.toggle', [$cliente->id, $direccion->id]) }}"
                                data-confirm="¿Confirmas cambiar el estado de esta dirección?"
                            >
                                @csrf
                                @method('PATCH')
                                <button class="icon-button icon-button--{{ $direccion->estado ? 'danger' : 'success' }}" title="Cambiar estado">
                                    <x-ui.icon name="power" :size="16" />
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="empty-table-state client-related-empty">
                <span class="empty-state__icon"><x-ui.icon name="map-pin" :size="29" /></span>
                <strong>Sin direcciones registradas</strong>
                <span>Agrega un punto de entrega o atención para este cliente.</span>
            </div>
        @endif
    </section>

    <section class="panel client-related-panel">
        <header class="panel-heading client-related-panel__heading">
            <div>
                <p class="eyebrow">Unidades asociadas</p>
                <h2>Vehículos</h2>
            </div>
            @unless ($cliente->es_mostrador)
                <a href="{{ route('clientes.vehiculos.create', $cliente->id) }}" class="button button--primary button--small">
                    <x-ui.icon name="plus" :size="16" />
                    Nuevo vehículo
                </a>
            @endunless
        </header>

        @if ($cliente->vehiculos->isNotEmpty())
            <div class="client-card-grid">
                @foreach ($cliente->vehiculos as $vehiculo)
                    <article class="client-related-card {{ ! $vehiculo->estado ? 'client-related-card--inactive' : '' }}">
                        <div class="client-related-card__top">
                            <span class="client-related-card__icon"><x-ui.icon name="car" :size="20" /></span>
                            <div>
                                <div class="client-related-card__badges">
                                    @if ($vehiculo->es_comodin)
                                        <span class="badge badge--info">COMODÍN</span>
                                    @endif
                                    <span class="badge badge--{{ $vehiculo->estado ? 'success' : 'danger' }}">
                                        {{ $vehiculo->estado ? 'ACTIVO' : 'INACTIVO' }}
                                    </span>
                                </div>
                                <strong>{{ $vehiculo->identificadorVisible() }}</strong>
                            </div>
                        </div>

                        <p>{{ $vehiculo->descripcionVisible() }}</p>
                        <small>
                            {{ collect([$vehiculo->anio, $vehiculo->color])->filter()->implode(' · ') ?: 'Sin año ni color' }}
                        </small>

                        <small>
                            {{ $vehiculo->ordenes_operacion_count }}
                            {{ $vehiculo->ordenes_operacion_count === 1
                                ? 'orden vinculada'
                                : 'órdenes vinculadas' }}
                        </small>

                        <div class="client-related-card__actions">
                            <a
                                href="{{ route(
                                    'clientes.vehiculos.show',
                                    [$cliente->id, $vehiculo->id]
                                ) }}"
                                class="button button--ghost button--small"
                            >
                                <x-ui.icon name="orders" :size="15" />
                                Ver historial
                            </a>

                            @unless ($vehiculo->es_comodin)
                                <a
                                    href="{{ route(
                                        'clientes.vehiculos.edit',
                                        [$cliente->id, $vehiculo->id]
                                    ) }}"
                                    class="button button--ghost button--small"
                                >
                                    <x-ui.icon name="edit" :size="15" />
                                    Editar
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route(
                                        'clientes.vehiculos.toggle',
                                        [$cliente->id, $vehiculo->id]
                                    ) }}"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        class="icon-button icon-button--{{
                                            $vehiculo->estado
                                                ? 'danger'
                                                : 'success'
                                        }}"
                                        title="Cambiar estado"
                                    >
                                        <x-ui.icon name="power" :size="16" />
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="empty-table-state client-related-empty">
                <span class="empty-state__icon"><x-ui.icon name="car" :size="29" /></span>
                <strong>Sin vehículos registrados</strong>
                <span>Registra la primera unidad vinculada al cliente.</span>
            </div>
        @endif
    </section>
@endsection
