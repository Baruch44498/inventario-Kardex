        @php
            $observacionesDeLineas = collect($lineasIniciales)
                ->map(fn ($linea) => trim((string) ($linea['observacion'] ?? '')));
            $observacionComun = $requisicionSeleccionada && $observacionesDeLineas->count() > 1
                && $observacionesDeLineas->every(fn ($valor) => $valor !== '')
                && $observacionesDeLineas->unique()->count() === 1
                    ? $observacionesDeLineas->first()
                    : null;
            $lineasConPrecio = collect($lineasIniciales)
                ->filter(fn ($linea) => trim((string) ($linea['precio_unitario'] ?? '')) !== '')
                ->count();
        @endphp
        <section id="paso-productos-cotizacion"
            class="panel supplier-quote-lines-panel supplier-quote-step-panel"
            data-quote-step-panel="2"
            @if ($pasoInicial !== 2) hidden @endif>
            @if ($requisicionSeleccionada)
                <x-ui.collapsible-notice class="supplier-quote-requisition-context"
                    label="Ver información de cotización parcial"
                    title="{{ $requisicionSeleccionada->codigo }} · cotización parcial permitida">
                    El proveedor puede cotizar una parte de los productos o de las cantidades solicitadas.
                </x-ui.collapsible-notice>
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

            <x-ui.collapsible-notice class="supplier-quote-tax-help"
                label="Ver tratamiento del IGV" title="IGV en la oferta">
                    El IGV es 18 % tanto en PEN como en USD. Si el precio ya incluye
                    IGV, el sistema separa la base y el impuesto sin sumarlo de nuevo.
            </x-ui.collapsible-notice>

            @if ($observacionComun)
                <p class="supplier-quote-shared-observation"><strong>Observación del requerimiento:</strong> {{ $observacionComun }}</p>
            @endif

            <div class="supplier-quote-entry-summary" data-quote-entry-summary hidden>
                <span data-quote-entry-label>Ingreso manual</span>
                <button type="button" class="button button--ghost button--small" data-quote-change-entry>Cambiar método</button>
            </div>

            <div class="supplier-quote-line-toolbar">
                <strong data-quote-priced-count>{{ $lineasConPrecio }} de {{ count($lineasIniciales) }} productos con precio</strong>
                <label class="supplier-quote-line-toolbar__filter">
                    <input type="checkbox" data-quote-unpriced-filter disabled> Solo sin precio
                </label>
                <details class="supplier-quote-bulk-actions">
                    <summary>Acciones para todas las líneas</summary>
                    <div>
                        <label class="form-field"><span>Tratamiento del IGV</span>
                            <select data-quote-bulk-tax>
                                <option value="INCLUIDO">El precio ya incluye IGV</option>
                                <option value="AGREGAR">Agregar IGV al precio</option>
                                <option value="NO_APLICA">No aplica IGV</option>
                            </select>
                        </label>
                        <button type="button" class="button button--ghost" data-quote-apply-tax disabled>Aplicar IGV a todas</button>
                        <button type="button" class="button button--ghost" data-quote-use-requested disabled>Usar la cantidad solicitada en todas las líneas vinculadas</button>
                    </div>
                </details>
            </div>
            <p class="supplier-quote-paste-status" role="status" data-quote-paste-status></p>

            <div class="supplier-quote-lines" data-supplier-quote-lines role="table" aria-label="Productos y precios ofrecidos">
                <div class="supplier-quote-lines__header" role="row">
                    <span role="columnheader">#</span><span role="columnheader">Producto</span>
                    <span role="columnheader">Cantidad</span><span role="columnheader">Precio</span>
                    <span role="columnheader">IGV</span><span role="columnheader">Total línea</span>
                    <span role="columnheader">Opciones</span>
                </div>
                @foreach ($lineasIniciales as $indice => $linea)
                    @include('cotizaciones_proveedor._linea_form', [
                        'indice' => $indice,
                        'numero' => $loop->iteration,
                        'linea' => $linea,
                    ])
                @endforeach
            </div>

            <p class="supplier-quote-lines__empty" data-quote-unpriced-empty hidden>Todas las líneas tienen precio.</p>

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

            <div class="supplier-quote-sticky-summary">
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
            </div>
        </section>
