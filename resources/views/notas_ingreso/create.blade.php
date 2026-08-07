@extends('layouts.app')

@section('title', 'Nueva nota de ingreso')
@section('page-kicker', 'Notas de ingreso')
@section('page-title', 'Nueva nota de ingreso')

@section('content')
<div class="document-flow-page" data-document-note-wizard data-initial-step="{{ $pasoActual }}">
    <a href="{{ route('notas-ingreso.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a notas de ingreso
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Entrada física</p>
            <h1>Registrar nota de ingreso</h1>
            <p>
                Una Nota de Ingreso puede recibir una compra, devolver una herramienta al Almacén,
                retornar material no utilizado o registrar la reposición de un préstamo.
            </p>
        </div>
    </section>

    <x-ui.workflow-stepper :steps="$pasosRegistro" :current="$pasoActual" />

    @if ($errors->any())
        <div class="notice notice--danger notice--block" role="alert">
            <x-ui.icon name="error" :size="18" />
            <div><strong>Revisa la información del formulario.</strong><span>{{ $errors->first() }}</span></div>
        </div>
    @endif

    <section id="paso-origen" class="panel order-selector-panel" data-flow-step-section="1">
        <div class="panel-heading panel-heading--split">
            <div>
                <p class="eyebrow">Origen</p>
                <h2>¿Por qué entra al Almacén?</h2>
                <p>La referencia original evita devolver o reponer más unidades de las que realmente salieron.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('notas-ingreso.create') }}" class="order-selector-form" data-entry-origin-form>
            <div class="form-field">
                <label for="motivo_ingreso_selector">Motivo de ingreso</label>
                <select id="motivo_ingreso_selector" name="motivo_ingreso" data-entry-origin-type>
                    <option value="COMPRA" @selected($motivo === 'COMPRA')>Recepción de compra</option>
                    <option value="DEVOLUCION_HERRAMIENTA" @selected($motivo === 'DEVOLUCION_HERRAMIENTA')>Devolución de herramienta / uso temporal</option>
                    <option value="RETORNO_MATERIAL" @selected($motivo === 'RETORNO_MATERIAL')>Retorno de material no utilizado</option>
                    <option value="REPOSICION_PRESTAMO" @selected($motivo === 'REPOSICION_PRESTAMO')>Reposición de préstamo de Proforma</option>
                </select>
            </div>

            @if ($motivo === 'COMPRA')
                <div class="form-field">
                    <label for="orden_compra_busqueda">Orden de compra</label>
                    <x-ui.remote-combobox
                        name="orden_compra_id"
                        search-id="orden_compra_busqueda"
                        value-id="orden_compra_id"
                        :search-url="route('catalogos.ordenes-compra.buscar')"
                        :selected-id="$orden?->id"
                        :selected-label="$orden ? $orden->codigo.' — '.($orden->proveedor?->nombreVisible() ?? 'Sin proveedor') : ''"
                        placeholder="Código o proveedor"
                        empty-text="No hay órdenes aprobadas pendientes de recepción."
                        required
                    />
                </div>
            @elseif (in_array($motivo, ['DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL'], true))
                <div class="form-field">
                    <label for="nota_salida_busqueda">Nota de Salida original</label>
                    <x-ui.remote-combobox
                        name="nota_salida_id"
                        search-id="nota_salida_busqueda"
                        value-id="nota_salida_id"
                        :search-url="route('catalogos.notas-salida.buscar', ['contexto' => $motivo === 'DEVOLUCION_HERRAMIENTA' ? 'devolucion_herramienta' : 'retorno_material'])"
                        :selected-id="$notaSalida?->id"
                        :selected-label="$notaSalida ? $notaSalida->codigo.' — '.($notaSalida->entregado_a ?: 'Sin receptor') : ''"
                        placeholder="Código de Nota de Salida o responsable"
                        empty-text="No se encontró una Nota de Salida con productos pendientes de retorno."
                        required
                    />
                </div>
            @else
                <div class="form-field">
                    <label for="proforma_reposicion_busqueda">Proforma del préstamo</label>
                    <x-ui.remote-combobox
                        name="proforma_id"
                        search-id="proforma_reposicion_busqueda"
                        value-id="proforma_id"
                        :search-url="route('catalogos.proformas-almacen.buscar', ['contexto' => 'reposicion_prestamo'])"
                        :selected-id="$proforma?->id"
                        :selected-label="$proforma ? $proforma->codigo.' — '.($proforma->cliente?->nombreVisible() ?? 'Sin cliente') : ''"
                        placeholder="Código de Proforma o cliente"
                        empty-text="No se encontró una Proforma con préstamos."
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
            <div class="notice notice--warning notice--block"><x-ui.icon name="warning" :size="18" /><span>El origen seleccionado ya no está disponible.</span></div>
        @endif
    </section>

    @if ($origenListo)
        <section class="order-context-card" data-note-origin-context>
            <div class="order-context-card__main">
                <span class="order-context-card__icon"><x-ui.icon name="entry" :size="25" /></span>
                <div>
                    <span>Origen seleccionado</span>
                    @if ($orden)
                        <strong>{{ $orden->codigo }}</strong>
                        <small>{{ $orden->proveedor?->razon_social ?? 'Proveedor no disponible' }}</small>
                    @elseif ($notaSalida)
                        <strong>{{ $notaSalida->codigo }}</strong>
                        <small>{{ $notaSalida->entregado_a ?: 'Sin receptor registrado' }}</small>
                    @else
                        <strong>{{ $proforma->codigo }}</strong>
                        <small>{{ $proforma->cliente?->nombreVisible() ?? 'Sin cliente' }}</small>
                    @endif
                </div>
            </div>
            <dl class="order-context-card__facts">
                <div><dt>Tipo</dt><dd>{{ match($motivo) { 'COMPRA' => 'Compra', 'DEVOLUCION_HERRAMIENTA' => 'Devolución de herramienta', 'RETORNO_MATERIAL' => 'Retorno de material', default => 'Reposición de préstamo' } }}</dd></div>
                <div><dt>Pendientes</dt><dd>{{ $filas->count() }} línea(s)</dd></div>
            </dl>
        </section>

        <form method="POST" action="{{ route('notas-ingreso.store') }}" class="entry-form" data-dirty-form data-loading-form data-note-wizard-form>
            @csrf
            <input type="hidden" name="motivo_ingreso" value="{{ $motivo }}">
            @if ($orden)<input type="hidden" name="orden_compra_id" value="{{ $orden->id }}">@endif
            @if ($notaSalida)<input type="hidden" name="nota_salida_id" value="{{ $notaSalida->id }}">@endif
            @if ($proforma)<input type="hidden" name="proforma_id" value="{{ $proforma->id }}">@endif

            <section id="paso-datos" class="panel form-panel" data-flow-step-section="2">
                <div class="panel-heading"><p class="eyebrow">Datos de recepción</p><h2>Documento y referencia</h2></div>
                <div class="form-grid form-grid--entry-header">
                    <div class="form-field generated-code-form-field">
                        <span>Código de nota</span>
                        <div class="generated-code-field"><span class="generated-code-field__icon"><x-ui.icon name="hash" :size="18" /></span><strong class="generated-code-field__value">NI-###-{{ now()->format('y') }}</strong><span class="badge badge--info">Automático</span></div>
                    </div>
                    <div class="form-field">
                        <label for="fecha_ingreso">Fecha de ingreso <span class="required-mark">*</span></label>
                        <input id="fecha_ingreso" name="fecha_ingreso" type="date" value="{{ old('fecha_ingreso', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </div>

                    @if ($motivo === 'COMPRA')
                        <div class="form-field">
                            <label for="factura_proveedor_id">Factura vinculada</label>
                            <select id="factura_proveedor_id" name="factura_proveedor_id">
                                <option value="">Sin factura vinculada</option>
                                @foreach ($facturas as $factura)
                                    <option value="{{ $factura->id }}" @selected((string) old('factura_proveedor_id') === (string) $factura->id)>{{ $factura->tipo_documento }} {{ $factura->serie }}-{{ $factura->numero }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="numero_guia_remision">Guía de remisión</label>
                            <input id="numero_guia_remision" name="numero_guia_remision" type="text" value="{{ old('numero_guia_remision') }}" maxlength="60">
                        </div>
                    @endif

                    <div class="form-field form-field--span-2">
                        <label for="observacion">Observación general</label>
                        <textarea id="observacion" name="observacion" rows="3" maxlength="500" placeholder="Estado de la herramienta, material retornado o referencia de la recepción">{{ old('observacion') }}</textarea>
                    </div>
                </div>
            </section>

            <section id="paso-productos" class="panel" data-flow-step-section="3">
                <div class="panel-heading panel-heading--split">
                    <div>
                        <p class="eyebrow">Productos</p>
                        <h2>{{ $motivo === 'COMPRA' ? 'Productos recibidos' : 'Productos que regresan al stock' }}</h2>
                        <p>
                            El sistema solo permite ingresar hasta la cantidad pendiente del documento original.
                            Las devoluciones y reposiciones conservan la trazabilidad de la salida.
                        </p>
                    </div>
                </div>

                @error('detalles')<div class="notice notice--danger notice--block"><x-ui.icon name="error" :size="18" /><span>{{ $message }}</span></div>@enderror

                @if ($filas->isEmpty())
                    <div class="empty-table-state empty-table-state--document-lines">
                        <div class="document-lines-empty">
                            <span class="empty-state__icon empty-state__icon--success document-lines-empty__icon"><x-ui.icon name="check-circle" :size="30" /></span>
                            <div class="document-lines-empty__copy"><strong>No hay cantidades pendientes</strong><p>El origen seleccionado ya fue recibido, devuelto o repuesto completamente.</p></div>
                        </div>
                    </div>
                @else
                    <div class="table-wrap table-wrap--wide table-wrap--responsive">
                        <table class="data-table entry-lines-table">
                            <thead>
                                <tr>
                                    <th class="table-sticky--start">Producto</th>
                                    <th>Pendiente</th>
                                    <th>Cantidad que ingresa</th>
                                    <th>Repisa destino</th>
                                    @if ($motivo === 'COMPRA')<th>Costo unitario</th><th>Lote / vencimiento</th>@endif
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filas as $indice => $fila)
                                    @php
                                        $producto = $fila['producto'];
                                        $repisaId = old("detalles.{$indice}.repisa_id", $fila['repisa_default_id']);
                                        $repisaSeleccionada = $repisaId ? $repisasSeleccionadas->get((int) $repisaId) : null;
                                    @endphp
                                    <tr data-entry-row>
                                        <td class="table-sticky--start">
                                            <input type="hidden" name="detalles[{{ $indice }}][producto_id]" value="{{ $producto->id }}">
                                            @if ($fila['orden_compra_detalle_id'])<input type="hidden" name="detalles[{{ $indice }}][orden_compra_detalle_id]" value="{{ $fila['orden_compra_detalle_id'] }}">@endif
                                            @if ($fila['nota_salida_detalle_id'])<input type="hidden" name="detalles[{{ $indice }}][nota_salida_detalle_id]" value="{{ $fila['nota_salida_detalle_id'] }}">@endif
                                            @if ($fila['proforma_detalle_id'])<input type="hidden" name="detalles[{{ $indice }}][proforma_detalle_id]" value="{{ $fila['proforma_detalle_id'] }}">@endif
                                            <strong>{{ $producto->codigo }}</strong>
                                            <span>{{ $producto->descripcion }}</span>
                                            <small>{{ $producto->unidadMedida?->codigo ?? 'UND' }}</small>
                                        </td>
                                        <td><strong><x-ui.quantity :value="$fila['pendiente']" /></strong></td>
                                        <td>
                                            <input type="number" name="detalles[{{ $indice }}][cantidad]" value="{{ old("detalles.{$indice}.cantidad", 0) }}" min="0" max="{{ $fila['pendiente'] }}" step="0.001" class="table-input" data-entry-quantity>
                                            @error("detalles.{$indice}.cantidad")<small class="field-error table-field-error">{{ $message }}</small>@enderror
                                        </td>
                                        <td>
                                            <x-ui.remote-combobox
                                                :name="'detalles['.$indice.'][repisa_id]'"
                                                :search-id="'repisa_busqueda_'.$indice"
                                                :value-id="'repisa_id_'.$indice"
                                                :search-url="route('catalogos.repisas.buscar')"
                                                :selected-id="$repisaSeleccionada?->id"
                                                :selected-label="$repisaSeleccionada ? $repisaSeleccionada->codigo.($repisaSeleccionada->descripcion ? ' — '.$repisaSeleccionada->descripcion : '') : ''"
                                                placeholder="Código de repisa"
                                                empty-text="No se encontró una repisa activa."
                                            />
                                            @error("detalles.{$indice}.repisa_id")<small class="field-error table-field-error">{{ $message }}</small>@enderror
                                        </td>
                                        @if ($motivo === 'COMPRA')
                                            <td><input type="number" name="detalles[{{ $indice }}][costo_unitario]" value="{{ old("detalles.{$indice}.costo_unitario", $fila['costo_default']) }}" min="0.0001" step="0.0001" class="table-input"></td>
                                            <td>
                                                <div class="entry-lot-fields">
                                                    <input type="text" name="detalles[{{ $indice }}][lote]" value="{{ old("detalles.{$indice}.lote") }}" maxlength="80" placeholder="Lote" class="table-input">
                                                    <input type="date" name="detalles[{{ $indice }}][fecha_vencimiento]" value="{{ old("detalles.{$indice}.fecha_vencimiento") }}" class="table-input">
                                                </div>
                                            </td>
                                        @else
                                            <input type="hidden" name="detalles[{{ $indice }}][costo_unitario]" value="{{ $fila['costo_default'] }}">
                                        @endif
                                        <td><input type="text" name="detalles[{{ $indice }}][observacion]" value="{{ old("detalles.{$indice}.observacion") }}" maxlength="300" placeholder="Opcional" class="table-input"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section id="paso-confirmacion" class="panel entry-confirmation-panel" data-flow-step-section="4">
                <span class="entry-confirmation-panel__icon"><x-ui.icon name="check-circle" :size="26" /></span>
                <div class="entry-confirmation-panel__copy">
                    <p class="eyebrow">Confirmación</p>
                    <h2>Revisa antes de incrementar el stock</h2>
                    <p>
                        Al confirmar se registra una entrada real al Kardex. Una devolución de herramienta
                        deja de estar pendiente; una reposición reduce el saldo del préstamo.
                    </p>
                </div>
            </section>

            <div class="form-actions form-actions--sticky note-wizard-actions" data-note-wizard-actions>
                <a href="{{ route('notas-ingreso.index') }}" class="button button--ghost">Cancelar</a>
                <button type="button" class="button button--ghost" data-note-wizard-prev>Cambiar origen</button>
                <button type="button" class="button button--primary" data-note-wizard-next data-step2-label="Continuar a productos" data-step3-label="Revisar confirmación">Continuar a productos</button>
                <button type="submit" class="button button--primary" data-note-wizard-submit data-submit-button data-loading-text="Confirmando ingreso..." @disabled($filas->isEmpty()) hidden>
                    <span data-submit-label>Confirmar nota de ingreso</span>
                </button>
            </div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const originForm = document.querySelector('[data-entry-origin-form]');
    const originType = originForm?.querySelector('[data-entry-origin-type]');
    originType?.addEventListener('change', () => originForm.submit());
});
</script>
<script src="{{ asset('js/document-note-wizard.js') }}"></script>
@endpush
