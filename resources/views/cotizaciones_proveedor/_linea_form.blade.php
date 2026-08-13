@php
    $modoDescuento = ($linea['descuento_modo'] ?? 'SIN_DESCUENTO') === 'APLICAR'
        ? 'APLICAR'
        : 'SIN_DESCUENTO';
    $tipoDescuento = $linea['descuento_tipo'] ?? '';
    $modoIgv = $linea['igv_modo'] ?? 'AGREGAR';
    $productoSeleccionado = $productos->firstWhere(
        'id',
        (int) ($linea['producto_id'] ?? 0)
    );
    $unidadSeleccionada = $productoSeleccionado?->unidadMedida;
    $etiquetaProducto = $productoSeleccionado
        ? $productoSeleccionado->codigo.' — '.$productoSeleccionado->descripcion
            .($unidadSeleccionada
                ? ' · '.($unidadSeleccionada->abreviatura
                    ?? $unidadSeleccionada->codigo
                    ?? $unidadSeleccionada->nombre)
                : '')
        : '';
    $tipoVinculacion = $linea['tipo_vinculacion'] ?? (
        ! empty($linea['requisicion_detalle_id']) ? 'SOLICITADO' : 'ADICIONAL'
    );
    $origenVinculacion = $linea['vinculacion_origen'] ?? (
        ! empty($linea['coincidencia_importada']) ? 'CONFIRMADA' : 'MANUAL'
    );
    $vinculacionConfirmada = filter_var(
        $linea['vinculacion_confirmada'] ?? empty($linea['coincidencia_importada']),
        FILTER_VALIDATE_BOOL
    );
    $lineasRelacionables = collect($lineasRequisicion ?? []);
@endphp

<div class="supplier-quote-line" data-supplier-quote-line
    data-imported-code="{{ $linea['codigo_importado'] ?? '' }}"
    data-imported-description="{{ $linea['descripcion_importada'] ?? '' }}">
    <div class="supplier-quote-line__index"><span>{{ $numero }}</span></div>

    <div class="supplier-quote-line__fields">
        <div class="form-field supplier-quote-line__product">
            <label for="producto_busqueda_{{ $indice }}">
                Producto <span class="required-mark">*</span>
            </label>
            <div class="supplier-product-combobox" data-product-combobox>
                <div class="supplier-product-combobox__control">
                    <x-ui.icon name="search" :size="17" />
                    <input id="producto_busqueda_{{ $indice }}" type="search"
                        value="{{ $etiquetaProducto }}" placeholder="Código o descripción"
                        autocomplete="off" required role="combobox"
                        aria-autocomplete="list" aria-expanded="false"
                        data-product-search>
                    <button type="button" class="supplier-product-combobox__clear"
                        title="Limpiar producto" aria-label="Limpiar producto"
                        data-product-clear @if (! $productoSeleccionado) hidden @endif>
                        <x-ui.icon name="close" :size="15" />
                    </button>
                </div>
                <input type="hidden" name="detalles[{{ $indice }}][producto_id]"
                    value="{{ $linea['producto_id'] ?? '' }}" data-line-product>
                <input type="hidden" name="detalles[{{ $indice }}][codigo_importado]"
                    value="{{ $linea['codigo_importado'] ?? '' }}">
                <input type="hidden" name="detalles[{{ $indice }}][descripcion_importada]"
                    value="{{ $linea['descripcion_importada'] ?? '' }}">
                <input type="hidden" name="detalles[{{ $indice }}][coincidencia_importada]"
                    value="{{ $linea['coincidencia_importada'] ?? '' }}">
                <div class="supplier-product-combobox__results" role="listbox"
                    data-product-results hidden></div>
            </div>
            <small>Escribe el código o parte de la descripción y selecciona un resultado.</small>
            @if (! empty($linea['descripcion_importada']) || ! empty($linea['codigo_importado']))
                <small class="supplier-quote-imported-source">
                    <span>Documento: {{ collect([$linea['codigo_importado'] ?? null, $linea['descripcion_importada'] ?? null])->filter()->implode(' — ') }}</span>
                    @if (! empty($linea['coincidencia_importada']))
                        <span class="badge badge--{{ in_array($linea['coincidencia_importada'], ['EXACTA', 'EXACTA_CATALOGO', 'REQUERIMIENTO'], true) ? 'success' : ($linea['coincidencia_importada'] === 'SUGERIDA' ? 'warning' : 'danger') }}">
                            {{ match ($linea['coincidencia_importada']) {
                                'EXACTA', 'EXACTA_CATALOGO', 'REQUERIMIENTO' => 'Detectado con seguridad',
                                'SUGERIDA' => 'Coincidencia sugerida',
                                default => 'Requiere revisión',
                            } }}
                        </span>
                    @endif
                </small>
            @endif
            @if (! empty($linea['requisicion_detalle_id']))
                <small class="supplier-quote-requisition-line-note">
                    Línea vinculada al requerimiento · solicitado: <x-ui.quantity :value="$linea['cantidad_requerida'] ?? $linea['cantidad'] ?? 0" />
                </small>
            @endif
            @error("detalles.{$indice}.producto_id")
                <small class="field-error" role="alert">{{ $message }}</small>
            @enderror
        </div>

        <section class="supplier-product-linking"
            data-product-linking @if (! $requisicionSeleccionada) hidden @endif>
            <header class="supplier-product-linking__heading">
                <div>
                    <span>Relación con el requerimiento</span>
                    <small>El requerimiento original no se modifica.</small>
                </div>
                <span class="badge badge--neutral" data-linking-status>
                    {{ $tipoVinculacion === 'ALTERNATIVA' ? 'Alternativa' : ($tipoVinculacion === 'SOLICITADO' ? 'Solicitado' : 'Adicional') }}
                </span>
            </header>

            <div class="supplier-product-linking__fields">
                <label class="form-field">
                    <span>Tipo de relación</span>
                    <select name="detalles[{{ $indice }}][tipo_vinculacion]"
                        data-line-link-type>
                        <option value="SOLICITADO" @selected($tipoVinculacion === 'SOLICITADO')>
                            Producto solicitado
                        </option>
                        <option value="ALTERNATIVA" @selected($tipoVinculacion === 'ALTERNATIVA')>
                            Alternativa ofrecida
                        </option>
                        <option value="ADICIONAL" @selected($tipoVinculacion === 'ADICIONAL')>
                            Producto adicional
                        </option>
                    </select>
                </label>

                <label class="form-field" data-line-requested-field>
                    <span>Producto solicitado relacionado</span>
                    <select name="detalles[{{ $indice }}][requisicion_detalle_id]"
                        data-line-requisition-detail>
                        <option value="">Seleccionar línea del requerimiento</option>
                        @foreach ($lineasRelacionables as $lineaRequerida)
                            <option value="{{ $lineaRequerida['requisicion_detalle_id'] }}"
                                data-product-id="{{ $lineaRequerida['producto_id'] }}"
                                @selected((int) ($linea['requisicion_detalle_id'] ?? 0) === (int) $lineaRequerida['requisicion_detalle_id'])>
                                {{ $lineaRequerida['producto_codigo'] ?? $productos->firstWhere('id', (int) $lineaRequerida['producto_id'])?->codigo ?? ('Producto '.$lineaRequerida['producto_id']) }}
                                — solicitado: {{ rtrim(rtrim(number_format((float) ($lineaRequerida['cantidad_requerida'] ?? $lineaRequerida['cantidad'] ?? 0), 2, '.', ''), '0'), '.') }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            <input type="hidden" name="detalles[{{ $indice }}][vinculacion_origen]"
                value="{{ $origenVinculacion }}" data-line-link-origin>
            <input type="hidden" name="detalles[{{ $indice }}][vinculacion_confirmada]"
                value="{{ $vinculacionConfirmada ? 1 : 0 }}" data-line-link-confirmed>

            <div class="supplier-product-linking__suggestions"
                data-linking-suggestions hidden></div>
            <p class="supplier-product-linking__message" data-linking-message>
                @if ($tipoVinculacion === 'ALTERNATIVA')
                    Esta oferta reemplaza únicamente la línea indicada para efectos de comparación.
                @elseif ($tipoVinculacion === 'SOLICITADO')
                    El producto ofrecido coincide con el solicitado.
                @else
                    Este producto forma parte de la oferta, pero no cubre una línea del requerimiento.
                @endif
            </p>

            @error("detalles.{$indice}.tipo_vinculacion")
                <small class="field-error" role="alert">{{ $message }}</small>
            @enderror
            @error("detalles.{$indice}.requisicion_detalle_id")
                <small class="field-error" role="alert">{{ $message }}</small>
            @enderror
        </section>

        <label class="form-field">
            <span>Cantidad</span>
            <input name="detalles[{{ $indice }}][cantidad]" type="number"
                min="0.001" step="0.001" value="{{ $linea['cantidad'] ?? 1 }}"
                required data-line-quantity>
        </label>

        <label class="form-field">
            <span>Precio informado</span>
            <input name="detalles[{{ $indice }}][precio_unitario]" type="number"
                min="0" step="0.0001" value="{{ $linea['precio_unitario'] ?? '' }}"
                required data-line-price>
        </label>

        <label class="form-field">
            <span>Tratamiento del IGV</span>
            <select name="detalles[{{ $indice }}][igv_modo]" required data-line-tax-mode>
                <option value="" @selected($modoIgv === '')>Seleccionar según documento</option>
                <option value="INCLUIDO" @selected($modoIgv === 'INCLUIDO')>
                    El precio ya incluye IGV
                </option>
                <option value="AGREGAR" @selected($modoIgv === 'AGREGAR')>
                    Agregar IGV al precio
                </option>
                <option value="NO_APLICA" @selected($modoIgv === 'NO_APLICA')>
                    No aplica IGV
                </option>
            </select>
        </label>

        <div class="form-field supplier-quote-discount-question">
            <span>¿El proveedor detalla un descuento?</span>
            <label class="supplier-quote-switch">
                <input type="checkbox" data-line-discount-switch
                    @checked($modoDescuento === 'APLICAR')>
                <span class="supplier-quote-switch__track" aria-hidden="true"></span>
                <span data-line-discount-answer>
                    {{ $modoDescuento === 'APLICAR' ? 'Sí, lo detalla' : 'No' }}
                </span>
            </label>
            <input type="hidden" name="detalles[{{ $indice }}][descuento_modo]"
                value="{{ $modoDescuento }}" data-line-discount-mode>
        </div>

        <div class="supplier-quote-line__discount-fields"
            data-line-discount-fields @if ($modoDescuento !== 'APLICAR') hidden @endif>
            <label class="form-field">
                <span>Tipo de descuento</span>
                <select name="detalles[{{ $indice }}][descuento_tipo]" data-line-discount-type>
                    <option value="">Seleccionar</option>
                    <option value="PORCENTAJE" @selected($tipoDescuento === 'PORCENTAJE')>
                        Porcentaje
                    </option>
                    <option value="MONTO" @selected($tipoDescuento === 'MONTO')>
                        Monto por unidad
                    </option>
                </select>
            </label>

            <label class="form-field">
                <span data-line-discount-value-label>Valor del descuento</span>
                <input name="detalles[{{ $indice }}][descuento_valor]" type="number"
                    min="0" step="0.0001" value="{{ $linea['descuento_valor'] ?? '' }}"
                    data-line-discount-value placeholder="Ej. 10">
            </label>

            <small data-line-discount-help>
                El descuento indicado se aplicará antes de calcular el IGV.
            </small>
        </div>

        <label class="form-field">
            <span>Marca ofrecida</span>
            <input name="detalles[{{ $indice }}][marca_ofertada]" type="text"
                maxlength="120" value="{{ $linea['marca_ofertada'] ?? '' }}"
                placeholder="Opcional">
        </label>

        <label class="form-field supplier-quote-line__observation">
            <span>Observación</span>
            <input name="detalles[{{ $indice }}][observacion]" type="text"
                maxlength="300" value="{{ $linea['observacion'] ?? '' }}"
                placeholder="Presentación, plazo o detalle">
        </label>

        <div class="supplier-quote-line__calculation">
            <div><span>Base sin IGV</span><strong data-line-base>—</strong></div>
            <div><span>IGV</span><strong data-line-tax>—</strong></div>
            <div class="supplier-quote-line__calculation-main">
                <span>Total línea</span><strong data-line-total>—</strong>
            </div>
        </div>
    </div>

    <button type="button" class="icon-button icon-button--danger"
        title="Quitar producto" data-remove-supplier-quote-line>
        <x-ui.icon name="close" :size="17" />
    </button>
</div>
