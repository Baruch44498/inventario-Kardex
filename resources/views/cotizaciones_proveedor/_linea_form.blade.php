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
    $presentacionesProducto = $productoSeleccionado?->presentaciones?->where('estado', true)
        ?? collect();
    $presentacionSeleccionadaId = (int) ($linea['producto_presentacion_id'] ?? 0);
    $cantidadPresentacion = $linea['cantidad'] ?? 1;
    $precioPresentacion = $linea['precio_unitario'] ?? '';
    $camposEnOpciones = [
        'tipo_vinculacion', 'requisicion_detalle_id', 'vinculacion_origen',
        'vinculacion_confirmada', 'producto_presentacion_id', 'descuento_modo',
        'descuento_tipo', 'descuento_valor', 'marca_ofertada', 'observacion',
    ];
    $errorEnOpciones = collect($camposEnOpciones)->contains(
        fn ($campo) => $errors->has("detalles.{$indice}.{$campo}")
    );
    $opcionesIniciales = $errorEnOpciones || ! $vinculacionConfirmada
        || ($requisicionSeleccionada && $tipoVinculacion !== 'ADICIONAL'
            && empty($linea['requisicion_detalle_id']));
@endphp

<div class="supplier-quote-line" data-supplier-quote-line role="rowgroup"
    data-initial-options="{{ $opcionesIniciales ? '1' : '0' }}"
    data-imported-code="{{ $linea['codigo_importado'] ?? '' }}"
    data-imported-description="{{ $linea['descripcion_importada'] ?? '' }}">
    <div class="supplier-quote-line__fields" role="row">
        <div class="supplier-quote-line__index" role="cell" data-label="#"><span>{{ $numero }}</span></div>
        <div class="form-field supplier-quote-line__product" role="cell" data-label="Producto">
            <div class="supplier-quote-line__fixed-product" data-line-fixed-product hidden>
                <div class="supplier-quote-line__product-identity">
                    <strong data-line-fixed-label>{{ $productoSeleccionado?->codigo }} — {{ $productoSeleccionado?->descripcion }}</strong>
                    <button type="button" class="button button--ghost button--small" data-line-change-product>Cambiar producto</button>
                </div>
                <small data-line-fixed-requested>Solicitado: <x-ui.quantity :value="$linea['cantidad_requerida'] ?? $linea['cantidad'] ?? 0" /> {{ $unidadSeleccionada?->codigo ?? 'UND' }}</small>
            </div>
            <div data-line-product-editor>
            <label class="supplier-quote-line__field-name" for="producto_busqueda_{{ $indice }}">
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
            </div>
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
            <div class="supplier-quote-line__badges" data-line-badges aria-label="Condiciones de la línea">
                <span class="badge badge--warning" data-line-alternative @if ($tipoVinculacion !== 'ALTERNATIVA' || empty($linea['requisicion_detalle_id'])) hidden @endif>Alternativa</span>
                <span class="badge badge--info" data-line-discount-badge @if ($modoDescuento !== 'APLICAR' || ! in_array($tipoDescuento, ['PORCENTAJE', 'MONTO'], true) || (float) ($linea['descuento_valor'] ?? 0) <= 0) hidden @endif>Con descuento</span>
                <span class="badge badge--neutral" data-line-presentation-badge @if (! $presentacionSeleccionadaId) hidden @endif>Presentación: {{ $presentacionesProducto->firstWhere('id', $presentacionSeleccionadaId)?->nombre }}</span>
                <span class="supplier-quote-line__meta" data-line-brand-summary @if (empty($linea['marca_ofertada'])) hidden @endif>Marca: {{ $linea['marca_ofertada'] ?? '' }}</span>
                <span class="supplier-quote-line__meta" data-line-note-summary @if (empty($linea['observacion']) || ($observacionComun ?? null) === ($linea['observacion'] ?? null)) hidden @endif>Con observación</span>
            </div>
            @error("detalles.{$indice}.producto_id")
                <small class="field-error" role="alert">{{ $message }}</small>
            @enderror
        </div>

        <div class="supplier-quote-line__quantity" role="cell" data-label="Cantidad">
        <label class="form-field">
            <span class="supplier-quote-line__field-name">Cantidad informada</span>
            <input name="detalles[{{ $indice }}][cantidad]" type="number"
                min="0.01" step="0.01" value="{{ $cantidadPresentacion }}"
                required data-line-quantity>
            <small data-line-base-preview></small>
            @error("detalles.{$indice}.cantidad")<small class="field-error" role="alert">{{ $message }}</small>@enderror
        </label>
        </div>

        <div class="supplier-quote-line__price" role="cell" data-label="Precio">
        <label class="form-field">
            <span class="supplier-quote-line__field-name">Precio por presentación</span>
            <input name="detalles[{{ $indice }}][precio_unitario]" type="number"
                min="0" step="0.0001" value="{{ $precioPresentacion }}"
                required data-line-price>
            @error("detalles.{$indice}.precio_unitario")<small class="field-error" role="alert">{{ $message }}</small>@enderror
        </label>
        </div>

        <div class="supplier-quote-line__tax" role="cell" data-label="IGV">
        <label class="form-field">
            <span class="supplier-quote-line__field-name">Tratamiento del IGV</span>
            <select name="detalles[{{ $indice }}][igv_modo]" required data-line-tax-mode>
                <option value="" @selected($modoIgv === '')>Seleccionar según documento</option>
                <option value="INCLUIDO" @selected($modoIgv === 'INCLUIDO')>
                    IGV incluido
                </option>
                <option value="AGREGAR" @selected($modoIgv === 'AGREGAR')>
                    Agregar IGV
                </option>
                <option value="NO_APLICA" @selected($modoIgv === 'NO_APLICA')>
                    Sin IGV
                </option>
            </select>
            @error("detalles.{$indice}.igv_modo")<small class="field-error" role="alert">{{ $message }}</small>@enderror
        </label>
        </div>

        <div class="supplier-quote-line__calculation" role="cell" data-label="Total línea">
            <strong data-line-total>—</strong>
            <small>Base <span data-line-base>—</span> · IGV <span data-line-tax>—</span></small>
        </div>
        <div class="supplier-quote-line__actions" role="cell" data-label="Opciones">
            <button type="button" class="button button--ghost button--small" data-line-options-toggle
                aria-expanded="true" aria-controls="linea-opciones-{{ $indice }}"
                aria-label="Ocultar detalles del producto {{ $numero }}">Ocultar detalles</button>
            <button type="button" class="icon-button icon-button--danger"
                title="Quitar producto" aria-label="Quitar producto {{ $numero }}" data-remove-supplier-quote-line>
                <x-ui.icon name="close" :size="17" />
            </button>
        </div>
    </div>
    <div id="linea-opciones-{{ $indice }}" class="supplier-quote-line__options" role="row" data-line-options>
        <div class="supplier-quote-line__options-content" role="cell" aria-colspan="7">
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
                                data-requested-quantity="{{ $lineaRequerida['cantidad_requerida'] ?? $lineaRequerida['cantidad'] ?? 0 }}"
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
            <span>Presentación</span>
            <select name="detalles[{{ $indice }}][producto_presentacion_id]"
                data-line-presentation data-base-unit="{{ $unidadSeleccionada?->codigo ?? 'UND' }}">
                <option value="" data-factor="1">Unidad base ({{ $unidadSeleccionada?->codigo ?? 'UND' }})</option>
                @foreach ($presentacionesProducto as $presentacion)
                    <option value="{{ $presentacion->id }}"
                        data-factor="{{ (float) $presentacion->factor_conversion }}"
                        data-name="{{ $presentacion->nombre }}"
                        @selected($presentacionSeleccionadaId === (int) $presentacion->id)>
                        {{ $presentacion->nombre }} · 1 = <x-ui.quantity :value="$presentacion->factor_conversion" /> {{ $unidadSeleccionada?->codigo ?? 'UND' }}
                    </option>
                @endforeach
            </select>
            <small data-line-presentation-help>La cantidad se convertirá a la unidad base.</small>
            @error("detalles.{$indice}.producto_presentacion_id")
                <small class="field-error" role="alert">{{ $message }}</small>
            @enderror
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
                @error("detalles.{$indice}.descuento_tipo")<small class="field-error" role="alert">{{ $message }}</small>@enderror
            </label>

            <label class="form-field">
                <span data-line-discount-value-label>Valor del descuento</span>
                <input name="detalles[{{ $indice }}][descuento_valor]" type="number"
                    min="0" step="0.0001" value="{{ $linea['descuento_valor'] ?? '' }}"
                    data-line-discount-value placeholder="Ej. 10">
                @error("detalles.{$indice}.descuento_valor")<small class="field-error" role="alert">{{ $message }}</small>@enderror
            </label>

            <small data-line-discount-help>
                El descuento indicado se aplicará antes de calcular el IGV.
            </small>
        </div>

        <label class="form-field">
            <span>Marca ofrecida</span>
            <input name="detalles[{{ $indice }}][marca_ofertada]" type="text"
                maxlength="120" value="{{ $linea['marca_ofertada'] ?? '' }}"
                placeholder="Opcional" data-line-brand>
            @error("detalles.{$indice}.marca_ofertada")<small class="field-error" role="alert">{{ $message }}</small>@enderror
        </label>

        <label class="form-field supplier-quote-line__observation">
            <span>Observación</span>
            <input name="detalles[{{ $indice }}][observacion]" type="text"
                maxlength="300" value="{{ $linea['observacion'] ?? '' }}"
                placeholder="Presentación, plazo o detalle" data-line-observation>
            @error("detalles.{$indice}.observacion")<small class="field-error" role="alert">{{ $message }}</small>@enderror
        </label>

        </div>
    </div>
</div>
