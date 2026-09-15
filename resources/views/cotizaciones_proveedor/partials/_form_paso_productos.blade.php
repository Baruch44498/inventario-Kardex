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
