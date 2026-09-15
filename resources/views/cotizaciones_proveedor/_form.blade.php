@php
    $editando = isset($cotizacion);

    $lineasIniciales = old(
        'detalles',
        $editando
            ? $cotizacion->detalles->map(fn ($detalle) => [
                'requisicion_detalle_id' => $detalle->requisicion_detalle_id,
                'producto_id' => $detalle->producto_id,
                'producto_presentacion_id' => $detalle->producto_presentacion_id,
                'tipo_vinculacion' => $detalle->tipoVinculacionEfectivo(),
                'vinculacion_origen' => $detalle->vinculacion_origen ?: 'LEGADO',
                'vinculacion_confirmada' => true,
                'codigo_importado' => $detalle->codigo_documento,
                'descripcion_importada' => $detalle->descripcion_documento,
                'cantidad' => $detalle->cantidad_presentacion ?? $detalle->cantidad,
                'precio_unitario' => $detalle->precio_presentacion ?? $detalle->precio_unitario,
                'descuento_modo' => $detalle->descuento_modo,
                'descuento_tipo' => $detalle->descuento_tipo,
                'descuento_valor' => $detalle->descuento_tipo === 'MONTO'
                    ? round((float) $detalle->descuento_valor * (float) ($detalle->factor_conversion ?: 1), 4)
                    : $detalle->descuento_valor,
                'igv_modo' => $detalle->igv_modo,
                'marca_ofertada' => $detalle->marca_ofertada,
                'observacion' => $detalle->observacion,
            ])->values()->all()
            : (($lineasRequisicion ?? []) !== []
                ? $lineasRequisicion
                : [[
                    'requisicion_detalle_id' => null,
                    'producto_id' => '',
                    'tipo_vinculacion' => $requisicionSeleccionada ? 'ADICIONAL' : null,
                    'vinculacion_origen' => 'MANUAL',
                    'vinculacion_confirmada' => true,
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
        || $errors->has('ajuste_redondeo_confirmado')
        ? 3
        : ($errors->any() && $erroresPasoProductos ? 2 : 1);
    $cabeceraImportada = isset($importacionAsistida) && $importacionAsistida
        ? data_get($importacionAsistida->datos_extraidos, 'cabecera', [])
        : ($cabeceraDocumentoGuardado ?? []);
    $importesDocumento = data_get($cabeceraImportada, 'importes_documento', []);
    $conciliacionInicial = data_get($cabeceraImportada, 'conciliacion', []);
    $totalDocumento = is_numeric($importesDocumento['total'] ?? null)
        ? (float) $importesDocumento['total']
        : null;
    $ajusteConfirmadoInicial = filter_var(
        old(
            'ajuste_redondeo_confirmado',
            $editando && $cotizacion->tieneAjusteRedondeo()
        ),
        FILTER_VALIDATE_BOOL
    );
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
    data-product-linking-url="{{ route('cotizaciones-proveedor.productos.vinculacion') }}"
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
        @include('cotizaciones_proveedor.partials._form_paso_documento')
        @include('cotizaciones_proveedor.partials._form_paso_productos')
        @include('cotizaciones_proveedor.partials._form_paso_revision')
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
<script src="{{ asset('js/cotizacion-proveedor-wizard.js') }}" defer></script>
<script src="{{ asset('js/cotizacion-productos.js') }}" defer></script>
@endpush
