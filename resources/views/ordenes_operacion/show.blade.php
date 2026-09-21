@extends('layouts.app')

@section('title', $orden->codigo_orden)
@section('page-kicker', 'Órdenes de operación')
@section('page-title', $orden->codigo_orden)

@section('content')
    @php
        $estadoClase = match ($orden->estado) {
            'ABIERTA' => 'info',
            'EN_PROCESO' => 'warning',
            'CERRADA' => 'success',
            'ANULADA' => 'danger',
            default => 'neutral',
        };

        $vehiculo = $orden->vehiculo?->placa
            ?? 'Sin vehículo';

        $descripcion = trim((string) $orden->descripcion);
        $tieneDescripcion = $descripcion !== ''
            && ! preg_match('/^[\s\-_.]+$/u', $descripcion);

        $esVentaDirecta = $orden->tipoOrden?->codigo === 'OV';

        $puedeEditarOrden =
            auth()->user()->esAdministrador()
            || ($esVentaDirecta && auth()->user()->puede('ordenes.editar_venta'))
            || (! $esVentaDirecta && auth()->user()->puede('ordenes.editar_comercial'));

        $puedeAnularOrden =
            auth()->user()->esAdministrador()
            || ($esVentaDirecta && auth()->user()->puede('ordenes.anular_venta'))
            || (! $esVentaDirecta && auth()->user()->puede('ordenes.anular_comercial'));

        $puedeGestionarEstado =
            auth()->user()->puede('ordenes.gestionar_estado');

        $puedeRegistrarSalida =
            auth()->user()->puede('salidas.registrar');

        $puedeGestionarReservas =
            auth()->user()->esAdministrador()
            || auth()->user()->puede('inventario.configurar')
            || auth()->user()->puede('produccion.gestionar');

        $puedeGestionarMateriales =
            auth()->user()->esAdministrador()
            || auth()->user()->puede('produccion.gestionar');

        $admiteReservas = in_array($orden->tipoOrden?->codigo, ['OM', 'OS', 'OP'], true);
        $puedeVerCostos = auth()->user()->puede('ordenes.ver_costos');
        $puedeGestionarCostos = auth()->user()->puede('ordenes.gestionar_costos');
        $avanceCompleto = (float) $resumenEjecucion['avance_operativo'] >= 99.999;
        $sinHerramientasPendientes = $herramientasEnUso->isEmpty();
        $puedeCerrarOperacion = $orden->estaEnProceso()
            && $avanceCompleto
            && $sinHerramientasPendientes;
    @endphp

    <div class="operation-page operation-page--show" data-operation-tabs-root>
        <section class="operation-hero">
            <div class="operation-hero__content">
                <a
                    href="{{ route('ordenes-operacion.index') }}"
                    class="back-link operation-hero__back"
                >
                    <x-ui.icon name="arrow-left" :size="17" />
                    Volver a órdenes de operación
                </a>

                <p class="eyebrow">
                    {{ $orden->tipoOrden?->nombre ?? 'Orden operacional' }}
                </p>

                <h1>{{ $orden->codigo_orden }}</h1>

            </div>

            <div class="operation-hero__actions">
                <span class="badge badge--{{ $estadoClase }}">
                    {{ str_replace('_', ' ', $orden->estado) }}
                </span>

                <div class="operation-hero__buttons">
                    @if ($puedeVerCostos && ! $esVentaDirecta)
                        <a href="{{ route('ordenes-operacion.gasto-real', $orden) }}" class="button button--ghost">Gasto real y Excel</a>
                    @endif
                    @if ($orden->puedeEditar() && $puedeEditarOrden && ! $orden->cotizacionCliente)
                        <a
                            href="{{ route('ordenes-operacion.edit', $orden->id) }}"
                            class="button button--ghost"
                        >
                            <x-ui.icon name="edit" :size="17" />
                            Editar
                        </a>
                    @endif

                    @if ($puedeRegistrarSalida && $orden->estaEnProceso())
                        <a
                            href="{{ route('notas-salida.create', ['orden_operacion_id' => $orden->id]) }}"
                            class="button button--primary"
                        >
                            <x-ui.icon name="exit" :size="17" />
                            Registrar salida
                        </a>
                    @endif
                </div>
            </div>
        </section>

        @if ($orden->estaAnulada())
            <div class="notice notice--danger notice--block">
                <x-ui.icon name="error" :size="18" />
                <div>
                    <strong>Orden anulada</strong>
                    <span>
                        {{ $orden->motivo_anulacion }}
                        · {{ $orden->anulado_en?->format('d/m/Y H:i') }}
                        · {{ $orden->anulador?->username ?? '—' }}
                    </span>
                </div>
            </div>
        @endif


        <nav class="operation-detail-tabs" role="tablist" aria-label="Secciones de la orden" data-operation-tabs>
            <button type="button" id="operation-tab-resumen" class="operation-detail-tab" role="tab" aria-controls="operation-panel-resumen" aria-selected="true" tabindex="0" data-operation-tab="resumen">Resumen</button>
            @if ($admiteReservas)
                <button type="button" id="operation-tab-ejecucion" class="operation-detail-tab" role="tab" aria-controls="operation-panel-ejecucion" aria-selected="false" tabindex="-1" data-operation-tab="ejecucion">Ejecución</button>
            @endif
            @if ($orden->cotizacionCliente || $admiteReservas)
                <button type="button" id="operation-tab-materiales" class="operation-detail-tab" role="tab" aria-controls="operation-panel-materiales" aria-selected="false" tabindex="-1" data-operation-tab="materiales">
                    Materiales
                    <span class="operation-detail-tab__count" aria-hidden="true">{{ $orden->materialesRequeridos->count() }}</span>
                </button>
            @endif
            @if ($admiteReservas)
                <button type="button" id="operation-tab-reservas" class="operation-detail-tab" role="tab" aria-controls="operation-panel-reservas" aria-selected="false" tabindex="-1" data-operation-tab="reservas">
                    Reservas
                    <span class="operation-detail-tab__count" aria-hidden="true">{{ $orden->reservasMateriales->where('estado', 'ACTIVA')->count() }}</span>
                </button>
            @endif
            <button type="button" id="operation-tab-herramientas" class="operation-detail-tab" role="tab" aria-controls="operation-panel-herramientas" aria-selected="false" tabindex="-1" data-operation-tab="herramientas">
                Herramientas
                <span class="operation-detail-tab__count" aria-hidden="true">{{ $herramientasEnUso->count() }}</span>
            </button>
            <button type="button" id="operation-tab-abastecimiento" class="operation-detail-tab" role="tab" aria-controls="operation-panel-abastecimiento" aria-selected="false" tabindex="-1" data-operation-tab="abastecimiento">Abastecimiento</button>
        </nav>

        <div class="operation-tab-panels" data-operation-tab-panels>
            <section id="operation-panel-resumen" class="operation-tab-panel operation-tab-panel--resumen" role="tabpanel" aria-labelledby="operation-tab-resumen" data-operation-panel="resumen">
                @include('ordenes_operacion.partials._show_resumen')
            </section>

            @if ($admiteReservas)
                <section id="operation-panel-ejecucion" class="operation-tab-panel operation-tab-panel--ejecucion" role="tabpanel" aria-labelledby="operation-tab-ejecucion" data-operation-panel="ejecucion">
                    @include('ordenes_operacion.partials._show_ejecucion')
                </section>
            @endif

            @if ($orden->cotizacionCliente || $admiteReservas)
                <section id="operation-panel-materiales" class="operation-tab-panel operation-tab-panel--materiales" role="tabpanel" aria-labelledby="operation-tab-materiales" data-operation-panel="materiales">
                    @include('ordenes_operacion.partials._show_materiales')
                </section>
            @endif

            @if ($admiteReservas)
                <section id="operation-panel-reservas" class="operation-tab-panel operation-tab-panel--reservas" role="tabpanel" aria-labelledby="operation-tab-reservas" data-operation-panel="reservas">
                    @include('ordenes_operacion.partials._show_reservas')
                </section>
            @endif

            <section id="operation-panel-herramientas" class="operation-tab-panel operation-tab-panel--herramientas" role="tabpanel" aria-labelledby="operation-tab-herramientas" data-operation-panel="herramientas">
                @include('ordenes_operacion.partials._show_herramientas')
            </section>

            <section id="operation-panel-abastecimiento" class="operation-tab-panel operation-tab-panel--abastecimiento" role="tabpanel" aria-labelledby="operation-tab-abastecimiento" data-operation-panel="abastecimiento">
                @include('ordenes_operacion.partials._show_abastecimiento')
            </section>
        </div>

        @if ($puedeAnularOrden && ! $orden->estaCerrada() && ! $orden->estaAnulada())
            <div
                class="modal-backdrop"
                data-order-cancel-modal
                @if (! $errors->has('motivo_anulacion')) hidden @endif
            >
                <section
                    class="confirmation-modal order-cancel-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="order-cancel-title"
                    tabindex="-1"
                >
                    <span class="confirmation-modal__icon confirmation-modal__icon--danger">
                        <x-ui.icon name="warning" :size="25" />
                    </span>

                    <div class="confirmation-modal__content">
                        <h2 id="order-cancel-title">
                            ¿Anular orden de operación?
                        </h2>
                        <p>
                            La orden dejará de estar disponible para nuevas
                            requerimientos de compra y salidas.
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('ordenes-operacion.anular', $orden->id) }}"
                        data-loading-form
                        class="order-cancel-form"
                    >
                        @csrf
                        @method('PATCH')

                        <label class="form-field">
                            <span>
                                Motivo de anulación
                                <span class="required-mark">*</span>
                            </span>
                            <textarea
                                name="motivo_anulacion"
                                rows="4"
                                maxlength="500"
                                required
                                placeholder="Explica el motivo"
                            >{{ old('motivo_anulacion') }}</textarea>
                            @error('motivo_anulacion')
                                <small class="field-error">{{ $message }}</small>
                            @enderror
                        </label>

                        <div class="confirmation-modal__actions">
                            <button
                                type="button"
                                class="button button--ghost"
                                data-close-order-cancel
                            >
                                Mantener orden
                            </button>

                            <button
                                type="submit"
                                class="button button--danger"
                                data-submit-button
                                data-loading-text="Anulando orden..."
                            >
                                <span data-submit-icon>
                                    <x-ui.icon name="error" :size="17" />
                                </span>
                                <span
                                    class="button-spinner"
                                    data-submit-spinner
                                    hidden
                                ></span>
                                <span data-submit-label>Anular orden</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/operation-detail-tabs.js') }}" defer></script>
<script src="{{ asset('js/operation-cancel-modal.js') }}" defer></script>
@endpush
