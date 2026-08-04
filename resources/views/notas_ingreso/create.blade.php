@extends('layouts.app')

@section('title', 'Nueva nota de ingreso')
@section('page-kicker', 'Notas de ingreso')
@section('page-title', 'Nueva nota de ingreso')

@section('content')
    <div class="document-flow-page">
    <a href="{{ route('notas-ingreso.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a notas de ingreso
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Recepción de compras</p>
            <h1>Registrar nota de ingreso</h1>
            <p>
                Selecciona una orden aprobada y registra únicamente las cantidades
                que llegaron físicamente al almacén.
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
                <h2>Orden de compra pendiente</h2>
                <p>Solo aparecen órdenes aprobadas con productos todavía pendientes de recepción.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('notas-ingreso.create') }}" class="order-selector-form">
            <div class="form-field">
                <label for="orden_compra_busqueda">Orden de compra</label>
                <x-ui.remote-combobox
                    name="orden_compra_id"
                    search-id="orden_compra_busqueda"
                    value-id="orden_compra_id"
                    :search-url="route('catalogos.ordenes-compra.buscar')"
                    :selected-id="$orden?->id"
                    :selected-label="$orden
                        ? $orden->codigo.' — '.($orden->proveedor?->nombreVisible() ?? 'Sin proveedor')
                        : ''"
                    placeholder="Código, documento o proveedor"
                    empty-text="No hay órdenes aprobadas pendientes de recepción."
                    required
                />
            </div>

            <button type="submit" class="button button--primary">
                <x-ui.icon name="refresh" :size="17" />
                Cargar orden
            </button>
        </form>

        @if (! $hayOrdenesDisponibles)
            <div class="inline-empty-state">
                <span class="empty-state__icon empty-state__icon--warning">
                    <x-ui.icon name="warning" :size="25" />
                </span>
                <div>
                    <strong>No hay órdenes disponibles para recepción</strong>
                    <span>
                        Debe existir una orden de compra aprobada con al menos un producto pendiente.
                    </span>
                </div>
                <a href="{{ route('modulos.show', 'ordenes-compra') }}" class="button button--ghost button--small">
                    Ver órdenes de compra
                </a>
            </div>
        @elseif ($ordenNoDisponible)
            <div class="notice notice--warning notice--block">
                <x-ui.icon name="warning" :size="18" />
                <span>La orden seleccionada ya no está disponible o no tiene cantidades pendientes.</span>
            </div>
        @endif
    </section>

    @if ($orden)
        <section class="order-context-card">
            <div class="order-context-card__main">
                <span class="order-context-card__icon">
                    <x-ui.icon name="purchase-order" :size="25" />
                </span>
                <div>
                    <span>Orden seleccionada</span>
                    <strong>{{ $orden->codigo }}</strong>
                    <small>{{ $orden->proveedor?->razon_social ?? 'Proveedor no disponible' }}</small>
                </div>
            </div>

            <dl class="order-context-card__facts">
                <div>
                    <dt>Emisión</dt>
                    <dd>{{ $orden->fecha_emision?->format('d/m/Y') }}</dd>
                </div>
                <div>
                    <dt>Moneda</dt>
                    <dd>{{ $orden->moneda }}</dd>
                </div>
                <div>
                    <dt>Total de orden</dt>
                    <dd>S/ {{ number_format((float) $orden->total, 2, '.', ',') }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd><span class="badge badge--warning">{{ $orden->estado }}</span></dd>
                </div>
            </dl>
        </section>

        <form
            method="POST"
            action="{{ route('notas-ingreso.store') }}"
            class="entry-form"
            data-dirty-form
            data-loading-form
            data-entry-form
        >
            @csrf
            <input type="hidden" name="orden_compra_id" value="{{ $orden->id }}">

            <section id="paso-datos" class="panel form-panel" data-flow-step-section="2">
                <div class="panel-heading">
                    <p class="eyebrow">Datos de recepción</p>
                    <h2>Datos de recepción</h2>
                </div>

                <div class="form-grid form-grid--entry-header">

                    <div class="form-field generated-code-form-field">
                        <span>Código de nota</span>
                        <div
                            class="generated-code-field"
                            aria-label="Código generado automáticamente"
                        >
                            <span class="generated-code-field__icon">
                                <x-ui.icon name="hash" :size="18" />
                            </span>
                            <strong class="generated-code-field__value">
                                NI-###-{{ now()->format('y') }}
                            </strong>
                            <span class="badge badge--info">Automático</span>
                        </div>
                        <small>
                            Se asignará al confirmar la recepción. Es independiente
                            del código de cada producto.
                        </small>
                    </div>

                    <div class="form-field">
                        <label for="fecha_ingreso">Fecha de ingreso <span class="required-mark">*</span></label>
                        <div class="input-with-icon">
                            <span class="input-with-icon__symbol">
                                <x-ui.icon name="calendar" :size="18" />
                            </span>
                            <input
                                id="fecha_ingreso"
                                name="fecha_ingreso"
                                type="date"
                                value="{{ old('fecha_ingreso', now()->toDateString()) }}"
                                max="{{ now()->toDateString() }}"
                                required
                                @class(['is-invalid' => $errors->has('fecha_ingreso')])
                            >
                        </div>
                        @error('fecha_ingreso')<small class="field-error" role="alert">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field">
                        <label for="factura_proveedor_id">Factura vinculada</label>
                        <div class="input-with-icon input-with-icon--select">
                            <span class="input-with-icon__symbol">
                                <x-ui.icon name="invoice" :size="18" />
                            </span>
                            <select id="factura_proveedor_id" name="factura_proveedor_id">
                                <option value="">Sin factura vinculada</option>
                                @foreach ($facturas as $factura)
                                    <option value="{{ $factura->id }}" @selected((string) old('factura_proveedor_id') === (string) $factura->id)>
                                        {{ $factura->tipo_documento }} {{ $factura->serie }}-{{ $factura->numero }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <small>La factura es opcional para registrar la recepción física.</small>
                        @error('factura_proveedor_id')<small class="field-error" role="alert">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field">
                        <label for="numero_guia_remision">Guía de remisión</label>
                        <div class="input-with-icon">
                            <span class="input-with-icon__symbol">
                                <x-ui.icon name="clipboard" :size="18" />
                            </span>
                            <input
                                id="numero_guia_remision"
                                name="numero_guia_remision"
                                type="text"
                                value="{{ old('numero_guia_remision') }}"
                                maxlength="60"
                                placeholder="Ej. T001-000452"
                            >
                        </div>
                        @error('numero_guia_remision')<small class="field-error" role="alert">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field form-grid__full">
                        <label for="observacion">Observación general</label>
                        <div class="input-with-icon input-with-icon--textarea">
                            <span class="input-with-icon__symbol">
                                <x-ui.icon name="align-left" :size="18" />
                            </span>
                            <textarea
                                id="observacion"
                                name="observacion"
                                rows="3"
                                maxlength="500"
                                placeholder="Ej. Recepción parcial según guía del proveedor"
                            >{{ old('observacion') }}</textarea>
                        </div>
                        <div class="field-meta">
                            <small>Registra novedades generales de la recepción.</small>
                            <small data-character-count="observacion">0 / 500</small>
                        </div>
                        @error('observacion')<small class="field-error" role="alert">{{ $message }}</small>@enderror
                    </div>
                </div>
            </section>

            <section id="paso-productos" class="panel entry-lines-panel" data-flow-step-section="3">
                <div class="panel-heading panel-heading--split">
                    <div>
                        <p class="eyebrow">Detalle de recepción</p>
                        <h2>Productos recibidos</h2>
                        <p>
                            Puedes registrar una recepción parcial reduciendo la cantidad.
                            Usa cero para omitir un producto en esta nota.
                        </p>
                    </div>

                    <div class="entry-live-total" aria-live="polite">
                        <span>Valor de esta recepción</span>
                        <strong data-entry-total>S/ 0.00</strong>
                    </div>
                </div>

                @error('detalles')
                    <div class="notice notice--danger notice--block">
                        <x-ui.icon name="error" :size="18" />
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <div class="table-wrap table-wrap--wide table-wrap--responsive table-wrap--form" data-responsive-table>
                    <table class="data-table data-table--responsive entry-lines-table">
                        <thead>
                            <tr>
                                <th class="table-sticky--start">Producto</th>
                                <th>Ordenado</th>
                                <th>Recibido</th>
                                <th>Pendiente</th>
                                <th>Cantidad a ingresar</th>
                                <th>Repisa</th>
                                <th>Costo unitario</th>
                                <th>Subtotal</th>
                                <th>Lote / vencimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orden->detalles as $indice => $detalle)
                                @php
                                    $pendiente = round(
                                        (float) $detalle->cantidad_ordenada
                                        - (float) $detalle->cantidad_recibida,
                                        3
                                    );
                                    $cantidadAnterior = old("detalles.{$indice}.cantidad", $pendiente);
                                    $costoAnterior = old("detalles.{$indice}.costo_unitario", $detalle->precio_unitario);
                                    $repisaAnterior = old("detalles.{$indice}.repisa_id");
                                    $repisaSeleccionada = $repisaAnterior
                                        ? $repisasSeleccionadas->get((int) $repisaAnterior)
                                        : null;
                                @endphp
                                <tr data-entry-row>
                                    <td class="entry-line-product table-sticky--start">
                                        <input type="hidden" name="detalles[{{ $indice }}][orden_compra_detalle_id]" value="{{ $detalle->id }}">
                                        <input type="hidden" name="detalles[{{ $indice }}][producto_id]" value="{{ $detalle->producto_id }}">
                                        <strong>{{ $detalle->producto?->codigo }}</strong>
                                        <span>{{ $detalle->producto?->descripcion }}</span>
                                        <small>{{ $detalle->producto?->unidadMedida?->codigo ?? 'UND' }}</small>
                                    </td>
                                    <td><x-ui.quantity :value="$detalle->cantidad_ordenada" /></td>
                                    <td><x-ui.quantity :value="$detalle->cantidad_recibida" /></td>
                                    <td><strong><x-ui.quantity :value="$pendiente" /></strong></td>
                                    <td>
                                        <input
                                            type="number"
                                            name="detalles[{{ $indice }}][cantidad]"
                                            value="{{ $cantidadAnterior }}"
                                            min="0"
                                            max="{{ $pendiente }}"
                                            step="0.001"
                                            inputmode="decimal"
                                            data-entry-quantity
                                            @class([
                                                'table-input',
                                                'is-invalid' => $errors->has("detalles.{$indice}.cantidad"),
                                            ])
                                        >
                                        @error("detalles.{$indice}.cantidad")
                                            <small class="field-error table-field-error">{{ $message }}</small>
                                        @enderror
                                    </td>
                                    <td>
                                        <x-ui.remote-combobox
                                            :name="'detalles['.$indice.'][repisa_id]'"
                                            :search-id="'repisa_busqueda_'.$indice"
                                            :value-id="'repisa_id_'.$indice"
                                            :search-url="route('catalogos.repisas.buscar')"
                                            :selected-id="$repisaSeleccionada?->id"
                                            :selected-label="$repisaSeleccionada
                                                ? $repisaSeleccionada->codigo.($repisaSeleccionada->descripcion ? ' — '.$repisaSeleccionada->descripcion : '')
                                                : ''"
                                            placeholder="Código de repisa"
                                            empty-text="No se encontró una repisa activa."
                                            :aria-label="'Repisa para '.($detalle->producto?->codigo ?? '')"
                                            :value-attributes="['data-entry-shelf' => '']"
                                        />
                                        @error("detalles.{$indice}.repisa_id")
                                            <small class="field-error table-field-error">{{ $message }}</small>
                                        @enderror
                                    </td>
                                    <td>
                                        <div class="table-money-input">
                                            <span>S/</span>
                                            <input
                                                type="number"
                                                name="detalles[{{ $indice }}][costo_unitario]"
                                                value="{{ $costoAnterior }}"
                                                min="0.0001"
                                                step="0.0001"
                                                inputmode="decimal"
                                                data-entry-cost
                                                @class([
                                                    'table-input',
                                                    'is-invalid' => $errors->has("detalles.{$indice}.costo_unitario"),
                                                ])
                                            >
                                        </div>
                                        @error("detalles.{$indice}.costo_unitario")
                                            <small class="field-error table-field-error">{{ $message }}</small>
                                        @enderror
                                    </td>
                                    <td>
                                        <strong data-entry-subtotal>S/ 0.00</strong>
                                    </td>
                                    <td>
                                        <div class="entry-lot-fields">
                                            <input
                                                type="text"
                                                name="detalles[{{ $indice }}][lote]"
                                                value="{{ old("detalles.{$indice}.lote") }}"
                                                maxlength="80"
                                                placeholder="Lote"
                                                class="table-input"
                                            >
                                            <input
                                                type="date"
                                                name="detalles[{{ $indice }}][fecha_vencimiento]"
                                                value="{{ old("detalles.{$indice}.fecha_vencimiento") }}"
                                                class="table-input"
                                            >
                                        </div>
                                        @error("detalles.{$indice}.fecha_vencimiento")
                                            <small class="field-error table-field-error">{{ $message }}</small>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>


            <section
                id="paso-confirmacion"
                class="panel entry-confirmation-panel"
                data-flow-step-section="4"
                data-entry-confirmation
                tabindex="-1"
            >
                <span class="entry-confirmation-panel__icon">
                    <x-ui.icon name="check-circle" :size="26" />
                </span>

                <div class="entry-confirmation-panel__copy">
                    <p class="eyebrow">Confirmación</p>
                    <h2>Revisa antes de actualizar el inventario</h2>
                    <p>
                        Al confirmar, el sistema registrará la nota, incrementará el stock,
                        recalculará el costo promedio y generará los movimientos correspondientes.
                    </p>
                </div>

                <div class="entry-confirmation-panel__total">
                    <span>Valor a ingresar</span>
                    <strong data-entry-confirmation-total>S/ 0.00</strong>
                </div>
            </section>

            <div class="form-actions form-actions--sticky">
                <button
                    type="button"
                    class="button button--ghost"
                    data-cancel-form
                    data-cancel-url="{{ route('notas-ingreso.index') }}"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="button button--primary"
                    data-submit-button
                    data-loading-text="Confirmando ingreso..."
                >
                    <span data-submit-icon><x-ui.icon name="check" :size="18" /></span>
                    <span class="button-spinner" data-submit-spinner hidden></span>
                    <span data-submit-label>Confirmar nota de ingreso</span>
                </button>
            </div>
        </form>
    @endif
    </div>
@endsection

@push('scripts')
<script>
    const workflowStepper = document.querySelector('[data-workflow-stepper]');

    const paintWorkflow = (currentStep, reachableStep) => {
        if (!workflowStepper) return;

        workflowStepper.dataset.currentStep = String(currentStep);
        workflowStepper.querySelectorAll('[data-workflow-step]').forEach((item) => {
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

    if (workflowStepper) {
        let currentStep = Number(workflowStepper.dataset.currentStep || 1);
        let reachableStep = currentStep;

        const setWorkflowStep = (step, options = {}) => {
            const next = Math.max(1, Math.min(4, Number(step)));
            reachableStep = Math.max(reachableStep, next);
            currentStep = next;
            paintWorkflow(currentStep, reachableStep);

            if (options.scroll) {
                const button = workflowStepper.querySelector(`[data-step-number="${next}"]`);
                const targetId = button?.dataset.stepTarget;
                const target = targetId ? document.getElementById(targetId) : null;
                target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                window.setTimeout(() => target?.focus?.({ preventScroll: true }), 380);
            }
        };

        const scrollToWorkflowSection = (button) => {
            if (!button || button.disabled) return;

            const targetId = button.dataset.stepTarget;
            const target = targetId
                ? document.getElementById(targetId)
                : null;

            target?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        };

        workflowStepper
            .querySelectorAll('[data-workflow-step-button]')
            .forEach((button) => {
                button.addEventListener('click', () => {
                    /*
                     * El clic solo navega hasta la sección.
                     * El progreso visual cambia únicamente por acciones reales
                     * dentro del formulario.
                     */
                    scrollToWorkflowSection(button);
                });
            });

        document.querySelectorAll('[data-entry-form]').forEach((form) => {
            const rows = Array.from(form.querySelectorAll('[data-entry-row]'));
            const totalOutputs = Array.from(form.querySelectorAll(
                '[data-entry-total], [data-entry-confirmation-total]'
            ));
            const confirmation = form.querySelector('[data-entry-confirmation]');

            const formatMoney = (value) => new Intl.NumberFormat('es-PE', {
                style: 'currency',
                currency: 'PEN',
                minimumFractionDigits: 2,
            }).format(value);

            const lineState = (row) => ({
                quantity: Math.max(0, Number(row.querySelector('[data-entry-quantity]')?.value || 0)),
                cost: Math.max(0, Number(row.querySelector('[data-entry-cost]')?.value || 0)),
                shelf: row.querySelector('[data-entry-shelf]')?.value || '',
            });

            const isReadyForConfirmation = () => {
                const positiveLines = rows.map(lineState).filter((line) => line.quantity > 0);
                return positiveLines.length > 0
                    && positiveLines.every((line) => line.cost > 0 && line.shelf !== '');
            };

            const refresh = () => {
                let total = 0;

                rows.forEach((row) => {
                    const quantity = row.querySelector('[data-entry-quantity]');
                    const cost = row.querySelector('[data-entry-cost]');
                    const shelf = row.querySelector('[data-entry-shelf]');
                    const subtotalOutput = row.querySelector('[data-entry-subtotal]');
                    const quantityValue = Math.max(0, Number(quantity?.value || 0));
                    const costValue = Math.max(0, Number(cost?.value || 0));
                    const subtotal = quantityValue * costValue;

                    total += subtotal;
                    if (subtotalOutput) subtotalOutput.textContent = formatMoney(subtotal);
                    if (shelf) shelf.required = quantityValue > 0;
                    if (cost) cost.required = quantityValue > 0;
                    row.classList.toggle('entry-row--skipped', quantityValue <= 0);
                });

                totalOutputs.forEach((output) => {
                    output.textContent = formatMoney(total);
                });

                const ready = isReadyForConfirmation();
                confirmation?.classList.toggle('entry-confirmation-panel--ready', ready);

                if (ready) {
                    reachableStep = Math.max(reachableStep, 4);
                } else if (currentStep === 4) {
                    currentStep = 3;
                }

                paintWorkflow(currentStep, reachableStep);
            };

            form.querySelector('[data-flow-step-section="2"]')?.addEventListener('focusin', () => {
                setWorkflowStep(2);
            });

            form.querySelector('[data-flow-step-section="3"]')?.addEventListener('focusin', () => {
                setWorkflowStep(3);
            });

            rows.forEach((row) => {
                row.querySelectorAll('[data-entry-quantity], [data-entry-cost], [data-entry-shelf]')
                    .forEach((field) => {
                        field.addEventListener('input', () => {
                            setWorkflowStep(3);
                            refresh();
                        });
                        field.addEventListener('change', () => {
                            setWorkflowStep(3);
                            refresh();
                        });
                    });
            });

            confirmation?.addEventListener('focusin', () => {
                if (isReadyForConfirmation()) setWorkflowStep(4);
            });
            confirmation?.addEventListener('click', () => {
                if (isReadyForConfirmation()) setWorkflowStep(4);
            });
            form.querySelector('[data-submit-button]')?.addEventListener('focus', () => {
                if (isReadyForConfirmation()) setWorkflowStep(4);
            });

            refresh();
        });

        paintWorkflow(currentStep, reachableStep);
    }
</script>
@endpush
