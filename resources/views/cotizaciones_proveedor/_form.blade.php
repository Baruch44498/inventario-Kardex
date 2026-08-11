@php
    $editando = isset($cotizacion);

    $lineasIniciales = old(
        'detalles',
        $editando
            ? $cotizacion->detalles->map(fn ($detalle) => [
                'requisicion_detalle_id' => $detalle->requisicion_detalle_id,
                'producto_id' => $detalle->producto_id,
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => $detalle->precio_unitario,
                'descuento_modo' => $detalle->descuento_modo,
                'descuento_tipo' => $detalle->descuento_tipo,
                'descuento_valor' => $detalle->descuento_valor,
                'igv_modo' => $detalle->igv_modo,
                'marca_ofertada' => $detalle->marca_ofertada,
                'observacion' => $detalle->observacion,
            ])->values()->all()
            : (($lineasRequisicion ?? []) !== []
                ? $lineasRequisicion
                : [[
                    'requisicion_detalle_id' => null,
                    'producto_id' => '',
                    'cantidad' => 1,
                    'precio_unitario' => '',
                    'descuento_modo' => 'SIN_DESCUENTO',
                    'descuento_tipo' => '',
                    'descuento_valor' => '',
                    'igv_modo' => 'AGREGAR',
                    'marca_ofertada' => '',
                    'observacion' => '',
                ]])
    );

    $modoDescuentoGlobal = old(
        'descuento_global_modo',
        ($cotizacion->descuento_global_modo ?? 'SIN_DESCUENTO') === 'APLICAR'
            ? 'APLICAR'
            : 'SIN_DESCUENTO'
    );

    $erroresPasoProductos = collect($errors->keys())->contains(
        fn (string $campo) => str_starts_with($campo, 'detalles')
            || str_starts_with($campo, 'descuento_global')
    );
    $pasoInicial = $errors->has('reconciliacion_documento')
        ? 3
        : ($errors->any() && $erroresPasoProductos ? 2 : 1);
    $cabeceraImportada = isset($importacionAsistida) && $importacionAsistida
        ? data_get($importacionAsistida->datos_extraidos, 'cabecera', [])
        : [];
    $importesDocumento = data_get($cabeceraImportada, 'importes_documento', []);
    $conciliacionInicial = data_get($cabeceraImportada, 'conciliacion', []);
    $totalDocumento = is_numeric($importesDocumento['total'] ?? null)
        ? (float) $importesDocumento['total']
        : null;
    // Estos valores no se muestran al usuario: conservan cuatro decimales para
    // que JavaScript concilie importes sin confundir precisión técnica con el
    // formato visual de dos decimales.
    $importeDocumentoParaDatos = static fn ($valor): string => is_numeric($valor)
        ? sprintf('%.4F', (float) $valor)
        : '';
    $pasosCotizacion = [
        [
            'number' => 1,
            'name' => 'Datos de cotización',
            'description' => 'Proveedor y documento',
            'target' => 'paso-datos-cotizacion',
        ],
        [
            'number' => 2,
            'name' => 'Productos y precios',
            'description' => 'IGV y descuentos',
            'target' => 'paso-productos-cotizacion',
        ],
        [
            'number' => 3,
            'name' => 'Revisar y registrar',
            'description' => 'Confirmación final',
            'target' => 'paso-resumen-cotizacion',
        ],
    ];

    $datosOpcionalesAbiertos = old('condiciones_pago')
        || old('condiciones_entrega')
        || old('observacion')
        || ($editando && (
            $cotizacion->condiciones_pago
            || $cotizacion->condiciones_entrega
            || $cotizacion->observacion
        ));
@endphp

@if (isset($importacionAsistida) && $importacionAsistida)
    <input type="hidden" name="importacion_cotizacion_id" value="{{ old('importacion_cotizacion_id', $importacionAsistida->id) }}">
@endif

<div class="supplier-quote-wizard"
    data-supplier-quote-wizard data-initial-step="{{ $pasoInicial }}"
    data-product-search-url="{{ route('cotizaciones-proveedor.productos.buscar') }}"
    data-product-create-url="{{ route('cotizaciones-proveedor.productos.registro-rapido') }}"
    @if ($totalDocumento !== null)
        data-document-total="{{ $importeDocumentoParaDatos($totalDocumento) }}"
        data-document-subtotal="{{ $importeDocumentoParaDatos($importesDocumento['subtotal'] ?? null) }}"
        data-document-tax="{{ $importeDocumentoParaDatos($importesDocumento['igv'] ?? null) }}"
        data-document-currency="{{ $cabeceraImportada['moneda'] ?? 'PEN' }}"
    @endif>
    <x-ui.workflow-stepper
        :steps="$pasosCotizacion"
        :current="$pasoInicial"
        label="Progreso del registro de cotización"
    />

    @if ($errors->any())
        <div class="notice notice--danger notice--block supplier-quote-wizard__error" role="alert">
            <x-ui.icon name="error" :size="18" />
            <div>
                <strong>Revisa la información del formulario.</strong>
                <span>{{ $errors->first() }}</span>
            </div>
        </div>
    @endif

    <div class="supplier-quote-sections">
        <section id="paso-datos-cotizacion"
            class="form-section supplier-quote-step-panel"
            data-quote-step-panel="1"
            @if ($pasoInicial !== 1) hidden @endif>
            <div class="form-section__heading">
                <span class="form-section__icon">
                    <x-ui.icon name="suppliers" :size="20" />
                </span>
                <div>
                    <p class="eyebrow">Paso 1 de 3</p>
                    <h2>Proveedor y datos del documento</h2>
                    <p>Completa primero la información que identifica la cotización.</p>
                </div>
            </div>

            <div class="form-grid supplier-quote-form-grid">
                <div class="form-field">
                    <label for="proveedor_busqueda">
                        Proveedor <span class="required-mark">*</span>
                    </label>
                    <x-ui.remote-combobox
                        name="proveedor_id"
                        search-id="proveedor_busqueda"
                        value-id="proveedor_id"
                        :search-url="route('catalogos.proveedores.buscar')"
                        :selected-id="$proveedorSeleccionado?->id"
                        :selected-label="$proveedorSeleccionado
                            ? $proveedorSeleccionado->ruc.' — '.$proveedorSeleccionado->nombreVisible()
                            : ''"
                        placeholder="RUC o razón social"
                        empty-text="Proveedor no encontrado. Regístralo primero en Proveedores."
                        :value-attributes="['data-quote-provider' => '']"
                        required
                    />
                    @error('proveedor_id')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-field">
                    <label for="numero_documento">N.º de cotización del proveedor</label>
                    <input id="numero_documento" name="numero_documento" type="text"
                        value="{{ old('numero_documento', $cotizacion->numero_documento ?? '') }}"
                        maxlength="60" placeholder="Ej. COT-4587"
                        data-quote-document-number>
                    <small>Es el número que figura en el documento recibido.</small>
                    @error('numero_documento')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-field">
                    <label for="fecha_cotizacion">
                        Fecha de cotización <span class="required-mark">*</span>
                    </label>
                    <input id="fecha_cotizacion" name="fecha_cotizacion" type="date"
                        value="{{ old('fecha_cotizacion', isset($cotizacion) ? $cotizacion->fecha_cotizacion->format('Y-m-d') : now()->format('Y-m-d')) }}"
                        required data-quote-date>
                    @error('fecha_cotizacion')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-field">
                    <label for="fecha_validez">Vigencia hasta</label>
                    <input id="fecha_validez" name="fecha_validez" type="date"
                        value="{{ old('fecha_validez', isset($cotizacion) && $cotizacion->fecha_validez ? $cotizacion->fecha_validez->format('Y-m-d') : '') }}">
                    @error('fecha_validez')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-field">
                    <label for="moneda">Moneda <span class="required-mark">*</span></label>
                    <select id="moneda" name="moneda" required
                        data-supplier-quote-currency>
                        <option value="PEN"
                            @selected(old('moneda', $cotizacion->moneda ?? 'PEN') === 'PEN')>
                            Soles (PEN)
                        </option>
                        <option value="USD"
                            @selected(old('moneda', $cotizacion->moneda ?? '') === 'USD')>
                            Dólares (USD)
                        </option>
                    </select>
                    @error('moneda')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-field" data-supplier-exchange-field>
                    <label for="tipo_cambio">
                        Tipo de cambio <span class="required-mark">*</span>
                    </label>
                    <input id="tipo_cambio" name="tipo_cambio" type="number"
                        step="0.000001" min="0.000001"
                        value="{{ old('tipo_cambio', $cotizacion->tipo_cambio ?? '') }}"
                        data-supplier-exchange-input placeholder="Ej. 3.750000">
                    <small>Solo genera la equivalencia referencial en soles.</small>
                    @error('tipo_cambio')<small class="field-error">{{ $message }}</small>@enderror
                </div>

                <div class="form-field form-field--wide">
                    <label for="requisicion_busqueda">Requerimiento relacionado</label>
                    <x-ui.remote-combobox
                        name="requisicion_id"
                        search-id="requisicion_busqueda"
                        value-id="requisicion_id"
                        :search-url="route('catalogos.requisiciones.buscar')"
                        :selected-id="$requisicionSeleccionada?->id"
                        :selected-label="$requisicionSeleccionada
                            ? $requisicionSeleccionada->codigo.' — '.($requisicionSeleccionada->fecha_solicitud?->format('d/m/Y') ?? 'Sin fecha')
                            : ''"
                        placeholder="Código o descripción"
                        empty-text="No se encontró un requerimiento enviado."
                    />
                    <small>Vincula esta oferta con la necesidad enviada por Almacén; no compromete la compra.</small>
                    @error('requisicion_id')<small class="field-error">{{ $message }}</small>@enderror
                </div>
            </div>

            <details class="supplier-quote-optional" @if ($datosOpcionalesAbiertos) open @endif>
                <summary>
                    <span>
                        <strong>Condiciones y observaciones</strong>
                        <small>Opcional: pago, entrega e información adicional</small>
                    </span>
                    <x-ui.icon name="chevron-down" :size="18" />
                </summary>

                <div class="form-grid supplier-quote-form-grid">
                    <div class="form-field">
                        <label for="condiciones_pago">Condiciones de pago</label>
                        <textarea id="condiciones_pago" name="condiciones_pago" rows="3"
                            maxlength="500" placeholder="Contado, crédito, adelanto, cuotas...">{{ old('condiciones_pago', $cotizacion->condiciones_pago ?? '') }}</textarea>
                        @error('condiciones_pago')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field">
                        <label for="condiciones_entrega">Condiciones de entrega</label>
                        <textarea id="condiciones_entrega" name="condiciones_entrega" rows="3"
                            maxlength="500" placeholder="Plazo, recojo, despacho o disponibilidad">{{ old('condiciones_entrega', $cotizacion->condiciones_entrega ?? '') }}</textarea>
                        @error('condiciones_entrega')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field form-field--wide">
                        <label for="observacion">Observación general</label>
                        <textarea id="observacion" name="observacion" rows="3"
                            maxlength="500" placeholder="Información adicional del documento">{{ old('observacion', $cotizacion->observacion ?? '') }}</textarea>
                        @error('observacion')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                </div>
            </details>

            <div class="form-actions supplier-quote-step-actions">
                <button type="button" class="button button--ghost"
                    data-cancel-form
                    data-cancel-url="{{ $editando ? route('cotizaciones-proveedor.show', $cotizacion->id) : route('cotizaciones-proveedor.index') }}">
                    Cancelar
                </button>
                <button type="button" class="button button--primary"
                    data-next-quote-step="2">
                    Continuar a productos
                </button>
            </div>
        </section>

        <section id="paso-productos-cotizacion"
            class="panel supplier-quote-lines-panel supplier-quote-step-panel"
            data-quote-step-panel="2"
            @if ($pasoInicial !== 2) hidden @endif>
            @if ($requisicionSeleccionada)
                <div class="notice notice--info notice--block supplier-quote-requisition-context">
                    <x-ui.icon name="info" :size="18" />
                    <div>
                        <strong>{{ $requisicionSeleccionada->codigo }} · cotización parcial permitida</strong>
                        <span>Este proveedor puede cotizar uno, varios o todos los productos del requerimiento. También puede ofrecer una cantidad parcial; otras cotizaciones podrán cubrir el resto.</span>
                    </div>
                </div>
            @endif

            <div class="supplier-quote-context" aria-live="polite">
                <div>
                    <span>Proveedor</span>
                    <strong data-context-provider>Por seleccionar</strong>
                </div>
                <div>
                    <span>N.º de cotización</span>
                    <strong data-context-document>Sin número externo</strong>
                </div>
                <div>
                    <span>Moneda</span>
                    <strong data-context-currency>PEN</strong>
                </div>
            </div>

            <header class="supplier-quote-lines-heading">
                <div>
                    <p class="eyebrow">Paso 2 de 3</p>
                    <h2>Productos y precios ofrecidos</h2>
                    <p>Indica por fila cómo se presenta el IGV y solo detalla descuentos expresos.</p>
                </div>

                <div class="supplier-quote-lines-heading__actions">
                    <button type="button" class="button button--ghost button--small"
                        data-open-quick-product>
                        <x-ui.icon name="plus" :size="16" /> Registrar producto nuevo
                    </button>
                    <button type="button" class="button button--ghost button--small"
                        data-add-supplier-quote-line>
                        <x-ui.icon name="plus" :size="16" /> Agregar fila
                    </button>
                </div>
            </header>

            <div class="supplier-quote-tax-help">
                <x-ui.icon name="info" :size="19" />
                <p>
                    El IGV es 18 % tanto en PEN como en USD. Si el precio ya incluye
                    IGV, el sistema separa la base y el impuesto sin sumarlo de nuevo.
                </p>
            </div>

            <div class="supplier-quote-lines" data-supplier-quote-lines>
                @foreach ($lineasIniciales as $indice => $linea)
                    @include('cotizaciones_proveedor._linea_form', [
                        'indice' => $indice,
                        'numero' => $loop->iteration,
                        'linea' => $linea,
                    ])
                @endforeach
            </div>

            <section class="supplier-quote-global-discount">
                <div>
                    <p class="eyebrow">Descuento del documento</p>
                    <h3>¿El proveedor detalla un descuento general?</h3>
                    <p>Actívalo solo si aparece aplicado al total de la cotización.</p>
                </div>

                <div class="form-field supplier-quote-discount-question">
                    <span>Descuento general</span>
                    <label class="supplier-quote-switch">
                        <input type="checkbox" data-global-discount-switch
                            @checked($modoDescuentoGlobal === 'APLICAR')>
                        <span class="supplier-quote-switch__track" aria-hidden="true"></span>
                        <span data-global-discount-answer>
                            {{ $modoDescuentoGlobal === 'APLICAR' ? 'Sí, lo detalla' : 'No' }}
                        </span>
                    </label>
                    <input type="hidden" name="descuento_global_modo"
                        value="{{ $modoDescuentoGlobal }}" data-global-discount-mode>
                </div>

                <div class="supplier-quote-global-discount__values"
                    data-global-discount-fields
                    @if ($modoDescuentoGlobal !== 'APLICAR') hidden @endif>
                    <label class="form-field">
                        <span>Tipo de descuento</span>
                        <select name="descuento_global_tipo" data-global-discount-type>
                            <option value="">Seleccionar</option>
                            <option value="PORCENTAJE"
                                @selected(old('descuento_global_tipo', $cotizacion->descuento_global_tipo ?? '') === 'PORCENTAJE')>
                                Porcentaje
                            </option>
                            <option value="MONTO"
                                @selected(old('descuento_global_tipo', $cotizacion->descuento_global_tipo ?? '') === 'MONTO')>
                                Monto
                            </option>
                        </select>
                    </label>

                    <label class="form-field">
                        <span data-global-discount-value-label>Valor del descuento</span>
                        <input name="descuento_global_valor" type="number"
                            min="0" step="0.0001"
                            value="{{ old('descuento_global_valor', $cotizacion->descuento_global_valor ?? '') }}"
                            data-global-discount-value placeholder="Ej. 5">
                    </label>
                </div>
            </section>

            <div class="supplier-quote-totals">
                <div><span>Subtotal sin IGV</span><strong data-quote-subtotal>—</strong></div>
                <div><span>Descuento general</span><strong data-quote-discount>—</strong></div>
                <div><span>Base neta</span><strong data-quote-net-base>—</strong></div>
                <div><span>IGV</span><strong data-quote-tax-total>—</strong></div>
                <div class="supplier-quote-totals__main">
                    <span>Total</span><strong data-quote-total>—</strong>
                </div>
            </div>

            <div class="form-actions supplier-quote-step-actions">
                <button type="button" class="button button--ghost"
                    data-previous-quote-step="1">
                    Volver a datos
                </button>
                <button type="button" class="button button--primary"
                    data-next-quote-step="3">
                    Revisar cotización
                </button>
            </div>
        </section>

        <section id="paso-resumen-cotizacion"
            class="panel supplier-quote-review supplier-quote-step-panel"
            data-quote-step-panel="3" hidden>
            <header class="supplier-quote-review__heading">
                <div>
                    <p class="eyebrow">Paso 3 de 3</p>
                    <h2>Revisa antes de registrar</h2>
                    <p>Confirma que el proveedor, los productos y el total sean correctos.</p>
                </div>
                <span class="supplier-quote-review__status" data-review-status>
                    <span data-review-status-text>{{ $totalDocumento !== null ? 'Validando importes' : 'Listo para registrar' }}</span>
                </span>
            </header>

            <div class="supplier-quote-review__grid">
                <div>
                    <span>Proveedor</span>
                    <strong data-review-provider>Por seleccionar</strong>
                </div>
                <div>
                    <span>N.º de cotización</span>
                    <strong data-review-document>Sin número externo</strong>
                </div>
                <div>
                    <span>Fecha</span>
                    <strong data-review-date>—</strong>
                </div>
                <div>
                    <span>Moneda</span>
                    <strong data-review-currency>PEN</strong>
                </div>
                <div>
                    <span>Productos</span>
                    <strong data-review-products>1 producto</strong>
                </div>
                <div class="supplier-quote-review__total">
                    <span>Total final</span>
                    <strong data-review-total>—</strong>
                </div>
            </div>

            @if ($totalDocumento !== null)
                <section class="supplier-quote-reconciliation {{ ($conciliacionInicial['estado'] ?? null) === 'COINCIDE' ? 'is-match' : 'has-difference' }}"
                    data-quote-reconciliation aria-live="polite">
                    <header class="supplier-quote-reconciliation__heading">
                        <div>
                            <p class="eyebrow">Control contra el documento original</p>
                            <h3>Conciliación de importes</h3>
                        </div>
                        <span class="supplier-quote-reconciliation__status" data-reconciliation-status>
                            {{ ($conciliacionInicial['estado'] ?? null) === 'COINCIDE' ? 'Importes conciliados' : 'Requiere revisión' }}
                        </span>
                    </header>

                    <div class="supplier-quote-reconciliation__grid">
                        <div>
                            <span>Total del documento</span>
                            <strong data-document-total-output>—</strong>
                        </div>
                        <div>
                            <span>Total calculado</span>
                            <strong data-system-total-output>—</strong>
                        </div>
                        <div>
                            <span>Diferencia</span>
                            <strong data-reconciliation-difference>—</strong>
                        </div>
                        <div>
                            <span>Interpretación inicial</span>
                            <strong>{{ $conciliacionInicial['interpretacion'] ?? 'Revisar IGV y precios' }}</strong>
                        </div>
                    </div>

                    <p class="supplier-quote-reconciliation__message" data-reconciliation-message>
                        El total calculado debe coincidir con el importe declarado por el proveedor.
                    </p>
                    @error('reconciliacion_documento')
                        <p class="field-error supplier-quote-reconciliation__error">{{ $message }}</p>
                    @enderror
                </section>
            @endif

            <div class="supplier-quote-review__notice">
                <x-ui.icon name="info" :size="19" />
                <p>
                    La cotización se guardará en el historial de precios. No genera
                    entrada de inventario ni confirma una compra.
                </p>
            </div>

            <div class="form-actions supplier-quote-step-actions">
                <button type="button" class="button button--ghost"
                    data-previous-quote-step="2">
                    Volver a productos
                </button>
                <button type="submit" class="button button--primary" data-submit-supplier-quote>
                    <x-ui.icon name="check" :size="18" />
                    {{ $editando ? 'Guardar cambios' : 'Registrar cotización' }}
                </button>
            </div>
        </section>
    </div>

    <template data-supplier-quote-line-template>
        @include('cotizaciones_proveedor._linea_form', [
            'indice' => '__INDEX__',
            'numero' => '__NUMBER__',
            'linea' => [],
        ])
    </template>

    @include('cotizaciones_proveedor._producto_rapido_modal')
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const wizard = document.querySelector('[data-supplier-quote-wizard]');
    const form = wizard?.closest('form');
    const stepper = wizard?.querySelector('[data-workflow-stepper]');
    const panels = Array.from(wizard?.querySelectorAll('[data-quote-step-panel]') || []);
    const lines = wizard?.querySelector('[data-supplier-quote-lines]');
    const template = wizard?.querySelector('[data-supplier-quote-line-template]');
    const addButton = wizard?.querySelector('[data-add-supplier-quote-line]');
    const currency = wizard?.querySelector('[data-supplier-quote-currency]');
    const exchangeField = wizard?.querySelector('[data-supplier-exchange-field]');
    const exchangeInput = wizard?.querySelector('[data-supplier-exchange-input]');
    const provider = wizard?.querySelector('[data-quote-provider]');
    const providerSearch = wizard?.querySelector('#proveedor_busqueda');
    const documentNumber = wizard?.querySelector('[data-quote-document-number]');
    const quoteDate = wizard?.querySelector('[data-quote-date]');
    const globalSwitch = wizard?.querySelector('[data-global-discount-switch]');
    const globalMode = wizard?.querySelector('[data-global-discount-mode]');
    const globalFields = wizard?.querySelector('[data-global-discount-fields]');
    const globalType = wizard?.querySelector('[data-global-discount-type]');
    const globalValue = wizard?.querySelector('[data-global-discount-value]');
    const globalAnswer = wizard?.querySelector('[data-global-discount-answer]');
    const globalValueLabel = wizard?.querySelector('[data-global-discount-value-label]');
    const reconciliation = wizard?.querySelector('[data-quote-reconciliation]');
    const documentTotal = wizard.dataset.documentTotal !== undefined
        ? Number(wizard.dataset.documentTotal)
        : null;
    const documentSubtotal = wizard.dataset.documentSubtotal
        ? Number(wizard.dataset.documentSubtotal)
        : null;
    const documentTax = wizard.dataset.documentTax
        ? Number(wizard.dataset.documentTax)
        : null;
    const documentCurrency = wizard.dataset.documentCurrency || null;

    if (!wizard || !form || !lines || !template) return;

    let currentStep = Number(wizard.dataset.initialStep || 1);
    let reachableStep = currentStep;
    let nextIndex = lines.querySelectorAll('[data-supplier-quote-line]').length;
    let lastTotalText = '—';
    let reconciliationMatches = documentTotal === null;

    const symbol = () => currency?.value === 'USD' ? 'US$' : 'S/';
    const number = (value, decimals = 2) => Number(value || 0).toLocaleString(
        'es-PE',
        { minimumFractionDigits: decimals, maximumFractionDigits: decimals }
    );
    const money = (value, decimals = 2) => symbol() + ' ' + number(value, decimals);
    const documentMoney = (value, decimals = 2) =>
        (documentCurrency === 'USD' ? 'US$' : 'S/') + ' ' + number(value, decimals);
    const setText = (selector, value) => {
        wizard.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    };

    const paintWorkflow = () => {
        if (!stepper) return;

        stepper.dataset.currentStep = String(currentStep);
        stepper.querySelectorAll('[data-workflow-step]').forEach((item) => {
            const step = Number(item.dataset.workflowStep);
            const button = item.querySelector('[data-workflow-step-button]');
            const completed = step < currentStep;
            const active = step === currentStep;

            item.classList.toggle('workflow-step--completed', completed);
            item.classList.toggle('workflow-step--current', active);
            item.classList.toggle('workflow-step--pending', step > currentStep);
            button?.toggleAttribute('disabled', step > reachableStep);

            if (active) {
                button?.setAttribute('aria-current', 'step');
            } else {
                button?.removeAttribute('aria-current');
            }
        });
    };

    const updateSummary = () => {
        const providerText = provider?.value
            ? providerSearch?.value.trim()
            : 'Por seleccionar';
        const documentText = documentNumber?.value.trim() || 'Sin número externo';
        const currencyText = currency?.value === 'USD' ? 'Dólares (USD)' : 'Soles (PEN)';
        let dateText = '—';

        if (quoteDate?.value) {
            dateText = new Intl.DateTimeFormat('es-PE').format(
                new Date(quoteDate.value + 'T00:00:00')
            );
        }

        const productCount = Array.from(
            lines.querySelectorAll('[data-line-product]')
        ).filter((input) => input.value).length;

        setText('[data-context-provider], [data-review-provider]', providerText);
        setText('[data-context-document], [data-review-document]', documentText);
        setText('[data-context-currency], [data-review-currency]', currencyText);
        setText('[data-review-date]', dateText);
        setText(
            '[data-review-products]',
            productCount + (productCount === 1 ? ' producto' : ' productos')
        );
        setText('[data-review-total]', lastTotalText);
    };

    const showStep = (step, scroll = true) => {
        currentStep = Math.max(1, Math.min(3, Number(step)));
        reachableStep = Math.max(reachableStep, currentStep);

        panels.forEach((panel) => {
            panel.hidden = Number(panel.dataset.quoteStepPanel) !== currentStep;
        });

        paintWorkflow();
        updateSummary();

        if (scroll) {
            const currentPanel = panels.find(
                (panel) => Number(panel.dataset.quoteStepPanel) === currentStep
            );
            currentPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const validateStep = (step) => {
        const panel = panels.find(
            (candidate) => Number(candidate.dataset.quoteStepPanel) === step
        );
        if (!panel) return true;

        const controls = Array.from(panel.querySelectorAll(
            'input:not([type="hidden"]), select, textarea'
        ));

        for (const control of controls) {
            if (!control.checkValidity()) {
                control.reportValidity();
                control.focus({ preventScroll: false });
                return false;
            }
        }

        return true;
    };

    const refreshIndexes = () => {
        lines.querySelectorAll('[data-supplier-quote-line]').forEach((line, index) => {
            const badge = line.querySelector('.supplier-quote-line__index span');
            if (badge) badge.textContent = index + 1;
        });
    };

    const updateDiscountValueLabel = (type, label, perUnit = false) => {
        if (!label) return;

        if (type === 'PORCENTAJE') {
            label.textContent = 'Porcentaje de descuento (%)';
        } else if (type === 'MONTO') {
            label.textContent = perUnit
                ? 'Monto por unidad (' + symbol() + ')'
                : 'Monto del descuento (' + symbol() + ')';
        } else {
            label.textContent = 'Valor del descuento';
        }
    };

    const syncLineDiscount = (line) => {
        const switchControl = line.querySelector('[data-line-discount-switch]');
        const mode = line.querySelector('[data-line-discount-mode]');
        const fields = line.querySelector('[data-line-discount-fields]');
        const type = line.querySelector('[data-line-discount-type]');
        const value = line.querySelector('[data-line-discount-value]');
        const answer = line.querySelector('[data-line-discount-answer]');
        const label = line.querySelector('[data-line-discount-value-label]');
        const active = Boolean(switchControl?.checked);

        if (mode) mode.value = active ? 'APLICAR' : 'SIN_DESCUENTO';
        if (fields) fields.hidden = !active;
        if (type) type.required = active;
        if (value) value.required = active;
        if (answer) answer.textContent = active ? 'Sí, lo detalla' : 'No';

        if (!active) {
            if (type) type.value = '';
            if (value) value.value = '';
        }

        updateDiscountValueLabel(type?.value, label, true);
    };

    const lineAmounts = (line) => {
        const quantity = Number(line.querySelector('[data-line-quantity]')?.value || 0);
        const price = Number(line.querySelector('[data-line-price]')?.value || 0);
        const discountMode = line.querySelector('[data-line-discount-mode]')?.value;
        const discountType = line.querySelector('[data-line-discount-type]')?.value;
        const discountValue = Number(
            line.querySelector('[data-line-discount-value]')?.value || 0
        );
        const taxMode = line.querySelector('[data-line-tax-mode]')?.value;
        let offeredUnit = price;

        if (discountMode === 'APLICAR') {
            offeredUnit -= discountType === 'PORCENTAJE'
                ? price * (discountValue / 100)
                : discountValue;
        }

        offeredUnit = Math.max(0, offeredUnit);

        let baseUnit = offeredUnit;
        let taxUnit = 0;
        let totalUnit = offeredUnit;

        if (taxMode === 'INCLUIDO') {
            baseUnit = offeredUnit / 1.18;
            taxUnit = offeredUnit - baseUnit;
        } else if (taxMode === 'AGREGAR') {
            taxUnit = baseUnit * 0.18;
            totalUnit = baseUnit + taxUnit;
        }

        return {
            base: quantity * baseUnit,
            tax: quantity * taxUnit,
            total: quantity * totalUnit,
        };
    };

    const updateReconciliation = ({ base, tax, total }) => {
        if (!reconciliation || documentTotal === null) return;

        const roundCurrency = (value) => Math.round((Number(value) + Number.EPSILON) * 100) / 100;
        const totalDifference = Math.abs(roundCurrency(total) - roundCurrency(documentTotal));
        const baseDifference = documentSubtotal === null
            ? 0
            : Math.abs(roundCurrency(base) - roundCurrency(documentSubtotal));
        const taxDifference = documentTax === null
            ? 0
            : Math.abs(roundCurrency(tax) - roundCurrency(documentTax));
        const sameCurrency = !documentCurrency || documentCurrency === currency?.value;

        reconciliationMatches = sameCurrency
            && totalDifference <= 0.01
            && baseDifference <= 0.01
            && taxDifference <= 0.01;

        reconciliation.classList.toggle('is-match', reconciliationMatches);
        reconciliation.classList.toggle('has-difference', !reconciliationMatches);

        const status = reconciliation.querySelector('[data-reconciliation-status]');
        const message = reconciliation.querySelector('[data-reconciliation-message]');
        const difference = reconciliation.querySelector('[data-reconciliation-difference]');
        const documentOutput = reconciliation.querySelector('[data-document-total-output]');
        const systemOutput = reconciliation.querySelector('[data-system-total-output]');
        const reviewStatus = wizard.querySelector('[data-review-status]');
        const reviewStatusText = wizard.querySelector('[data-review-status-text]');

        if (status) {
            status.textContent = reconciliationMatches
                ? 'Importes conciliados'
                : 'Requiere revisión';
        }
        if (documentOutput) documentOutput.textContent = documentMoney(documentTotal);
        if (systemOutput) systemOutput.textContent = money(total);
        if (difference) difference.textContent = money(totalDifference);
        reviewStatus?.classList.toggle('has-difference', !reconciliationMatches);
        if (reviewStatusText) {
            reviewStatusText.textContent = reconciliationMatches
                ? 'Listo para registrar'
                : 'Importes pendientes';
        }

        if (message) {
            if (!sameCurrency) {
                message.textContent = 'La moneda del formulario no coincide con la moneda detectada en el documento.';
            } else if (reconciliationMatches) {
                message.textContent = 'El total, la base neta y el IGV coinciden con el documento del proveedor.';
            } else {
                message.textContent = 'Revisa el precio unitario, el IGV o los descuentos. El sistema no registrará la cotización mientras exista una diferencia.';
            }
        }

        wizard.querySelector('[data-submit-supplier-quote]')
            ?.setAttribute('aria-disabled', reconciliationMatches ? 'false' : 'true');
    };

    const calculate = () => {
        let subtotal = 0;
        let taxBeforeGlobalDiscount = 0;

        lines.querySelectorAll('[data-supplier-quote-line]').forEach((line) => {
            const amounts = lineAmounts(line);
            subtotal += amounts.base;
            taxBeforeGlobalDiscount += amounts.tax;

            const baseOutput = line.querySelector('[data-line-base]');
            const taxOutput = line.querySelector('[data-line-tax]');
            const totalOutput = line.querySelector('[data-line-total]');
            if (baseOutput) baseOutput.textContent = money(amounts.base);
            if (taxOutput) taxOutput.textContent = money(amounts.tax);
            if (totalOutput) totalOutput.textContent = money(amounts.total);
        });

        let discount = 0;
        let discountFactor = 0;

        if (globalMode?.value === 'APLICAR') {
            const value = Number(globalValue?.value || 0);
            discount = globalType?.value === 'PORCENTAJE'
                ? subtotal * (value / 100)
                : value;
            discount = Math.min(Math.max(0, discount), subtotal);
            discountFactor = subtotal > 0 ? discount / subtotal : 0;
        }

        const netBase = subtotal - discount;
        const tax = taxBeforeGlobalDiscount * (1 - discountFactor);
        const total = netBase + tax;
        lastTotalText = money(total);

        setText('[data-quote-subtotal]', money(subtotal));
        setText('[data-quote-discount]', money(discount));
        setText('[data-quote-net-base]', money(netBase));
        setText('[data-quote-tax-total]', money(tax));
        setText('[data-quote-total], [data-review-total]', lastTotalText);
        updateReconciliation({ base: netBase, tax, total });
        updateSummary();
    };

    const bindLine = (line) => {
        line.querySelectorAll('input, select').forEach((input) => {
            input.addEventListener('input', calculate);
            input.addEventListener('change', calculate);
        });

        line.querySelector('[data-line-discount-switch]')?.addEventListener(
            'change',
            () => {
                syncLineDiscount(line);
                calculate();
            }
        );

        line.querySelector('[data-line-discount-type]')?.addEventListener(
            'change',
            (event) => {
                updateDiscountValueLabel(
                    event.target.value,
                    line.querySelector('[data-line-discount-value-label]'),
                    true
                );
            }
        );

        line.querySelector('[data-remove-supplier-quote-line]')?.addEventListener(
            'click',
            () => {
                const all = lines.querySelectorAll('[data-supplier-quote-line]');

                if (all.length === 1) {
                    line.querySelectorAll('input').forEach((input) => {
                        if (input.type === 'checkbox') {
                            input.checked = false;
                        } else {
                            input.value = input.matches('[data-line-quantity]') ? 1 : '';
                        }
                    });
                    const product = line.querySelector('[data-line-product]');
                    const taxMode = line.querySelector('[data-line-tax-mode]');
                    const discountType = line.querySelector('[data-line-discount-type]');
                    if (product) product.value = '';
                    if (taxMode) taxMode.value = 'AGREGAR';
                    if (discountType) discountType.value = '';
                    const productSearch = line.querySelector('[data-product-search]');
                    const productClear = line.querySelector('[data-product-clear]');
                    if (productSearch) productSearch.dataset.selectedLabel = '';
                    if (productClear) productClear.hidden = true;
                    syncLineDiscount(line);
                } else {
                    line.remove();
                    refreshIndexes();
                }

                calculate();
            }
        );

        syncLineDiscount(line);
    };

    lines.querySelectorAll('[data-supplier-quote-line]').forEach(bindLine);

    form.addEventListener('submit', (event) => {
        if (currentStep < 3 || documentTotal === null || reconciliationMatches) return;

        event.preventDefault();
        showStep(3, false);
        reconciliation?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        reconciliation?.querySelector('[data-reconciliation-message]')?.focus?.();
    });

    addButton?.addEventListener('click', () => {
        const html = template.innerHTML
            .replaceAll('__INDEX__', nextIndex)
            .replaceAll('__NUMBER__', nextIndex + 1);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const line = wrapper.firstElementChild;
        lines.appendChild(line);
        bindLine(line);
        nextIndex += 1;
        refreshIndexes();
        calculate();
    });

    const syncCurrency = () => {
        const usd = currency?.value === 'USD';
        if (exchangeField) exchangeField.hidden = !usd;
        if (exchangeInput) {
            exchangeInput.disabled = !usd;
            exchangeInput.required = usd;
            if (!usd) exchangeInput.value = '';
        }

        lines.querySelectorAll('[data-supplier-quote-line]').forEach((line) => {
            updateDiscountValueLabel(
                line.querySelector('[data-line-discount-type]')?.value,
                line.querySelector('[data-line-discount-value-label]'),
                true
            );
        });
        updateDiscountValueLabel(globalType?.value, globalValueLabel);
        calculate();
    };

    const syncGlobalDiscount = () => {
        const active = Boolean(globalSwitch?.checked);
        if (globalMode) globalMode.value = active ? 'APLICAR' : 'SIN_DESCUENTO';
        if (globalFields) globalFields.hidden = !active;
        if (globalType) globalType.required = active;
        if (globalValue) globalValue.required = active;
        if (globalAnswer) globalAnswer.textContent = active ? 'Sí, lo detalla' : 'No';

        if (!active) {
            if (globalType) globalType.value = '';
            if (globalValue) globalValue.value = '';
        }

        updateDiscountValueLabel(globalType?.value, globalValueLabel);
        calculate();
    };

    currency?.addEventListener('change', syncCurrency);
    provider?.addEventListener('change', updateSummary);
    documentNumber?.addEventListener('input', updateSummary);
    quoteDate?.addEventListener('change', updateSummary);
    globalSwitch?.addEventListener('change', syncGlobalDiscount);
    globalType?.addEventListener('change', () => {
        updateDiscountValueLabel(globalType.value, globalValueLabel);
        calculate();
    });
    globalValue?.addEventListener('input', calculate);

    wizard.querySelectorAll('[data-next-quote-step]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!validateStep(currentStep)) return;
            showStep(Number(button.dataset.nextQuoteStep));
        });
    });

    wizard.querySelectorAll('[data-previous-quote-step]').forEach((button) => {
        button.addEventListener('click', () => {
            showStep(Number(button.dataset.previousQuoteStep));
        });
    });

    stepper?.querySelectorAll('[data-workflow-step-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = Number(button.dataset.stepNumber);
            if (target <= reachableStep && target !== currentStep) {
                showStep(target);
            }
        });
    });

    form.addEventListener('submit', (event) => {
        if (currentStep < 3) {
            event.preventDefault();
            if (validateStep(currentStep)) {
                showStep(currentStep + 1);
            }
        }
    });

    syncGlobalDiscount();
    syncCurrency();
    calculate();
    showStep(currentStep, false);
});
</script>
<script src="{{ asset('js/cotizacion-productos.js') }}"></script>
@endpush
