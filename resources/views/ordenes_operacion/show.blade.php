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
    @endphp

    <div class="operation-page operation-page--show">
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
                    @if ($orden->puedeEditar() && $puedeEditarOrden)
                        <a
                            href="{{ route('ordenes-operacion.edit', $orden->id) }}"
                            class="button button--ghost"
                        >
                            <x-ui.icon name="edit" :size="17" />
                            Editar
                        </a>
                    @endif

                    @if ($puedeRegistrarSalida && ! $orden->estaCerrada() && ! $orden->estaAnulada())
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

        <section class="operation-show-grid">
            <article class="panel operation-context-card">
                <div class="panel-heading operation-card-heading">
                    <p class="eyebrow">Datos principales</p>
                    <h2>Contexto de la orden</h2>
                </div>

                <div class="operation-context-description">
                    <span class="operation-context-description__icon">
                        <x-ui.icon name="clipboard" :size="20" />
                    </span>
                    <div>
                        <span>Descripción del trabajo</span>
                        <p>
                            {{ $tieneDescripcion
                                ? $descripcion
                                : 'Sin descripción registrada.' }}
                        </p>
                    </div>
                </div>

                <dl class="operation-info-grid">
                    <div class="operation-info-item">
                        <dt>Tipo</dt>
                        <dd>
                            {{ $orden->tipoOrden?->codigo }}
                            · {{ $orden->tipoOrden?->nombre }}
                        </dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Fecha de apertura</dt>
                        <dd>{{ $orden->fecha_apertura?->format('d/m/Y') }}</dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Cliente</dt>
                        <dd>
                            {{ $orden->cliente?->razon_social ?? 'Sin cliente asociado' }}
                        </dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Vehículo</dt>
                        <dd>{{ $vehiculo }}</dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Ubicación de referencia</dt>
                        <dd>
                            @if ($orden->clienteDireccion)
                                {{ $orden->clienteDireccion->destino
                                    ?: $orden->clienteDireccion->direccion }}

                                @if ($orden->clienteDireccion->ciudad)
                                    <small>
                                        · {{ $orden->clienteDireccion->ciudad }}
                                    </small>
                                @endif
                            @else
                                Sin ubicación asociada
                            @endif

                            <small>· Atención y recojo en HIDROIL</small>
                        </dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Creado por</dt>
                        <dd>{{ $orden->creador?->username ?? '—' }}</dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Creación</dt>
                        <dd>{{ $orden->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Cierre</dt>
                        <dd>
                            {{ $orden->cerrado_en?->format('d/m/Y H:i') ?? 'Pendiente' }}
                        </dd>
                    </div>
                </dl>
            </article>

            <aside class="panel operation-lifecycle-card">
                <div class="panel-heading operation-card-heading">
                    <p class="eyebrow">Ciclo de vida</p>
                    <h2>Acciones de estado</h2>
                </div>

                <div class="operation-lifecycle-status">
                    <span class="operation-lifecycle-status__icon">
                        <x-ui.icon name="activity" :size="25" />
                    </span>
                    <div>
                        <span>Estado actual</span>
                        <strong>{{ str_replace('_', ' ', $orden->estado) }}</strong>
                    </div>
                </div>

                <div class="operation-lifecycle-actions">
                    @if ($puedeGestionarEstado && $orden->estaAbierta())
                        <form
                            method="POST"
                            action="{{ route('ordenes-operacion.iniciar', $orden->id) }}"
                            data-loading-form
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                class="button button--primary button--block"
                                data-submit-button
                                data-loading-text="Iniciando orden..."
                                data-confirm="¿Marcar esta orden como EN PROCESO?"
                            >
                                <span data-submit-icon>
                                    <x-ui.icon name="activity" :size="17" />
                                </span>
                                <span
                                    class="button-spinner"
                                    data-submit-spinner
                                    hidden
                                ></span>
                                <span data-submit-label>Iniciar operación</span>
                            </button>
                        </form>
                    @endif

                    @if ($puedeGestionarEstado && ! $orden->estaCerrada() && ! $orden->estaAnulada())
                        <form
                            method="POST"
                            action="{{ route('ordenes-operacion.cerrar', $orden->id) }}"
                            data-loading-form
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                class="button button--ghost button--block"
                                data-submit-button
                                data-loading-text="Cerrando orden..."
                                data-confirm="¿Cerrar esta orden? Luego será de solo lectura."
                            >
                                <span data-submit-icon>
                                    <x-ui.icon name="check-circle" :size="17" />
                                </span>
                                <span
                                    class="button-spinner"
                                    data-submit-spinner
                                    hidden
                                ></span>
                                <span data-submit-label>Cerrar orden</span>
                            </button>
                        </form>

                        @if ($puedeAnularOrden)
                            <button
                                type="button"
                                class="button button--danger button--block"
                                data-open-order-cancel
                            >
                                <x-ui.icon name="error" :size="17" />
                                Anular orden
                            </button>
                        @endif
                    @endif
                </div>

                @if ($orden->estaCerrada())
                    <div class="notice notice--success notice--block">
                        <x-ui.icon name="check-circle" :size="18" />
                        <span>
                            La orden está cerrada y conserva su historial como solo lectura.
                        </span>
                    </div>
                @endif
            </aside>
        </section>

        <section class="operation-related-grid">
            <article class="panel operation-related-card">
                <div class="panel-heading panel-heading--split operation-card-heading">
                    <div>
                        <p class="eyebrow">Abastecimiento</p>
                        <h2>Requisiciones</h2>
                    </div>
                    <span class="count-chip">{{ $orden->requisiciones_count }}</span>
                </div>

                @forelse ($orden->requisiciones as $requisicion)
                    <a
                        href="{{ route('modulos.show', 'requisiciones') }}"
                        class="related-record"
                    >
                        <div>
                            <strong>{{ $requisicion->codigo }}</strong>
                            <span>
                                {{ $requisicion->fecha_solicitud?->format('d/m/Y') }}
                                · {{ $requisicion->descripcion }}
                            </span>
                        </div>
                        <span class="badge badge--info">
                            {{ $requisicion->estado }}
                        </span>
                    </a>
                @empty
                    <div class="operation-embedded-empty">
                        <span class="operation-embedded-empty__icon">
                            <x-ui.icon name="requisitions" :size="25" />
                        </span>
                        <strong>Sin requisiciones</strong>
                        <span>
                            Aún no se han solicitado materiales para esta orden.
                        </span>
                    </div>
                @endforelse
            </article>

            <article class="panel operation-related-card">
                <div class="panel-heading panel-heading--split operation-card-heading">
                    <div>
                        <p class="eyebrow">Almacén</p>
                        <h2>Notas de salida</h2>
                    </div>
                    <span class="count-chip">{{ $orden->notas_salida_count }}</span>
                </div>

                @forelse ($orden->notasSalida as $nota)
                    <a
                        href="{{ route('notas-salida.show', $nota->id) }}"
                        class="related-record"
                    >
                        <div>
                            <strong>{{ $nota->codigo }}</strong>
                            <span>
                                {{ $nota->fecha_salida?->format('d/m/Y') }}
                                · {{ $nota->entregado_a }}
                            </span>
                        </div>
                        <span
                            class="badge badge--{{ $nota->estado === 'CONFIRMADA'
                                ? 'success'
                                : ($nota->estado === 'ANULADA' ? 'danger' : 'warning') }}"
                        >
                            {{ $nota->estado }}
                        </span>
                    </a>
                @empty
                    <div class="operation-embedded-empty">
                        <span class="operation-embedded-empty__icon">
                            <x-ui.icon name="exit" :size="25" />
                        </span>
                        <strong>Sin salidas</strong>
                        <span>
                            No se han entregado productos para esta orden.
                        </span>
                    </div>
                @endforelse
            </article>
        </section>

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
                            requisiciones y salidas.
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
<script>
    const orderCancelModal = document.querySelector(
        '[data-order-cancel-modal]'
    );
    const openOrderCancel = document.querySelector(
        '[data-open-order-cancel]'
    );
    const closeOrderCancel = document.querySelector(
        '[data-close-order-cancel]'
    );

    const openOrderCancelModal = () => {
        if (!orderCancelModal) return;

        orderCancelModal.hidden = false;
        document.body.classList.add('modal-open');

        requestAnimationFrame(() => {
            orderCancelModal
                .querySelector('textarea')
                ?.focus();
        });
    };

    const closeOrderCancelModal = () => {
        if (!orderCancelModal) return;

        orderCancelModal.hidden = true;
        document.body.classList.remove('modal-open');
        openOrderCancel?.focus();
    };

    openOrderCancel?.addEventListener(
        'click',
        openOrderCancelModal
    );

    closeOrderCancel?.addEventListener(
        'click',
        closeOrderCancelModal
    );

    orderCancelModal?.addEventListener('click', (event) => {
        if (event.target === orderCancelModal) {
            closeOrderCancelModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape'
            && orderCancelModal
            && ! orderCancelModal.hidden
        ) {
            closeOrderCancelModal();
        }
    });

    if (orderCancelModal && ! orderCancelModal.hidden) {
        document.body.classList.add('modal-open');
    }
</script>
@endpush
