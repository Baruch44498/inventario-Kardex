@extends('layouts.app')

@section('title', 'Nueva nota de salida')
@section('page-kicker', 'Notas de salida')
@section('page-title', 'Nueva nota de salida')

@section('content')
    <div class="document-flow-page">
    <a href="{{ route('notas-salida.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a notas de salida
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Despacho de materiales</p>
            <h1>Registrar nota de salida</h1>
            <p>
                Selecciona la orden de operación, identifica al receptor y
                registra únicamente las cantidades que salen físicamente del almacén.
            </p>
        </div>
    </section>

    <x-ui.workflow-stepper :steps="$pasosRegistro" :current="$pasoActual" />

    @if ($errors->any())
        <div class="notice notice--danger notice--block" role="alert">
            <x-ui.icon name="error" :size="18" />
            <div>
                <strong>Revisa la información del formulario.</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        </div>
    @endif

    <section id="paso-orden" class="panel order-selector-panel" data-flow-step-section="1">
        <div class="panel-heading panel-heading--split">
            <div>
                <p class="eyebrow">Selección de orden</p>
                <h2>Orden de operación</h2>
                <p>
                    Solo aparecen órdenes abiertas o en proceso.
                </p>
            </div>
        </div>

        <form method="GET" action="{{ route('notas-salida.create') }}" class="order-selector-form">
            <label class="form-field">
                <span>Orden de operación</span>
                <div class="input-with-icon input-with-icon--select">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="orders" :size="18" />
                    </span>
                    <select name="orden_operacion_id" required>
                        <option value="">Selecciona una orden</option>
                        @foreach ($ordenes as $ordenDisponible)
                            <option
                                value="{{ $ordenDisponible->id }}"
                                @selected(
                                    (int) request('orden_operacion_id') === $ordenDisponible->id
                                    || (int) old('orden_operacion_id') === $ordenDisponible->id
                                )
                            >
                                {{ $ordenDisponible->codigo_orden }} ·
                                {{ $ordenDisponible->tipoOrden?->codigo ?? 'Sin tipo' }} ·
                                {{ $ordenDisponible->cliente?->razon_social ?? 'Sin cliente' }} ·
                                {{ $ordenDisponible->estado }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </label>

            <button type="submit" class="button button--primary">
                <x-ui.icon name="refresh" :size="17" />
                Cargar orden
            </button>
        </form>

        @if ($ordenes->isEmpty())
            <div class="inline-empty-state">
                <span class="empty-state__icon empty-state__icon--warning">
                    <x-ui.icon name="warning" :size="25" />
                </span>
                <div>
                    <strong>No hay órdenes disponibles</strong>
                    <span>
                        Debe existir una orden de operación abierta o en proceso.
                    </span>
                </div>
                <a
                    href="{{ route('ordenes-operacion.index') }}"
                    class="button button--ghost button--small"
                >
                    Ver órdenes de operación
                </a>
            </div>
        @elseif ($ordenNoDisponible)
            <div class="notice notice--warning notice--block">
                <x-ui.icon name="warning" :size="18" />
                <span>
                    La orden seleccionada ya no está disponible para registrar salidas.
                </span>
            </div>
        @endif
    </section>

    @if ($orden)
        @php
            $identificadorVehiculo = $orden->vehiculo?->placa
                ?? $orden->vehiculo?->codigo_interno
                ?? 'Sin vehículo';
        @endphp

        <section class="order-context-card output-order-context">
            <div class="order-context-card__main">
                <span class="order-context-card__icon output-order-context__icon">
                    <x-ui.icon name="orders" :size="25" />
                </span>
                <div>
                    <span>Orden seleccionada</span>
                    <strong>{{ $orden->codigo_orden }}</strong>
                    <small>
                        {{ $orden->cliente?->razon_social ?? 'Sin cliente asociado' }}
                    </small>
                </div>
            </div>

            <dl class="order-context-card__facts">
                <div>
                    <dt>Tipo</dt>
                    <dd>{{ $orden->tipoOrden?->codigo ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Apertura</dt>
                    <dd>{{ $orden->fecha_apertura?->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt>Vehículo</dt>
                    <dd>{{ $identificadorVehiculo }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>
                        <span class="badge badge--info">{{ $orden->estado }}</span>
                    </dd>
                </div>
            </dl>
        </section>

        <form
            method="POST"
            action="{{ route('notas-salida.store') }}"
            class="entry-form output-form"
            data-dirty-form
            data-loading-form
            data-output-form
        >
            @csrf
            <input
                type="hidden"
                name="orden_operacion_id"
                value="{{ $orden->id }}"
            >

            <section id="paso-datos" class="panel form-panel" data-flow-step-section="2">
                <div class="panel-heading">
                    <p class="eyebrow">Datos de entrega</p>
                    <h2>Documento y responsable</h2>
                </div>

                <div class="form-grid form-grid--entry-header">

<div class="form-field">
    <span>Código de nota</span>
    <div class="generated-code-field">
        <span class="generated-code-field__icon">
            <x-ui.icon name="hash" :size="18" />
        </span>
        <div class="generated-code-field__content">
            <strong>NS-###-{{ now()->format('y') }}</strong>
            <span>Se asignará automáticamente al confirmar la salida.</span>
        </div>
        <span class="badge badge--info">Automático</span>
    </div>
    <small>
        Identifica el despacho completo; cada producto conserva su propio código.
    </small>
</div>

                    <div class="form-field">
                        <label for="fecha_salida">
                            Fecha de salida <span class="required-mark">*</span>
                        </label>
                        <div class="input-with-icon">
                            <span class="input-with-icon__symbol">
                                <x-ui.icon name="calendar" :size="18" />
                            </span>
                            <input
                                id="fecha_salida"
                                name="fecha_salida"
                                type="date"
                                value="{{ old('fecha_salida', now()->toDateString()) }}"
                                max="{{ now()->toDateString() }}"
                                required
                                @class(['is-invalid' => $errors->has('fecha_salida')])
                            >
                        </div>
                        @error('fecha_salida')
                            <small class="field-error" role="alert">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-field form-field--span-2">
                        <label for="entregado_a">
                            Entregado a <span class="required-mark">*</span>
                        </label>
                        <div class="input-with-icon">
                            <span class="input-with-icon__symbol">
                                <x-ui.icon name="user" :size="18" />
                            </span>
                            <input
                                id="entregado_a"
                                name="entregado_a"
                                type="text"
                                value="{{ old('entregado_a') }}"
                                maxlength="150"
                                placeholder="Nombre de la persona que recibe"
                                required
                                @class(['is-invalid' => $errors->has('entregado_a')])
                            >
                        </div>
                        <small>
                            Este dato quedará registrado en la trazabilidad de la entrega.
                        </small>
                        @error('entregado_a')
                            <small class="field-error" role="alert">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-field form-field--span-2">
                        <label for="observacion">Observación general</label>
                        <textarea
                            id="observacion"
                            name="observacion"
                            rows="3"
                            maxlength="500"
                            placeholder="Indicaciones, destino o referencia de la entrega"
                            @class(['is-invalid' => $errors->has('observacion')])
                        >{{ old('observacion') }}</textarea>
                        <div class="field-meta">
                            <small>Opcional.</small>
                            <small data-character-count="observacion">0 / 500</small>
                        </div>
                        @error('observacion')
                            <small class="field-error" role="alert">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
            </section>

            <section id="paso-productos" class="panel" data-flow-step-section="3">
                <div class="panel-heading panel-heading--split">
                    <div>
                        <p class="eyebrow">Existencias disponibles</p>
                        <h2>Productos y cantidades</h2>
                        <p>
                            Ingresa cantidad solo en las filas que serán entregadas.
                        </p>
                    </div>

                    <div class="entry-live-total output-live-total">
                        <span>Valor de salida</span>
                        <strong data-output-total>S/ 0.00</strong>
                        <small>
                            <span data-output-lines>0</span> filas seleccionadas
                        </small>
                    </div>
                </div>

                @error('detalles')
                    <div class="notice notice--danger notice--block">
                        <x-ui.icon name="error" :size="18" />
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                @if ($inventarios->isEmpty())
                    <div class="empty-table-state">
                        <span class="empty-state__icon empty-state__icon--warning">
                            <x-ui.icon name="warning" :size="30" />
                        </span>
                        <strong>No existen productos con stock disponible</strong>
                        <span>
                            Registra una nota de ingreso antes de intentar una salida.
                        </span>
                        <div class="empty-table-state__actions">
                            <a
                                href="{{ route('notas-ingreso.create') }}"
                                class="button button--primary button--small"
                            >
                                <x-ui.icon name="entry" :size="16" />
                                Registrar ingreso
                            </a>
                        </div>
                    </div>
                @else
                    <div class="output-line-search">
                        <label class="form-field">
                            <span>Buscar producto disponible</span>
                            <div class="input-with-icon">
                                <span class="input-with-icon__symbol">
                                    <x-ui.icon name="search" :size="17" />
                                </span>
                                <input
                                    type="search"
                                    placeholder="Código, descripción, marca o repisa"
                                    data-output-search
                                >
                            </div>
                        </label>
                    </div>

                    <div class="table-wrap table-wrap--wide table-wrap--responsive">
                        <table class="data-table entry-lines-table output-lines-table">
                            <thead>
                                <tr>
                                    <th class="table-sticky--start">Producto</th>
                                    <th>Repisa</th>
                                    <th>Unidad</th>
                                    <th>Disponible</th>
                                    <th>Costo promedio</th>
                                    <th>Cantidad a entregar</th>
                                    <th>Subtotal</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($inventarios as $indice => $inventario)
                                    @php
                                        $textoBusqueda = mb_strtolower(
                                            implode(' ', [
                                                $inventario->producto?->codigo,
                                                $inventario->producto?->descripcion,
                                                $inventario->producto?->marcaPrincipal?->nombre,
                                                $inventario->repisa?->codigo,
                                            ])
                                        );
                                    @endphp
                                    <tr
                                        data-output-row
                                        data-output-search-text="{{ $textoBusqueda }}"
                                    >
                                        <td class="table-sticky--start">
                                            <input
                                                type="hidden"
                                                name="detalles[{{ $indice }}][inventario_id]"
                                                value="{{ $inventario->id }}"
                                            >
                                            <input
                                                type="hidden"
                                                name="detalles[{{ $indice }}][producto_id]"
                                                value="{{ $inventario->producto_id }}"
                                            >
                                            <input
                                                type="hidden"
                                                name="detalles[{{ $indice }}][repisa_id]"
                                                value="{{ $inventario->repisa_id }}"
                                            >

                                            <strong>{{ $inventario->producto?->codigo }}</strong>
                                            <span>{{ $inventario->producto?->descripcion }}</span>
                                            @if ($inventario->producto?->marcaPrincipal)
                                                <small>{{ $inventario->producto->marcaPrincipal->nombre }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="location-chip">
                                                <x-ui.icon name="shelf" :size="14" />
                                                {{ $inventario->repisa?->codigo }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $inventario->producto?->unidadMedida?->codigo ?? '—' }}
                                        </td>
                                        <td>
                                            <strong class="stock-available">
                                                <x-ui.quantity :value="$inventario->stock_actual" />
                                            </strong>
                                        </td>
                                        <td>
                                            S/ {{ number_format((float) $inventario->costo_promedio_soles, 4, '.', ',') }}
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                name="detalles[{{ $indice }}][cantidad]"
                                                value="{{ old("detalles.{$indice}.cantidad", 0) }}"
                                                min="0"
                                                max="{{ $inventario->stock_actual }}"
                                                step="0.001"
                                                inputmode="decimal"
                                                class="table-input table-input--quantity"
                                                data-output-quantity
                                                data-output-stock="{{ (float) $inventario->stock_actual }}"
                                                data-output-cost="{{ (float) $inventario->costo_promedio_soles }}"
                                                aria-label="Cantidad de {{ $inventario->producto?->codigo }}"
                                            >
                                            @error("detalles.{$indice}.cantidad")
                                                <small class="field-error table-field-error">{{ $message }}</small>
                                            @enderror
                                        </td>
                                        <td>
                                            <strong data-output-subtotal>S/ 0.00</strong>
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                name="detalles[{{ $indice }}][observacion]"
                                                value="{{ old("detalles.{$indice}.observacion") }}"
                                                maxlength="300"
                                                placeholder="Opcional"
                                                class="table-input"
                                            >
                                            @error("detalles.{$indice}.observacion")
                                                <small class="field-error table-field-error">{{ $message }}</small>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section
                id="paso-confirmacion"
                class="panel entry-confirmation-panel output-confirmation-panel"
                data-flow-step-section="4"
                data-output-confirmation
                tabindex="-1"
            >
                <span class="entry-confirmation-panel__icon">
                    <x-ui.icon name="check-circle" :size="26" />
                </span>

                <div class="entry-confirmation-panel__copy">
                    <p class="eyebrow">Confirmación</p>
                    <h2>Revisa antes de descontar el inventario</h2>
                    <p>
                        Al confirmar, el sistema registrará la nota, reducirá el
                        stock de cada repisa y generará los movimientos de salida.
                    </p>
                </div>

                <div class="entry-confirmation-panel__total">
                    <span>Valor a entregar</span>
                    <strong data-output-confirmation-total>S/ 0.00</strong>
                </div>
            </section>

            <div class="form-actions form-actions--sticky">
                <button
                    type="button"
                    class="button button--ghost"
                    data-cancel-form
                    data-cancel-url="{{ route('notas-salida.index') }}"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="button button--primary"
                    data-submit-button
                    data-loading-text="Confirmando salida..."
                    @disabled($inventarios->isEmpty())
                >
                    <span data-submit-icon>
                        <x-ui.icon name="check" :size="18" />
                    </span>
                    <span
                        class="button-spinner"
                        data-submit-spinner
                        hidden
                    ></span>
                    <span data-submit-label>Confirmar nota de salida</span>
                </button>
            </div>
        </form>
    @endif
    </div>
@endsection

@push('scripts')
<script>
    const outputStepper = document.querySelector('[data-workflow-stepper]');

    const paintOutputWorkflow = (currentStep, reachableStep) => {
        if (!outputStepper) return;

        outputStepper.dataset.currentStep = String(currentStep);

        outputStepper
            .querySelectorAll('[data-workflow-step]')
            .forEach((item) => {
                const number = Number(item.dataset.workflowStep);
                const button = item.querySelector('[data-workflow-step-button]');
                const completed = number < currentStep;
                const current = number === currentStep;

                item.classList.toggle('workflow-step--completed', completed);
                item.classList.toggle('workflow-step--current', current);
                item.classList.toggle('workflow-step--pending', number > currentStep);
                button?.toggleAttribute('disabled', number > reachableStep);

                if (current) {
                    button?.setAttribute('aria-current', 'step');
                } else {
                    button?.removeAttribute('aria-current');
                }
            });
    };

    if (outputStepper) {
        let currentStep = Number(outputStepper.dataset.currentStep || 1);
        let reachableStep = currentStep;

        const setOutputStep = (step, options = {}) => {
            const next = Math.max(1, Math.min(4, Number(step)));
            reachableStep = Math.max(reachableStep, next);
            currentStep = next;
            paintOutputWorkflow(currentStep, reachableStep);

            if (options.scroll) {
                const button = outputStepper.querySelector(
                    `[data-step-number="${next}"]`
                );
                const targetId = button?.dataset.stepTarget;
                const target = targetId
                    ? document.getElementById(targetId)
                    : null;

                target?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });

                window.setTimeout(() => {
                    target?.focus?.({ preventScroll: true });
                }, 380);
            }
        };

        outputStepper
            .querySelectorAll('[data-workflow-step-button]')
            .forEach((button) => {
                button.addEventListener('click', () => {
                    if (!button.disabled) {
                        setOutputStep(
                            button.dataset.stepNumber,
                            { scroll: true }
                        );
                    }
                });
            });

        document
            .querySelectorAll('[data-output-form]')
            .forEach((form) => {
                const rows = Array.from(
                    form.querySelectorAll('[data-output-row]')
                );
                const search = form.querySelector('[data-output-search]');
                const confirmation = form.querySelector(
                    '[data-output-confirmation]'
                );
                const totals = Array.from(
                    form.querySelectorAll(
                        '[data-output-total], [data-output-confirmation-total]'
                    )
                );
                const linesOutput = form.querySelector('[data-output-lines]');

                const formatMoney = (value) => new Intl.NumberFormat(
                    'es-PE',
                    {
                        style: 'currency',
                        currency: 'PEN',
                        minimumFractionDigits: 2,
                    }
                ).format(value);

                const selectedRows = () => rows.filter((row) => {
                    const quantity = Number(
                        row.querySelector('[data-output-quantity]')?.value || 0
                    );

                    return quantity > 0;
                });

                const refresh = () => {
                    let total = 0;
                    let selected = 0;

                    rows.forEach((row) => {
                        const input = row.querySelector(
                            '[data-output-quantity]'
                        );
                        const subtotalOutput = row.querySelector(
                            '[data-output-subtotal]'
                        );
                        const stock = Number(
                            input?.dataset.outputStock || 0
                        );
                        const cost = Number(
                            input?.dataset.outputCost || 0
                        );
                        const quantity = Math.max(
                            0,
                            Number(input?.value || 0)
                        );
                        const subtotal = quantity * cost;

                        if (input && quantity > stock) {
                            input.setCustomValidity(
                                `La cantidad no puede superar el stock disponible de ${stock}.`
                            );
                        } else {
                            input?.setCustomValidity('');
                        }

                        total += subtotal;

                        if (quantity > 0) {
                            selected++;
                        }

                        subtotalOutput.textContent = formatMoney(subtotal);
                        row.classList.toggle(
                            'output-row--selected',
                            quantity > 0
                        );
                    });

                    totals.forEach((output) => {
                        output.textContent = formatMoney(total);
                    });

                    if (linesOutput) {
                        linesOutput.textContent = String(selected);
                    }

                    const ready = selectedRows().length > 0
                        && form.checkValidity();

                    confirmation?.classList.toggle(
                        'entry-confirmation-panel--ready',
                        ready
                    );

                    if (ready) {
                        reachableStep = Math.max(reachableStep, 4);
                    } else if (currentStep === 4) {
                        currentStep = 3;
                    }

                    paintOutputWorkflow(
                        currentStep,
                        reachableStep
                    );
                };

                form
                    .querySelector('[data-flow-step-section="2"]')
                    ?.addEventListener('focusin', () => {
                        setOutputStep(2);
                    });

                form
                    .querySelector('[data-flow-step-section="3"]')
                    ?.addEventListener('focusin', () => {
                        setOutputStep(3);
                    });

                rows.forEach((row) => {
                    row
                        .querySelector('[data-output-quantity]')
                        ?.addEventListener('input', () => {
                            setOutputStep(3);
                            refresh();
                        });
                });

                search?.addEventListener('input', () => {
                    const query = search.value
                        .trim()
                        .toLocaleLowerCase('es');

                    rows.forEach((row) => {
                        const text = (
                            row.dataset.outputSearchText || ''
                        ).toLocaleLowerCase('es');

                        row.hidden = query !== ''
                            && !text.includes(query);
                    });
                });

                confirmation?.addEventListener('focusin', () => {
                    if (selectedRows().length > 0
                        && form.checkValidity()) {
                        setOutputStep(4);
                    }
                });

                confirmation?.addEventListener('click', () => {
                    if (selectedRows().length > 0
                        && form.checkValidity()) {
                        setOutputStep(4);
                    }
                });

                form
                    .querySelector('[data-submit-button]')
                    ?.addEventListener('focus', () => {
                        if (selectedRows().length > 0
                            && form.checkValidity()) {
                            setOutputStep(4);
                        }
                    });

                refresh();
            });

        paintOutputWorkflow(currentStep, reachableStep);
    }
</script>
@endpush
