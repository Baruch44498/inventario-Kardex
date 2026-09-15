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
