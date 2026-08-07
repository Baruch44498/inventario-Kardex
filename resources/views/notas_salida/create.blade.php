@extends('layouts.app')

@section('title', 'Nueva nota de salida')
@section('page-kicker', 'Notas de salida')
@section('page-title', 'Nueva nota de salida')

@section('content')
<div class="document-flow-page" data-document-note-wizard data-initial-step="{{ $pasoActual }}">
    <a href="{{ route('notas-salida.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a notas de salida
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Movimiento físico</p>
            <h1>Registrar nota de salida</h1>
            <p>
                Registra lo que realmente deja Almacén. La salida puede pertenecer a una orden,
                una Proforma o un uso interno; el sistema conserva su origen y tratamiento.
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

    <section id="paso-origen" class="panel order-selector-panel" data-flow-step-section="1">
        <div class="panel-heading panel-heading--split">
            <div>
                <p class="eyebrow">Origen</p>
                <h2>¿Por qué sale del Almacén?</h2>
                <p>Selecciona el documento o motivo que explica el movimiento físico.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('notas-salida.create') }}" class="order-selector-form" data-output-origin-form>
            <div class="form-field">
                <label for="motivo_salida_selector">Motivo de salida</label>
                <select id="motivo_salida_selector" name="motivo_salida" data-output-origin-type>
                    <option value="ORDEN_OPERACION" @selected($motivo === 'ORDEN_OPERACION')>Orden de operación (OM / OS / OP)</option>
                    <option value="PROFORMA" @selected($motivo === 'PROFORMA')>Proforma de Almacén</option>
                    <option value="USO_INTERNO" @selected($motivo === 'USO_INTERNO')>Uso interno</option>
                    <option value="OTRO" @selected($motivo === 'OTRO')>Otro</option>
                </select>
            </div>

            @if ($motivo === 'ORDEN_OPERACION')
                <div class="form-field">
                    <label for="orden_operacion_busqueda">Orden relacionada</label>
                    <x-ui.remote-combobox
                        name="orden_operacion_id"
                        search-id="orden_operacion_busqueda"
                        value-id="orden_operacion_id"
                        :search-url="route('catalogos.ordenes-operacion.buscar')"
                        :selected-id="$orden?->id"
                        :selected-label="$orden ? $orden->codigo_orden.' — '.($orden->cliente?->nombreVisible() ?? 'Sin cliente') : ''"
                        placeholder="Código, cliente o descripción"
                        empty-text="No hay órdenes abiertas o en proceso."
                        required
                    />
                </div>
            @elseif ($motivo === 'PROFORMA')
                <div class="form-field">
                    <label for="proforma_busqueda">Proforma</label>
                    <x-ui.remote-combobox
                        name="proforma_id"
                        search-id="proforma_busqueda"
                        value-id="proforma_id"
                        :search-url="route('catalogos.proformas-almacen.buscar')"
                        :selected-id="$proforma?->id"
                        :selected-label="$proforma ? $proforma->codigo.' — '.($proforma->cliente?->nombreVisible() ?? 'Sin cliente') : ''"
                        placeholder="Código de Proforma o cliente"
                        empty-text="No se encontró una Proforma vigente."
                        required
                    />
                </div>
            @endif

            <button type="submit" class="button button--primary">
                <x-ui.icon name="refresh" :size="17" />
                Cargar origen
            </button>
        </form>

        @if ($origenNoDisponible)
            <div class="notice notice--warning notice--block">
                <x-ui.icon name="warning" :size="18" />
                <span>El origen seleccionado ya no está disponible.</span>
            </div>
        @endif
    </section>

    @if ($origenListo)
        @if ($orden)
            <section class="order-context-card output-order-context" data-note-origin-context>
                <div class="order-context-card__main">
                    <span class="order-context-card__icon output-order-context__icon"><x-ui.icon name="orders" :size="25" /></span>
                    <div>
                        <span>Orden seleccionada</span>
                        <strong>{{ $orden->codigo_orden }}</strong>
                        <small>{{ $orden->cliente?->razon_social ?? 'Sin cliente asociado' }}</small>
                    </div>
                </div>
                <dl class="order-context-card__facts">
                    <div><dt>Tipo</dt><dd>{{ $orden->tipoOrden?->codigo ?? '—' }}</dd></div>
                    <div><dt>Apertura</dt><dd>{{ $orden->fecha_apertura?->format('d/m/Y') }}</dd></div>
                    <div><dt>Estado</dt><dd><span class="badge badge--info">{{ $orden->estado }}</span></dd></div>
                </dl>
            </section>
        @elseif ($proforma)
            <section class="order-context-card output-order-context" data-note-origin-context>
                <div class="order-context-card__main">
                    <span class="order-context-card__icon output-order-context__icon"><x-ui.icon name="clipboard" :size="25" /></span>
                    <div>
                        <span>Proforma seleccionada</span>
                        <strong>{{ $proforma->codigo }}</strong>
                        <small>{{ $proforma->cliente?->nombreVisible() ?? 'Sin cliente' }}</small>
                    </div>
                </div>
                <dl class="order-context-card__facts">
                    <div><dt>Emisión</dt><dd>{{ $proforma->fecha_emision?->format('d/m/Y') }}</dd></div>
                    <div><dt>Estado</dt><dd><span class="badge badge--info">{{ $proforma->estado }}</span></dd></div>
                </dl>
            </section>
        @endif

        <form method="POST" action="{{ route('notas-salida.store') }}" class="entry-form output-form" data-dirty-form data-loading-form data-output-form data-note-wizard-form>
            @csrf
            <input type="hidden" name="motivo_salida" value="{{ $motivo }}">
            @if ($orden)<input type="hidden" name="orden_operacion_id" value="{{ $orden->id }}">@endif
            @if ($proforma)<input type="hidden" name="proforma_id" value="{{ $proforma->id }}">@endif

            <section id="paso-datos" class="panel form-panel" data-flow-step-section="2">
                <div class="panel-heading">
                    <p class="eyebrow">Datos de entrega</p>
                    <h2>Documento y responsable</h2>
                </div>

                <div class="form-grid form-grid--entry-header">
                    <div class="form-field generated-code-form-field">
                        <span>Código de nota</span>
                        <div class="generated-code-field">
                            <span class="generated-code-field__icon"><x-ui.icon name="hash" :size="18" /></span>
                            <strong class="generated-code-field__value">NS-###-{{ now()->format('y') }}</strong>
                            <span class="badge badge--info">Automático</span>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="fecha_salida">Fecha de salida <span class="required-mark">*</span></label>
                        <input id="fecha_salida" name="fecha_salida" type="date" value="{{ old('fecha_salida', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </div>

                    <div class="form-field form-field--span-2">
                        <label for="entregado_a">Entregado a <span class="required-mark">*</span></label>
                        <input id="entregado_a" name="entregado_a" type="text" value="{{ old('entregado_a', $proforma?->cliente?->nombreVisible()) }}" maxlength="150" placeholder="Persona o empresa que recibe" required>
                        <small>Para herramientas, identifica a la persona responsable mientras permanezcan fuera del Almacén.</small>
                    </div>

                    <div class="form-field form-field--span-2">
                        <label for="observacion">Observación general</label>
                        <textarea id="observacion" name="observacion" rows="3" maxlength="500" placeholder="Destino, referencia o indicación adicional">{{ old('observacion') }}</textarea>
                    </div>
                </div>
            </section>

            <section id="paso-productos" class="panel" data-flow-step-section="3">
                <div class="panel-heading panel-heading--split">
                    <div>
                        <p class="eyebrow">Stock físico</p>
                        <h2>Productos que salen</h2>
                        <p>
                            <strong>Consumo</strong> significa salida definitiva para el trabajo.
                            <strong>Uso temporal</strong> identifica herramientas o elementos que deben regresar.
                        </p>
                    </div>
                </div>

                @error('detalles')
                    <div class="notice notice--danger notice--block"><x-ui.icon name="error" :size="18" /><span>{{ $message }}</span></div>
                @enderror

                @if ($filas->isEmpty())
                    <div class="empty-table-state empty-table-state--document-lines">
                        <div class="document-lines-empty">
                            <span class="empty-state__icon empty-state__icon--warning document-lines-empty__icon"><x-ui.icon name="warning" :size="30" /></span>
                            <div class="document-lines-empty__copy">
                                <strong>No hay existencias pendientes disponibles para este origen</strong>
                                <p>Verifica el stock físico o si los productos ya fueron despachados.</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="table-wrap table-wrap--wide table-wrap--responsive">
                        <table class="data-table entry-lines-table output-lines-table">
                            <thead>
                                <tr>
                                    <th class="table-sticky--start">Producto</th>
                                    <th>Repisa</th>
                                    <th>Stock físico</th>
                                    @if ($motivo === 'PROFORMA')<th>Pendiente Proforma</th>@endif
                                    <th>Tratamiento</th>
                                    <th>Cantidad</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filas as $indice => $fila)
                                    @php
                                        $inventario = $fila['inventario'];
                                        $detalleProforma = $fila['proforma_detalle'];
                                        $tratamientoFijo = $fila['tratamiento'];
                                        $maximo = $fila['pendiente_origen'] !== null
                                            ? min((float) $inventario->stock_actual, (float) $fila['pendiente_origen'])
                                            : (float) $inventario->stock_actual;
                                        $tratamientoAnterior = old("detalles.{$indice}.tratamiento", $tratamientoFijo ?: 'CONSUMO');
                                    @endphp
                                    <tr data-output-row>
                                        <td class="table-sticky--start">
                                            <input type="hidden" name="detalles[{{ $indice }}][inventario_id]" value="{{ $inventario->id }}">
                                            <input type="hidden" name="detalles[{{ $indice }}][producto_id]" value="{{ $inventario->producto_id }}">
                                            <input type="hidden" name="detalles[{{ $indice }}][repisa_id]" value="{{ $inventario->repisa_id }}">
                                            @if ($detalleProforma)<input type="hidden" name="detalles[{{ $indice }}][proforma_detalle_id]" value="{{ $detalleProforma->id }}">@endif
                                            <strong>{{ $inventario->producto?->codigo }}</strong>
                                            <span>{{ $inventario->producto?->descripcion }}</span>
                                            @if ($detalleProforma)
                                                <small>Línea {{ $detalleProforma->tratamiento === 'PRESTAMO' ? 'de préstamo' : 'de venta' }}</small>
                                            @endif
                                        </td>
                                        <td><span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $inventario->repisa?->codigo }}</span></td>
                                        <td><strong><x-ui.quantity :value="$inventario->stock_actual" /></strong></td>
                                        @if ($motivo === 'PROFORMA')
                                            <td><x-ui.quantity :value="$fila['pendiente_origen']" /></td>
                                        @endif
                                        <td>
                                            @if ($tratamientoFijo)
                                                <input type="hidden" name="detalles[{{ $indice }}][tratamiento]" value="{{ $tratamientoFijo }}" data-output-treatment>
                                                <span class="badge badge--{{ $tratamientoFijo === 'PRESTAMO_EXTERNO' ? 'warning' : 'info' }}">
                                                    {{ $tratamientoFijo === 'PRESTAMO_EXTERNO' ? 'Préstamo' : 'Venta' }}
                                                </span>
                                            @else
                                                <select name="detalles[{{ $indice }}][tratamiento]" class="table-input" data-output-treatment>
                                                    <option value="CONSUMO" @selected($tratamientoAnterior === 'CONSUMO')>Consumo</option>
                                                    <option value="USO_TEMPORAL" @selected($tratamientoAnterior === 'USO_TEMPORAL')>Uso temporal / herramienta</option>
                                                </select>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="number" name="detalles[{{ $indice }}][cantidad]" value="{{ old("detalles.{$indice}.cantidad", 0) }}" min="0" max="{{ $maximo }}" step="0.001" class="table-input table-input--quantity" data-output-quantity data-output-stock="{{ (float) $inventario->stock_actual }}" data-output-total-stock="{{ (float) ($fila['stock_total_producto'] ?? $inventario->stock_actual) }}">
                                            <small class="field-warning" data-last-tool-warning hidden>⚠️ Si confirmas, no quedarán unidades disponibles de esta herramienta en Almacén.</small>
                                            @error("detalles.{$indice}.cantidad")<small class="field-error table-field-error">{{ $message }}</small>@enderror
                                        </td>
                                        <td><input type="text" name="detalles[{{ $indice }}][observacion]" value="{{ old("detalles.{$indice}.observacion") }}" maxlength="300" placeholder="Opcional" class="table-input"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section id="paso-confirmacion" class="panel entry-confirmation-panel output-confirmation-panel" data-flow-step-section="4">
                <span class="entry-confirmation-panel__icon"><x-ui.icon name="check-circle" :size="26" /></span>
                <div class="entry-confirmation-panel__copy">
                    <p class="eyebrow">Confirmación</p>
                    <h2>Revisa antes de descontar el stock</h2>
                    <p>Solo al confirmar se crea el movimiento físico. Una reserva futura no descontará stock; este documento sí.</p>
                </div>
            </section>

            <div class="form-actions form-actions--sticky note-wizard-actions" data-note-wizard-actions>
                <a href="{{ route('notas-salida.index') }}" class="button button--ghost">Cancelar</a>
                <button type="button" class="button button--ghost" data-note-wizard-prev>Cambiar origen</button>
                <button type="button" class="button button--primary" data-note-wizard-next data-step2-label="Continuar a productos" data-step3-label="Revisar confirmación">Continuar a productos</button>
                <button type="submit" class="button button--primary" data-note-wizard-submit data-submit-button data-loading-text="Confirmando salida..." @disabled($filas->isEmpty()) hidden>
                    <span data-submit-label>Confirmar nota de salida</span>
                </button>
            </div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const originForm = document.querySelector('[data-output-origin-form]');
    const originType = originForm?.querySelector('[data-output-origin-type]');
    originType?.addEventListener('change', () => originForm.submit());

    document.querySelectorAll('[data-output-row]').forEach((row) => {
        const quantity = row.querySelector('[data-output-quantity]');
        const treatment = row.querySelector('[data-output-treatment]');
        const warning = row.querySelector('[data-last-tool-warning]');
        const refresh = () => {
            if (!quantity || !treatment || !warning) return;
            const stockTotal = Number(quantity.dataset.outputTotalStock || quantity.dataset.outputStock || 0);
            const value = Number(quantity.value || 0);
            warning.hidden = !(treatment.value === 'USO_TEMPORAL' && value > 0 && value >= stockTotal);
        };
        quantity?.addEventListener('input', refresh);
        treatment?.addEventListener('change', refresh);
        refresh();
    });
});
</script>
<script src="{{ asset('js/document-note-wizard.js') }}"></script>
@endpush
