<section class="panel entry-detail-card entry-truth-panel">
    <div class="panel-heading panel-heading--split">
        <div>
            <p class="eyebrow">Verdad física</p>
            <h2>{{ $nota->motivo_ingreso === 'DEVOLUCION_MATERIAL_MALOGRADO' ? 'Material malogrado registrado' : 'Productos ingresados' }}</h2>
            <p>Estas cantidades y ubicaciones son las que afectaron físicamente el inventario.</p>
        </div>
        <a href="{{ route('inventario.index') }}" class="button button--ghost button--small"><x-ui.icon name="inventory" :size="16" /> Ver inventario</a>
    </div>

    <div class="entry-truth-summary" aria-label="Resumen físico del ingreso">
        <div><span>Productos distintos</span><strong>{{ $productosDistintos }}</strong></div>
        <div>
            <span>Cantidad recibida:</span>
            <strong>
                @if ($puedeTotalizarCantidad)
                    <x-ui.quantity :value="$nota->detalles->sum('cantidad')" /> {{ $unidadResumen }}
                @else
                    Ver detalle
                @endif
            </strong>
        </div>
        <div>
            <span>{{ $nota->motivo_ingreso === 'DEVOLUCION_MATERIAL_MALOGRADO' ? 'Valor de la pérdida registrada' : 'Valor de ingreso' }}</span>
            <strong>S/ {{ number_format((float) $nota->detalles->sum('subtotal'), 2, '.', ',') }}</strong>
        </div>
    </div>

    <div class="table-wrap table-wrap--responsive">
        <table class="data-table data-table--responsive entry-detail-table entry-detail-table--compact">
            <thead>
                <tr><th>Producto</th><th>Cantidad</th><th>Ubicación</th><th>Valorización</th><th>Referencia física</th></tr>
            </thead>
            <tbody>
                @foreach ($nota->detalles as $detalle)
                    <tr>
                        <td data-label="Producto"><a href="{{ route('productos.show', $detalle->producto_id) }}" class="table-primary-link">{{ $detalle->producto?->codigo }}</a><span>{{ $detalle->producto?->descripcion }}</span></td>
                        <td data-label="Cantidad">
                            <strong><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->codigo }}</strong>
                            @if ($detalle->cantidad_presentacion !== null && $detalle->presentacion_nombre)
                                <small>Recibido como <x-ui.quantity :value="$detalle->cantidad_presentacion" /> {{ $detalle->presentacion_nombre }} · factor <x-ui.quantity :value="$detalle->factor_conversion" /></small>
                            @endif
                        </td>
                        <td data-label="Ubicación"><span class="location-chip"><x-ui.icon name="shelf" :size="14" /> {{ $detalle->repisa?->codigo }}</span></td>
                        <td data-label="Valorización"><span>S/ {{ number_format((float) $detalle->costo_unitario, 2, '.', ',') }} c/u</span><strong>S/ {{ number_format((float) $detalle->subtotal, 2, '.', ',') }}</strong></td>
                        <td data-label="Referencia física">
                            @if (! $detalle->afecta_stock)<span class="badge badge--warning">No ingresó al stock</span>@endif
                            <span>
                                @if ($detalle->notaSalidaDetalle)
                                    Retorno de salida #{{ $detalle->notaSalidaDetalle->nota_salida_id }}
                                @elseif ($detalle->proformaDetalle)
                                    Reposición de {{ $detalle->proformaDetalle->codigo_producto }}
                                @else
                                    Recepción de compra
                                @endif
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
