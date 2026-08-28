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

        <form method="GET" action="{{ route('notas-salida.create') }}" class="order-selector-form order-selector-form--note-output" data-output-origin-form>
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
                    <label for="orden_operacion_busqueda">Orden activa relacionada</label>
                    <x-ui.remote-combobox
                        name="orden_operacion_id"
                        search-id="orden_operacion_busqueda"
                        value-id="orden_operacion_id"
                        :search-url="route('catalogos.ordenes-operacion.buscar')"
                        :selected-id="$orden?->id"
                        :selected-label="$orden ? $orden->codigo_orden.' — '.($orden->cliente?->nombreVisible() ?? 'Sin cliente') : ''"
                        placeholder="OM-0001, mantenimiento, OS, servicio, OP, producción o cliente"
                        empty-text="No se encontraron órdenes activas en ejecución."
                        required
                    />
                    <small>El buscador muestra únicamente OM/OS/OP en ejecución. Las órdenes cerradas, anuladas o aún no activadas no aparecen.</small>
                </div>

                @if ($orden)
                    <div class="form-field">
                        <label for="area_trabajo_selector">Área del trabajo</label>
                        <select id="area_trabajo_selector" name="area_trabajo" required>
                            @foreach ($areasTrabajo as $areaDisponible)
                                <option value="{{ $areaDisponible }}" @selected($areaTrabajo === $areaDisponible)>{{ $areaDisponible }}</option>
                            @endforeach
                        </select>
                        <small>Las áreas provienen de los grupos de materiales de la hoja de costos de esta orden.</small>
                    </div>
                @endif
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

            <button type="submit" class="button button--primary order-selector-form__submit">
                <x-ui.icon name="refresh" :size="17" />
                {{ $orden ? 'Cargar orden y área' : 'Cargar origen' }}
            </button>
        </form>

        @if ($origenNoDisponible)
            <div class="notice notice--warning notice--block">
                <x-ui.icon name="warning" :size="18" />
                <span>{{ $motivo === 'ORDEN_OPERACION' ? 'La orden seleccionada no está activa o ya no está disponible. Actívala antes de registrar una salida.' : 'El origen seleccionado ya no está disponible.' }}</span>
            </div>
        @endif
    </section>

    @if ($origenListo)
        @if ($orden)
            <section class="order-context-card output-order-context" data-note-origin-context>
                <div class="order-context-card__main">
                    <span class="order-context-card__icon output-order-context__icon"><x-ui.icon name="orders" :size="25" /></span>
                    <div>
                        <span>Orden activa seleccionada</span>
                        <strong>{{ $orden->codigo_orden }}</strong>
                        <small>{{ $orden->cliente?->razon_social ?? 'Sin cliente asociado' }}</small>
                    </div>
                </div>
                <dl class="order-context-card__facts">
                    <div><dt>Tipo</dt><dd>{{ $orden->tipoOrden?->codigo ?? '—' }}</dd></div>
                    <div><dt>Área</dt><dd>{{ $areaTrabajo ?? 'GENERAL' }}</dd></div>
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
            @if ($orden)<input type="hidden" name="area_trabajo" value="{{ $areaTrabajo }}">@endif
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

                    @if ($motivo === 'ORDEN_OPERACION')
                        <div class="form-field form-field--span-2">
                            <label for="recibido_por_empleado_id">Empleado que recibe <span class="required-mark">*</span></label>
                            <select id="recibido_por_empleado_id" name="recibido_por_empleado_id" required>
                                <option value="">Selecciona nombre y DNI</option>
                                @foreach ($empleadosActivos as $empleado)
                                    <option value="{{ $empleado->id }}" @selected((int) old('recibido_por_empleado_id') === $empleado->id)>
                                        {{ $empleado->nombre_completo }} — DNI {{ $empleado->dni }}
                                    </option>
                                @endforeach
                            </select>
                            <small>Se guardarán el nombre y DNI actuales como fotografía histórica de la entrega.</small>
                        </div>

                        @if ($empleadosActivos->isEmpty())
                            <div class="notice notice--warning notice--block form-field--span-2">
                                <x-ui.icon name="warning" :size="18" />
                                <span>Primero registra y activa al empleado que recibirá los productos.</span>
                            </div>
                        @endif
                    @else
                        <div class="form-field form-field--span-2">
                            <label for="entregado_a">Entregado a <span class="required-mark">*</span></label>
                            <input id="entregado_a" name="entregado_a" type="text" value="{{ old('entregado_a', $proforma?->cliente?->nombreVisible()) }}" maxlength="150" placeholder="Persona o empresa que recibe" required>
                            <small>Para herramientas, identifica a la persona responsable mientras permanezcan fuera del Almacén.</small>
                        </div>
                    @endif

                    <div class="form-field form-field--span-2">
                        <label for="observacion">Observación general</label>
                        <textarea id="observacion" name="observacion" rows="3" maxlength="500" placeholder="Destino, referencia o indicación adicional">{{ old('observacion') }}</textarea>
                    </div>
                </div>
            </section>

            <section id="paso-productos" class="panel" data-flow-step-section="3">
                <div class="panel-heading panel-heading--split output-stock-heading">
                    <div class="output-stock-heading__copy">
                        <p class="eyebrow">Stock físico</p>
                        <h2>Productos que salen</h2>
                        <p>
                            <strong>Consumo</strong> significa salida definitiva para el trabajo.
                            <strong>Uso temporal</strong> identifica herramientas o elementos que deben regresar.
                            Las reservas son blandas: si hay stock físico la salida nunca se bloquea, pero el sistema advierte cuando afecta material comprometido.
                        </p>
                    </div>

                    @if ($motivo === 'ORDEN_OPERACION' && $orden)
                        <div class="ui-collapsible-notice-cluster output-stock-heading__notices" aria-label="Ayudas de la entrega">
                            <x-ui.collapsible-notice
                                title="Entrega guiada por la orden"
                                label="Ver cómo funciona la entrega guiada"
                            >
                                <span>
                                    El sistema prioriza los materiales pendientes de {{ $orden->codigo_orden }}.
                                    Puedes hacer entregas parciales; cada Nota de Salida actualiza lo entregado y el saldo reservado.
                                </span>
                            </x-ui.collapsible-notice>

                            @if ($pendientesOrden->isEmpty())
                                <x-ui.collapsible-notice
                                    variant="success"
                                    icon="check-circle"
                                    title="Sin materiales pendientes planificados"
                                    label="Ver estado de los materiales planificados"
                                >
                                    <span>La orden no tiene materiales planificados pendientes de consumo. Las salidas adicionales se registrarán como consumo real no planificado o uso temporal.</span>
                                </x-ui.collapsible-notice>
                            @else
                                <x-ui.collapsible-notice
                                    variant="success"
                                    icon="inventory"
                                    title="Materiales pendientes de la orden"
                                    label="Ver resumen de materiales pendientes"
                                >
                                    <span>
                                        {{ $pendientesOrden->count() }} {{ $pendientesOrden->count() === 1 ? 'material pendiente' : 'materiales pendientes' }} por atender en esta orden.
                                    </span>
                                </x-ui.collapsible-notice>
                            @endif

                            <x-ui.collapsible-notice
                                title="Productos adicionales"
                                label="Ver ayuda sobre productos adicionales"
                            >
                                <span>Los materiales previstos de la orden ya están cargados en la tabla. Usa el buscador únicamente para agregar productos adicionales o no previstos.</span>
                            </x-ui.collapsible-notice>
                        </div>
                    @endif
                </div>

                @if ($motivo === 'ORDEN_OPERACION' && $orden)
                    @if ($pendientesSinStock->isNotEmpty())
                        <div class="notice notice--warning notice--block">
                            <x-ui.icon name="warning" :size="18" />
                            <div>
                                <strong>Pendientes sin stock físico</strong>
                                <span>
                                    {{ $pendientesSinStock->map(fn ($item) => ($item['producto']?->codigo ?? 'Producto').' (pend. '.number_format($item['pendiente'], 2).')')->implode(' · ') }}.
                                    Permanecen pendientes hasta que Almacén sea abastecido.
                                </span>
                            </div>
                        </div>
                    @endif
                @endif

                @error('detalles')
                    <div class="notice notice--danger notice--block"><x-ui.icon name="error" :size="18" /><span>{{ $message }}</span></div>
                @enderror

                @if ($motivo === 'ORDEN_OPERACION' && $orden)
                    <div class="output-product-finder output-product-finder--extras-only" data-output-product-finder>
                        <div class="form-field output-product-finder__extra">
                            <label for="producto_extra_salida_busqueda">Agregar producto adicional / no previsto</label>
                            <x-ui.remote-combobox
                                name="producto_extra_salida_id"
                                search-id="producto_extra_salida_busqueda"
                                value-id="producto_extra_salida_id"
                                :search-url="route('catalogos.existencias-salida.buscar', ['orden_id' => $orden->id])"
                                placeholder="Código, descripción o repisa"
                                empty-text="No se encontró stock adicional disponible."
                            />
                            <small>Úsalo solo para un producto que no forma parte del plan. Si piden más cantidad de un material ya previsto, registra el exceso en su misma fila.</small>
                        </div>
                    </div>

                    @if ($filas->isEmpty())
                        <div class="notice notice--warning notice--block output-extra-help">
                            <x-ui.icon name="warning" :size="18" />
                            <span>No hay stock físico disponible para registrar la salida de los materiales planificados. Puedes buscar un producto adicional si corresponde.</span>
                        </div>
                    @endif
                @endif

                @if ($filas->isEmpty() && $motivo !== 'ORDEN_OPERACION')
                    <div class="empty-table-state empty-table-state--document-lines">
                        <div class="document-lines-empty">
                            <span class="empty-state__icon empty-state__icon--warning document-lines-empty__icon"><x-ui.icon name="warning" :size="30" /></span>
                            <div class="document-lines-empty__copy">
                                <strong>
                                    {{ $motivo === 'ORDEN_OPERACION' && $pendientesOrden->isNotEmpty()
                                        ? 'No hay stock físico disponible para registrar la salida'
                                        : 'No hay existencias pendientes disponibles para este origen' }}
                                </strong>
                                <p>
                                    {{ $motivo === 'ORDEN_OPERACION' && $pendientesOrden->isNotEmpty()
                                        ? 'La necesidad de la orden permanece pendiente. Registra la salida cuando exista stock físico.'
                                        : 'Verifica el stock físico o si los productos ya fueron despachados.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="table-wrap table-wrap--wide table-wrap--responsive">
                        <table class="data-table entry-lines-table output-lines-table">
                            <thead>
                                <tr>
                                    <th class="table-sticky--start output-col-product">Producto</th>
                                    <th class="output-col-shelf">Repisa</th>
                                    <th class="output-col-stock">Stock físico</th>
                                    @if ($motivo === 'ORDEN_OPERACION')
                                        <th class="output-col-plan">Plan de la orden</th>
                                        <th class="output-col-reserve">Reserva pendiente</th>
                                    @endif
                                    <th class="output-col-available">Disponible libre</th>
                                    @if ($motivo === 'PROFORMA')<th class="output-col-proforma-pending">Pendiente Proforma</th>@endif
                                    <th class="output-col-treatment">Tratamiento</th>
                                    <th class="output-col-quantity">Cantidad</th>
                                    @if ($motivo === 'ORDEN_OPERACION')<th class="output-col-deviation">Motivo del exceso</th>@endif
                                    <th class="output-col-observation">Observación</th>
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
                                        $reservaOrden = (float) ($fila['reserva_orden_pendiente'] ?? 0);
                                        $reservadoGlobal = (float) ($fila['reservado_global_pendiente'] ?? 0);
                                        $disponibleLibre = (float) ($fila['disponible_libre'] ?? 0);
                                        $herramientasEnUso = (float) ($fila['herramientas_en_uso'] ?? 0);
                                        $materialOrden = $fila['material_orden'] ?? null;
                                        $pendienteOrden = (float) ($materialOrden['pendiente'] ?? 0);
                                        $planificado = (bool) $materialOrden;
                                    @endphp
                                    <tr
                                        data-output-row
                                        data-output-motivo="{{ $motivo }}"
                                        data-reserva-orden="{{ $reservaOrden }}"
                                        data-reservado-global="{{ $reservadoGlobal }}"
                                        data-stock-total="{{ (float) ($fila['stock_total_producto'] ?? $inventario->stock_actual) }}"
                                        data-herramientas-en-uso="{{ $herramientasEnUso }}"
                                        data-planificado-orden="{{ $planificado ? '1' : '0' }}"
                                        data-pendiente-orden="{{ $pendienteOrden }}"
                                        data-producto-id="{{ $inventario->producto_id }}"
                                    >
                                        <td class="table-sticky--start output-col-product">
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
                                        <td class="output-col-shelf"><span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $inventario->repisa?->codigo }}</span></td>
                                        <td class="output-col-stock">
                                            <strong><x-ui.quantity :value="$inventario->stock_actual" /></strong>
                                            <small>{{ $inventario->repisa?->codigo }}</small>
                                        </td>
                                        @if ($motivo === 'ORDEN_OPERACION')
                                            <td class="output-order-plan-cell output-col-plan">
                                                @if ($materialOrden)
                                                    @if ($pendienteOrden > 0.0001)
                                                        <span class="badge badge--warning">Pendiente</span>
                                                        <strong><x-ui.quantity :value="$pendienteOrden" /></strong>
                                                    @else
                                                        <span class="badge badge--success">Atendido</span>
                                                    @endif
                                                    <small>Previsto <x-ui.quantity :value="$materialOrden['previsto']" /> · Requerido <x-ui.quantity :value="$materialOrden['requerido']" /></small>
                                                    <small>Entregado <x-ui.quantity :value="$materialOrden['entregado']" /></small>
                                                @else
                                                    <span class="badge badge--neutral">No planificado</span>
                                                    <small>No figura en materiales requeridos.</small>
                                                @endif
                                            </td>
                                            <td class="output-col-reserve">
                                                @if ($reservaOrden > 0.0001)
                                                    <strong><x-ui.quantity :value="$reservaOrden" /></strong>
                                                    <small>saldo comprometido</small>
                                                @else
                                                    <span class="badge badge--neutral">Sin saldo</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="output-col-available">
                                            <strong @class(['availability-negative' => $disponibleLibre < 0])>
                                                <x-ui.quantity :value="$disponibleLibre" />
                                            </strong>
                                            <small>producto total</small>
                                        </td>
                                        @if ($motivo === 'PROFORMA')
                                            <td class="output-col-proforma-pending"><x-ui.quantity :value="$fila['pendiente_origen']" /></td>
                                        @endif
                                        <td class="output-col-treatment">
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
                                        <td class="output-col-quantity">
                                            <input type="number" name="detalles[{{ $indice }}][cantidad]" value="{{ old("detalles.{$indice}.cantidad", 0) }}" min="0" max="{{ $maximo }}" step="{{ $inventario->producto?->permite_fraccionamiento ? '0.01' : '1' }}" class="table-input table-input--quantity" data-output-quantity data-output-stock="{{ (float) $inventario->stock_actual }}" data-output-total-stock="{{ (float) ($fila['stock_total_producto'] ?? $inventario->stock_actual) }}">
                                            <small class="table-field-help">{{ $inventario->producto?->permite_fraccionamiento ? 'Admite decimales.' : 'Solo cantidades enteras.' }}</small>
                                            @if ($motivo === 'ORDEN_OPERACION' && $pendienteOrden > 0.0001)
                                                <button type="button" class="button button--ghost button--small output-fill-pending" data-fill-pending>
                                                    Usar pendiente
                                                </button>
                                            @endif
                                            <small class="field-warning" data-plan-warning hidden></small>
                                            <small class="field-warning" data-reservation-warning hidden></small>
                                            <small class="field-warning" data-committed-stock-warning hidden></small>
                                            <small class="field-warning" data-last-tool-warning hidden></small>
                                            @error("detalles.{$indice}.cantidad")<small class="field-error table-field-error">{{ $message }}</small>@enderror
                                        </td>
                                        @if ($motivo === 'ORDEN_OPERACION')
                                            <td class="output-col-deviation">
                                                <select name="detalles[{{ $indice }}][motivo_excedente]" class="table-input" data-output-excess-reason>
                                                    <option value="">Solo si supera el plan</option>
                                                    <option value="NECESIDAD_OPERATIVA" @selected(old("detalles.{$indice}.motivo_excedente") === 'NECESIDAD_OPERATIVA')>Necesidad operativa adicional</option>
                                                    <option value="REPOSICION_MALOGRADO" @selected(old("detalles.{$indice}.motivo_excedente") === 'REPOSICION_MALOGRADO')>Reposición de material malogrado</option>
                                                </select>
                                                <small>La salida normal no requiere motivo.</small>
                                            </td>
                                        @endif
                                        <td class="output-col-observation"><input type="text" name="detalles[{{ $indice }}][observacion]" value="{{ old("detalles.{$indice}.observacion") }}" maxlength="300" placeholder="Opcional" class="table-input"></td>
                                    </tr>
                                @endforeach

                                @if ($motivo === 'ORDEN_OPERACION')
                                    @foreach ($materialesSinExistencia as $materialSinExistencia)
                                        @php
                                            $productoSinExistencia = $materialSinExistencia['producto'];
                                            $pendienteSinExistencia = (float) $materialSinExistencia['pendiente'];
                                            $reservaSinExistencia = (float) $materialSinExistencia['reserva_pendiente'];
                                        @endphp
                                        <tr class="output-row--no-stock" data-planned-no-stock>
                                            <td class="table-sticky--start output-col-product">
                                                <strong>{{ $productoSinExistencia?->codigo }}</strong>
                                                <span>{{ $productoSinExistencia?->descripcion }}</span>
                                                <small>Material previsto en la orden</small>
                                            </td>
                                            <td class="output-col-shelf"><span class="badge badge--neutral">Sin repisa disponible</span></td>
                                            <td class="output-col-stock">
                                                <strong>0</strong>
                                                <small>Sin existencia disponible</small>
                                            </td>
                                            <td class="output-order-plan-cell output-col-plan">
                                                @if ($pendienteSinExistencia > 0.0001)
                                                    <span class="badge badge--warning">Pendiente</span>
                                                    <strong><x-ui.quantity :value="$pendienteSinExistencia" /></strong>
                                                @else
                                                    <span class="badge badge--success">Atendido</span>
                                                @endif
                                                <small>Previsto <x-ui.quantity :value="$materialSinExistencia['previsto']" /> · Requerido <x-ui.quantity :value="$materialSinExistencia['requerido']" /></small>
                                                <small>Entregado <x-ui.quantity :value="$materialSinExistencia['entregado']" /></small>
                                            </td>
                                            <td class="output-col-reserve">
                                                @if ($reservaSinExistencia > 0.0001)
                                                    <strong><x-ui.quantity :value="$reservaSinExistencia" /></strong>
                                                    <small>saldo comprometido</small>
                                                @else
                                                    <span class="badge badge--neutral">Sin saldo</span>
                                                @endif
                                            </td>
                                            <td class="output-col-available">
                                                <strong @class(['availability-negative' => $materialSinExistencia['disponible_libre'] < 0])>
                                                    <x-ui.quantity :value="$materialSinExistencia['disponible_libre']" />
                                                </strong>
                                                <small>producto total</small>
                                            </td>
                                            <td class="output-col-treatment"><span class="badge badge--info">Consumo</span></td>
                                            <td class="output-col-quantity">
                                                <span class="badge badge--warning">Sin stock</span>
                                                <small>Abastecer antes de despachar.</small>
                                            </td>
                                            <td class="output-col-deviation"><small>Sin salida disponible.</small></td>
                                            <td class="output-col-observation"><small>No disponible para esta Nota de Salida.</small></td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                        @if ($motivo === 'ORDEN_OPERACION' && $orden)
                            <template data-output-extra-row-template>
                                <tr
                                    data-output-row
                                    data-output-motivo="ORDEN_OPERACION"
                                    data-reserva-orden="0"
                                    data-reservado-global="0"
                                    data-stock-total="0"
                                    data-herramientas-en-uso="0"
                                    data-planificado-orden="0"
                                    data-pendiente-orden="0"
                                    data-producto-id=""
                                    data-extra-output-row
                                >
                                    <td class="table-sticky--start output-col-product">
                                        <input type="hidden" data-detail-field="inventario_id">
                                        <input type="hidden" data-detail-field="producto_id">
                                        <input type="hidden" data-detail-field="repisa_id">
                                        <strong data-extra-code></strong>
                                        <span data-extra-description></span>
                                        <small>Adicional / no previsto</small>
                                        <button type="button" class="button button--ghost button--small" data-remove-extra-row>Quitar</button>
                                    </td>
                                    <td class="output-col-shelf"><span class="location-chip"><x-ui.icon name="shelf" :size="14" /><span data-extra-shelf></span></span></td>
                                    <td class="output-col-stock"><strong data-extra-stock></strong><small data-extra-unit></small></td>
                                    <td class="output-order-plan-cell output-col-plan">
                                        <span class="badge badge--neutral">No planificado</span>
                                        <small>No figura en materiales requeridos.</small>
                                    </td>
                                    <td class="output-col-reserve"><span class="badge badge--neutral">Sin saldo</span></td>
                                    <td class="output-col-available"><strong data-extra-available></strong><small>producto total</small></td>
                                    <td class="output-col-treatment">
                                        <select class="table-input" data-detail-field="tratamiento" data-output-treatment>
                                            <option value="CONSUMO">Consumo</option>
                                            <option value="USO_TEMPORAL">Uso temporal / herramienta</option>
                                        </select>
                                    </td>
                                    <td class="output-col-quantity">
                                        <input type="number" value="0" min="0" step="0.001" class="table-input table-input--quantity" data-detail-field="cantidad" data-output-quantity data-output-stock="0" data-output-total-stock="0">
                                        <small class="field-warning" data-plan-warning hidden></small>
                                        <small class="field-warning" data-reservation-warning hidden></small>
                                        <small class="field-warning" data-committed-stock-warning hidden></small>
                                        <small class="field-warning" data-last-tool-warning hidden></small>
                                    </td>
                                    <td class="output-col-deviation">
                                        <select class="table-input" data-detail-field="motivo_excedente" data-output-excess-reason>
                                            <option value="">Selecciona el motivo</option>
                                            <option value="NECESIDAD_OPERATIVA">Necesidad operativa adicional</option>
                                            <option value="REPOSICION_MALOGRADO">Reposición de material malogrado</option>
                                        </select>
                                        <small>Obligatorio para consumo no previsto.</small>
                                    </td>
                                    <td class="output-col-observation"><input type="text" maxlength="300" placeholder="Motivo del adicional" class="table-input" data-detail-field="observacion"></td>
                                </tr>
                            </template>
                        @endif
                    </div>
                @endif
            </section>

            <section id="paso-confirmacion" class="panel entry-confirmation-panel output-confirmation-panel" data-flow-step-section="4">
                <span class="entry-confirmation-panel__icon"><x-ui.icon name="check-circle" :size="26" /></span>
                <div class="entry-confirmation-panel__copy">
                    <p class="eyebrow">Confirmación</p>
                    <h2>Revisa antes de descontar el stock</h2>
                    <p>Solo al confirmar se crea el movimiento físico. Una reserva no descuenta stock; este documento sí. Las salidas sobre stock comprometido se permiten y quedan advertidas.</p>
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

    const formatQuantity = (value) => Number(value || 0).toLocaleString('es-PE', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });

    const initializeOutputRow = (row) => {
        if (!row || row.dataset.outputInitialized === '1') return;
        row.dataset.outputInitialized = '1';

        const quantity = row.querySelector('[data-output-quantity]');
        const treatment = row.querySelector('[data-output-treatment]');
        const toolWarning = row.querySelector('[data-last-tool-warning]');
        const planWarning = row.querySelector('[data-plan-warning]');
        const reservationWarning = row.querySelector('[data-reservation-warning]');
        const committedWarning = row.querySelector('[data-committed-stock-warning]');
        const fillPending = row.querySelector('[data-fill-pending]');

        const refresh = () => {
            if (!quantity || !treatment) return;

            const stockTotal = Number(row.dataset.stockTotal || quantity.dataset.outputTotalStock || 0);
            const reservedOrder = Number(row.dataset.reservaOrden || 0);
            const reservedGlobal = Number(row.dataset.reservadoGlobal || 0);
            const toolsInUse = Number(row.dataset.herramientasEnUso || 0);
            const outputMotive = row.dataset.outputMotivo || '';
            const plannedForOrder = row.dataset.planificadoOrden === '1';
            const pendingRequired = Number(row.dataset.pendienteOrden || 0);
            const value = Number(quantity.value || 0);
            const isTemporary = treatment.value === 'USO_TEMPORAL';
            const isConsumption = treatment.value === 'CONSUMO';

            if (toolWarning) {
                const lastUnits = isTemporary && value > 0 && value >= stockTotal;
                toolWarning.hidden = !lastUnits;
                toolWarning.textContent = lastUnits
                    ? `⚠️ Si confirmas, no quedarán unidades disponibles de esta herramienta en Almacén.${toolsInUse > 0 ? ` Actualmente ${formatQuantity(toolsInUse)} ya están en uso.` : ''}`
                    : '';
            }

            let appliedReservation = 0;
            if (isConsumption && reservedOrder > 0) {
                appliedReservation = Math.min(value, reservedOrder);
            }
            const unreservedWithdrawal = Math.max(0, value - appliedReservation);
            const freeBefore = stockTotal - reservedGlobal;
            const committedAffected = Math.max(0, unreservedWithdrawal - Math.max(0, freeBefore));

            if (planWarning) {
                const appliesOrderPlan = outputMotive === 'ORDEN_OPERACION' && isConsumption && value > 0;
                const unplanned = appliesOrderPlan && !plannedForOrder;
                const alreadyAttended = appliesOrderPlan && plannedForOrder && pendingRequired <= 0.0001;
                const exceedsPending = appliesOrderPlan && plannedForOrder && pendingRequired > 0.0001 && value > pendingRequired;

                planWarning.hidden = !(unplanned || alreadyAttended || exceedsPending);
                planWarning.textContent = unplanned
                    ? '⚠️ Este consumo no figura en los materiales requeridos de la orden. Se permitirá y quedará como consumo real no planificado.'
                    : (alreadyAttended
                        ? '⚠️ El requerimiento planificado de este producto ya fue atendido. Esta cantidad quedará como consumo real adicional.'
                        : (exceedsPending
                            ? `⚠️ ${formatQuantity(value - pendingRequired)} exceden el pendiente planificado. El exceso quedará registrado como consumo real adicional.`
                            : ''));
            }

            if (reservationWarning) {
                const appliesOrderReservation = outputMotive === 'ORDEN_OPERACION' && isConsumption;
                const noReservation = appliesOrderReservation && value > 0 && reservedOrder <= 0;
                const exceedsReservation = appliesOrderReservation && value > reservedOrder && reservedOrder > 0;
                reservationWarning.hidden = !(noReservation || exceedsReservation);
                reservationWarning.textContent = noReservation
                    ? '⚠️ Este material no tiene reserva para esta orden. La salida se permitirá como consumo no planificado.'
                    : (exceedsReservation
                        ? `⚠️ ${formatQuantity(value - reservedOrder)} exceden la reserva pendiente de esta orden. El exceso se permitirá.`
                        : '');
            }

            if (committedWarning) {
                committedWarning.hidden = !(value > 0 && committedAffected > 0.0001);
                committedWarning.textContent = committedAffected > 0.0001
                    ? `⚠️ Esta salida usará ${formatQuantity(committedAffected)} de stock comprometido para otras órdenes. No se bloquea, pero aumenta el faltante de abastecimiento.`
                    : '';
            }
        };

        fillPending?.addEventListener('click', () => {
            if (!quantity) return;
            const pending = Number(row.dataset.pendienteOrden || 0);
            const stock = Number(quantity.dataset.outputStock || 0);
            quantity.value = String(Math.min(pending, stock));
            refresh();
        });

        quantity?.addEventListener('input', refresh);
        treatment?.addEventListener('change', refresh);
        refresh();
    };

    document.querySelectorAll('[data-output-row]').forEach(initializeOutputRow);

    const tableBody = document.querySelector('.output-lines-table tbody');

    const extraTemplate = document.querySelector('[data-output-extra-row-template]');
    const extraBox = document.querySelector('#producto_extra_salida_id')?.closest('[data-remote-combobox]');
    const submitButton = document.querySelector('[data-note-wizard-submit]');
    let nextDetailIndex = document.querySelectorAll('[data-output-row]').length;

    const detailField = (row, field, index, value = '') => {
        const input = row.querySelector(`[data-detail-field="${field}"]`);
        if (!input) return;
        input.name = `detalles[${index}][${field}]`;
        input.value = value ?? '';
    };

    const findRowByInventory = (inventoryId) => Array.from(document.querySelectorAll('[data-output-row]'))
        .find((row) => {
            const input = row.querySelector('input[name$="[inventario_id]"]');
            return input && String(input.value) === String(inventoryId);
        });

    const addExtraRow = (item) => {
        if (!extraTemplate || !tableBody || !item?.inventario_id) return;

        const existing = findRowByInventory(item.inventario_id);
        if (existing) {
            existing.scrollIntoView({ behavior: 'smooth', block: 'center' });
            existing.querySelector('[data-output-quantity]')?.focus();
            return;
        }

        const fragment = extraTemplate.content.cloneNode(true);
        const row = fragment.querySelector('[data-output-row]');
        if (!row) return;
        const index = nextDetailIndex++;

        detailField(row, 'inventario_id', index, item.inventario_id);
        detailField(row, 'producto_id', index, item.producto_id);
        detailField(row, 'repisa_id', index, item.repisa_id);
        detailField(row, 'tratamiento', index, 'CONSUMO');
        detailField(row, 'cantidad', index, '0');
        detailField(row, 'motivo_excedente', index, '');
        detailField(row, 'observacion', index, '');

        row.dataset.productoId = String(item.producto_id || '');
        row.dataset.reservaOrden = String(item.reserva_orden || 0);
        row.dataset.reservadoGlobal = String(item.reservado_global || 0);
        row.dataset.stockTotal = String(item.stock_total_producto || item.stock_actual || 0);
        row.dataset.herramientasEnUso = String(item.herramientas_en_uso || 0);

        row.querySelector('[data-extra-code]').textContent = item.codigo || 'Producto';
        row.querySelector('[data-extra-description]').textContent = item.descripcion || 'Sin descripción';
        row.querySelector('[data-extra-shelf]').textContent = item.repisa || '—';
        row.querySelector('[data-extra-stock]').textContent = formatQuantity(item.stock_actual);
        row.querySelector('[data-extra-unit]').textContent = item.unidad || '';
        row.querySelector('[data-extra-available]').textContent = formatQuantity(item.disponible_libre);

        const quantity = row.querySelector('[data-output-quantity]');
        if (quantity) {
            quantity.max = String(item.stock_actual || 0);
            quantity.step = item.permite_fraccionamiento ? '0.01' : '1';
            quantity.dataset.outputStock = String(item.stock_actual || 0);
            quantity.dataset.outputTotalStock = String(item.stock_total_producto || item.stock_actual || 0);
        }

        row.querySelector('[data-remove-extra-row]')?.addEventListener('click', () => row.remove());
        tableBody.appendChild(row);
        initializeOutputRow(row);
        if (submitButton) submitButton.disabled = false;
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        quantity?.focus();
    };

    extraBox?.addEventListener('remote-combobox:selected', (event) => {
        addExtraRow(event.detail || {});
        window.setTimeout(() => window.HidroilRemoteCombobox?.clear(extraBox, false), 0);
    });
});
</script>
<script src="{{ asset('js/document-note-wizard.js') }}"></script>
@endpush
