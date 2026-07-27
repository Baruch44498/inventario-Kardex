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

        $cliente = $nota->ordenOperacion?->cliente?->razon_social
            ?? 'Sin cliente asociado';

        $vehiculo = $nota->ordenOperacion?->vehiculo?->placa
            ?? $nota->ordenOperacion?->vehiculo?->codigo_interno
            ?? 'Sin vehículo';
    @endphp

    <a href="{{ route('notas-salida.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a notas de salida
    </a>

    <section class="module-header">
        <div>
            <p class="eyebrow">Despacho confirmado</p>
            <h1>{{ $nota->codigo }}</h1>
            <p>
                Salida asociada a la orden
                <strong>{{ $nota->ordenOperacion?->codigo_orden ?? '—' }}</strong>.
            </p>
        </div>

        <div class="module-header__actions">
            <span class="badge badge--{{ $estadoClase }}">
                {{ $nota->estado }}
            </span>

            @if ($nota->estaConfirmada())
                <button
                    type="button"
                    class="button button--danger"
                    data-open-output-cancel
                >
                    <x-ui.icon name="error" :size="17" />
                    Anular nota
                </button>
            @endif
        </div>
    </section>

    @if ($nota->estaAnulada())
        <div class="notice notice--danger notice--block">
            <x-ui.icon name="error" :size="18" />
            <div>
                <strong>Nota anulada</strong>
                <span>
                    {{ $nota->motivo_anulacion }}
                    @if ($nota->anulado_en)
                        · {{ $nota->anulado_en->format('d/m/Y H:i') }}
                    @endif
                    @if ($nota->anulador)
                        · {{ $nota->anulador->username }}
                    @endif
                </span>
            </div>
        </div>
    @endif

    <section class="entry-show-grid output-show-grid">
        <article class="panel entry-document-card">
            <div class="panel-heading">
                <p class="eyebrow">Documento</p>
                <h2>Información de la salida</h2>
            </div>

            <dl class="detail-list detail-list--two-columns">
                <div>
                    <dt>Fecha de salida</dt>
                    <dd>{{ $nota->fecha_salida?->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>
                        <span class="badge badge--{{ $estadoClase }}">
                            {{ $nota->estado }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt>Orden de operación</dt>
                    <dd>{{ $nota->ordenOperacion?->codigo_orden ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Tipo de orden</dt>
                    <dd>{{ $nota->ordenOperacion?->tipoOrden?->codigo ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Cliente</dt>
                    <dd>{{ $cliente }}</dd>
                </div>
                <div>
                    <dt>Vehículo</dt>
                    <dd>{{ $vehiculo }}</dd>
                </div>
                <div>
                    <dt>Entregado a</dt>
                    <dd>{{ $nota->entregado_a ?: 'No registrado' }}</dd>
                </div>
                <div>
                    <dt>Registrado por</dt>
                    <dd>{{ $nota->registrador?->username ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Confirmado</dt>
                    <dd>{{ $nota->confirmado_en?->format('d/m/Y H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel entry-total-card output-total-card">
            <span class="entry-total-card__icon output-total-card__icon">
                <x-ui.icon name="exit" :size="28" />
            </span>
            <span>Productos entregados</span>
            <strong>{{ $nota->detalles->count() }}</strong>
            <small>
                Cantidad total:
                <x-ui.quantity :value="$nota->detalles->sum('cantidad')" />
            </small>

            <div class="entry-total-card__amount">
                <span>Valor entregado</span>
                <strong>
                    S/ {{ number_format((float) $nota->detalles->sum('subtotal'), 2, '.', ',') }}
                </strong>
            </div>
        </article>
    </section>

    @if ($nota->observacion)
        <div class="notice notice--info notice--block">
            <x-ui.icon name="info" :size="18" />
            <span>{{ $nota->observacion }}</span>
        </div>
    @endif

    <section class="panel">
        <div class="panel-heading panel-heading--split">
            <div>
                <p class="eyebrow">Detalle</p>
                <h2>Productos entregados</h2>
            </div>

            <div class="panel-heading__actions">
                <a
                    href="{{ route('inventario.index') }}"
                    class="button button--ghost button--small"
                >
                    <x-ui.icon name="inventory" :size="16" />
                    Ver inventario
                </a>
                <a
                    href="{{ route('movimientos.index', ['q' => $nota->id]) }}"
                    class="button button--ghost button--small"
                >
                    <x-ui.icon name="movements" :size="16" />
                    Ver movimientos
                </a>
            </div>
        </div>

        <div class="table-wrap table-wrap--wide table-wrap--responsive">
            <table class="data-table output-detail-table">
                <thead>
                    <tr>
                        <th class="table-sticky--start">Producto</th>
                        <th>Repisa</th>
                        <th>Cantidad</th>
                        <th>Costo promedio</th>
                        <th>Subtotal</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($nota->detalles as $detalle)
                        <tr>
                            <td class="table-sticky--start">
                                <a
                                    href="{{ route('productos.show', $detalle->producto_id) }}"
                                    class="table-primary-link"
                                >
                                    {{ $detalle->producto?->codigo }}
                                </a>
                                <span>{{ $detalle->producto?->descripcion }}</span>
                            </td>
                            <td>
                                <span class="location-chip">
                                    <x-ui.icon name="shelf" :size="14" />
                                    {{ $detalle->repisa?->codigo }}
                                </span>
                            </td>
                            <td>
                                <x-ui.quantity :value="$detalle->cantidad" />
                                {{ $detalle->producto?->unidadMedida?->codigo }}
                            </td>
                            <td>
                                S/ {{ number_format((float) $detalle->costo_unitario_promedio, 4, '.', ',') }}
                            </td>
                            <td>
                                <strong>
                                    S/ {{ number_format((float) $detalle->subtotal, 2, '.', ',') }}
                                </strong>
                            </td>
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
