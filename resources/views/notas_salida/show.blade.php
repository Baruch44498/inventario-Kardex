@extends('layouts.app')

@section('title', 'Nota de salida ' . $nota->codigo)
@section('page-kicker', 'Notas de salida')
@section('page-title', $nota->codigo)

@section('content')
    @php
        $estadoClase = match ($nota->estado) {
            'CONFIRMADA' => 'success',
            'ANULADA' => 'danger',
            default => 'warning',
        };

        $origen = match ($nota->motivo_salida) {
            'ORDEN_OPERACION' => $nota->ordenOperacion?->codigo_orden ?? 'Orden no disponible',
            'PROFORMA' => $nota->proforma?->codigo ?? 'Proforma no disponible',
            'USO_INTERNO' => 'Uso interno',
            default => 'Salida sin documento origen',
        };

        $productosDistintos = $nota->detalles->pluck('producto_id')->filter()->unique()->count();
        $unidadesDetalle = $nota->detalles
            ->map(fn ($detalle) => $detalle->producto?->unidadMedida?->codigo)
            ->filter()
            ->unique()
            ->values();
        $puedeTotalizarCantidad = $unidadesDetalle->count() === 1;
        $unidadResumen = $unidadesDetalle->first();
    @endphp

    <div class="document-flow-page document-flow-page--completed">
    <a href="{{ route('notas-salida.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a notas de salida
    </a>

    <section class="module-header module-header--compact entry-show-header">
        <div>
            <p class="eyebrow">{{ $nota->motivoVisible() }}</p>
            <h1>{{ $nota->codigo }}</h1>
            <p>Salida física vinculada a <strong>{{ $origen }}</strong>.</p>
        </div>

        <div class="module-header__actions">
            <span class="badge badge--{{ $estadoClase }} badge--large">{{ $nota->estado }}</span>
            @if ($nota->estaConfirmada())
                <button type="button" class="button button--danger" data-open-output-cancel>
                    <x-ui.icon name="error" :size="17" /> Anular nota
                </button>
            @endif
        </div>
    </section>

    <x-ui.workflow-stepper
        :steps="$pasosRegistro"
        :current="5"
        :interactive="false"
        label="Registro de la Nota de Salida completado"
    />

    @if ($nota->estaAnulada())
        <div class="notice notice--danger notice--block">
            <x-ui.icon name="error" :size="18" />
            <div>
                <strong>Nota anulada</strong>
                <span>
                    {{ $nota->motivo_anulacion }}
                    @if ($nota->anulado_en) · {{ $nota->anulado_en->format('d/m/Y H:i') }} @endif
                    @if ($nota->anulador) · {{ $nota->anulador->username }} @endif
                </span>
            </div>
        </div>
    @endif

    <section class="entry-document-grid output-show-grid">
        <article class="panel entry-document-card">
            <div class="panel-heading">
                <p class="eyebrow">Documento</p>
                <h2>Información de la salida</h2>
            </div>

            <dl class="detail-list detail-list--entry">
                <div><dt>Fecha de salida</dt><dd>{{ $nota->fecha_salida?->format('d/m/Y') }}</dd></div>
                <div><dt>Motivo</dt><dd>{{ $nota->motivoVisible() }}</dd></div>
                <div><dt>Documento origen</dt><dd>{{ $origen }}</dd></div>
                @if ($nota->ordenOperacion)
                    <div><dt>Tipo de orden</dt><dd>{{ $nota->ordenOperacion?->tipoOrden?->codigo ?? '—' }}</dd></div>
                    <div><dt>Área del trabajo</dt><dd>{{ $nota->area_trabajo ?: 'GENERAL / histórica' }}</dd></div>
                    <div><dt>Cliente</dt><dd>{{ $nota->ordenOperacion?->cliente?->razon_social ?? '—' }}</dd></div>
                    @if ($nota->ordenOperacion?->vehiculo)
                        <div>
                            <dt>Vehículo</dt>
                            <dd>{{ $nota->ordenOperacion->vehiculo->placa ?: ($nota->ordenOperacion->vehiculo->codigo_interno ?: '—') }}</dd>
                        </div>
                    @endif
                @elseif ($nota->proforma)
                    <div><dt>Cliente</dt><dd>{{ $nota->proforma?->cliente?->razon_social ?? '—' }}</dd></div>
                @endif
                <div>
                    <dt>Recibido por</dt>
                    <dd>
                        {{ $nota->recibido_por_nombre ?: ($nota->entregado_a ?: 'No registrado') }}
                        @if ($nota->recibido_por_dni) · DNI {{ $nota->recibido_por_dni }} @endif
                    </dd>
                </div>
                <div>
                    <dt>Entregado por</dt>
                    <dd>
                        {{ $nota->entregado_por_nombre ?: ($nota->confirmador?->empleado?->nombre_completo ?? ($nota->confirmador?->username ?? '—')) }}
                        @if ($nota->entregado_por_dni) · DNI {{ $nota->entregado_por_dni }} @endif
                    </dd>
                </div>
                <div><dt>Confirmado</dt><dd>{{ $nota->confirmado_en?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>
        </article>

        <article class="panel entry-total-card output-total-card">
            <span class="entry-total-card__icon output-total-card__icon"><x-ui.icon name="exit" :size="28" /></span>
            <span>Productos distintos</span>
            <strong>{{ $productosDistintos }}</strong>
            @if ($puedeTotalizarCantidad)
                <small>Cantidad entregada: <x-ui.quantity :value="$nota->detalles->sum('cantidad')" /> {{ $unidadResumen }}</small>
            @else
                <small>Ver cantidades en el detalle</small>
            @endif
            <div class="entry-total-card__amount">
                <span>Valor de salida</span>
                <strong>S/ {{ number_format((float) $nota->detalles->sum('subtotal'), 2, '.', ',') }}</strong>
            </div>
        </article>
    </section>

    @if ($nota->observacion)
        <div class="notice notice--info notice--block">
            <x-ui.icon name="info" :size="18" /><span>{{ $nota->observacion }}</span>
        </div>
    @endif

    <section class="panel entry-detail-card">
        <div class="panel-heading panel-heading--split">
            <div><p class="eyebrow">Detalle</p><h2>Productos entregados</h2></div>
            <div class="panel-heading__actions">
                <a href="{{ route('inventario.index') }}" class="button button--ghost button--small"><x-ui.icon name="inventory" :size="16" /> Ver inventario</a>
                <a href="{{ route('movimientos.index', ['q' => $nota->id]) }}" class="button button--ghost button--small"><x-ui.icon name="movements" :size="16" /> Ver movimientos</a>
            </div>
        </div>

        <div class="table-wrap table-wrap--wide table-wrap--responsive">
            <table class="data-table output-detail-table">
                <thead>
                    <tr><th>Producto</th><th>Repisa</th><th>Tratamiento</th><th>Cantidad</th><th>Costo promedio</th><th>Subtotal</th><th>Observación</th></tr>
                </thead>
                <tbody>
                    @foreach ($nota->detalles as $detalle)
                        <tr>
                            <td><a href="{{ route('productos.show', $detalle->producto_id) }}" class="table-primary-link">{{ $detalle->producto?->codigo }}</a><span>{{ $detalle->producto?->descripcion }}</span></td>
                            <td><span class="location-chip"><x-ui.icon name="shelf" :size="14" /> {{ $detalle->repisa?->codigo }}</span></td>
                            <td><strong>{{ $detalle->tratamientoVisible() }}</strong></td>
                            <td><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->codigo }}</td>
                            <td>S/ {{ number_format((float) $detalle->costo_unitario_promedio, 2, '.', ',') }}</td>
                            <td><strong>S/ {{ number_format((float) $detalle->subtotal, 2, '.', ',') }}</strong></td>
                            <td>{{ $detalle->observacion ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($nota->estaConfirmada())
        <div
            class="modal-backdrop"
            data-output-cancel-modal
            @if (! $errors->has('motivo_anulacion')) hidden @endif
        >
            <section
                class="confirmation-modal output-cancel-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="output-cancel-title"
                aria-describedby="output-cancel-description"
                tabindex="-1"
            >
                <span class="confirmation-modal__icon confirmation-modal__icon--danger">
                    <x-ui.icon name="warning" :size="25" />
                </span>

                <div class="confirmation-modal__content">
                    <h2 id="output-cancel-title">¿Anular nota de salida?</h2>
                    <p id="output-cancel-description">
                        El sistema devolverá las cantidades al inventario y
                        registrará movimientos de reversa.
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('notas-salida.anular', $nota->id) }}"
                    data-loading-form
                    class="output-cancel-form"
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
                            placeholder="Explica por qué se anula esta salida"
                            @class(['is-invalid' => $errors->has('motivo_anulacion')])
                        >{{ old('motivo_anulacion') }}</textarea>
                        @error('motivo_anulacion')
                            <small class="field-error" role="alert">{{ $message }}</small>
                        @enderror
                    </label>

                    <div class="confirmation-modal__actions">
                        <button
                            type="button"
                            class="button button--ghost"
                            data-close-output-cancel
                        >
                            Mantener nota
                        </button>

                        <button
                            type="submit"
                            class="button button--danger"
                            data-submit-button
                            data-loading-text="Anulando salida..."
                        >
                            <span data-submit-icon>
                                <x-ui.icon name="error" :size="17" />
                            </span>
                            <span
                                class="button-spinner"
                                data-submit-spinner
                                hidden
                            ></span>
                            <span data-submit-label>Anular y restituir stock</span>
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
    const outputCancelModal = document.querySelector(
        '[data-output-cancel-modal]'
    );
    const outputCancelDialog = outputCancelModal?.querySelector(
        '.output-cancel-modal'
    );
    const openOutputCancel = document.querySelector(
        '[data-open-output-cancel]'
    );
    const closeOutputCancel = outputCancelModal?.querySelector(
        '[data-close-output-cancel]'
    );

    const openOutputCancelModal = () => {
        if (!outputCancelModal) return;

        outputCancelModal.hidden = false;
        document.body.classList.add('modal-open');

        requestAnimationFrame(() => {
            outputCancelModal
                .querySelector('textarea')
                ?.focus();
        });
    };

    const closeOutputCancelModal = () => {
        if (!outputCancelModal) return;

        outputCancelModal.hidden = true;
        document.body.classList.remove('modal-open');
        openOutputCancel?.focus();
    };

    openOutputCancel?.addEventListener(
        'click',
        openOutputCancelModal
    );

    closeOutputCancel?.addEventListener(
        'click',
        closeOutputCancelModal
    );

    outputCancelModal?.addEventListener('click', (event) => {
        if (event.target === outputCancelModal) {
            closeOutputCancelModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape'
            && outputCancelModal
            && !outputCancelModal.hidden) {
            closeOutputCancelModal();
        }
    });

    outputCancelDialog?.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;

        const focusable = Array.from(
            outputCancelDialog.querySelectorAll(
                'button:not([disabled]), textarea, input, select, [href]'
            )
        );

        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey
            && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey
            && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

   if (outputCancelModal && !outputCancelModal.hidden) {
    document.body.classList.add('modal-open');

    requestAnimationFrame(() => {
        outputCancelModal
            .querySelector('textarea')
            ?.focus();
    });
}
</script>
@endpush
