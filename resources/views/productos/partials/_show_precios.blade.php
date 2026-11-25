<section class="panel product-detail-section">
    <header class="panel__header">
        <div><p class="eyebrow">Compras</p><h2>Precios recientes de proveedores</h2><p>Referencia histórica; no modifica el costo actual del inventario.</p></div>
        @if ($puedeVerPrecios)
            <a href="{{ route('historial-precios.index', ['producto_id' => $producto->id_producto]) }}" class="text-link">Ver historial completo</a>
        @endif
    </header>

    @if (! $puedeVerPrecios)
        <div class="product-detail-restricted">
            <x-ui.icon name="lock" :size="24" />
            <div><strong>Información restringida</strong><span>El historial de precios está disponible para usuarios con permiso de Compras.</span></div>
        </div>
    @elseif ($precios->isNotEmpty())
        <div class="table-wrap table-wrap--cards">
            <table class="data-table product-detail-table data-table--cards" role="table">
                <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Fecha</th><th role="columnheader" scope="col">Proveedor</th><th role="columnheader" scope="col">Moneda</th><th role="columnheader" scope="col" class="text-right">Precio final</th><th role="columnheader" scope="col" class="text-right">Equiv. PEN</th><th role="columnheader" scope="col">Cotización</th></tr></thead>
                <tbody role="rowgroup">
                    @foreach ($precios as $precio)
                        @php($equivalente = $precio->precioEquivalentePen())
                        <tr role="row">
                            <td role="cell" data-label="Fecha">{{ $precio->cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                            <td role="cell" class="table-card__wide" data-label="Proveedor">{{ $precio->cotizacion->proveedor->nombreVisible() }}</td>
                            <td role="cell" data-label="Moneda"><span class="currency-chip">{{ $precio->cotizacion->moneda }}</span></td>
                            <td role="cell" class="text-right" data-label="Precio final"><strong>{{ $precio->cotizacion->simboloMoneda() }} {{ number_format($precio->precioFinalUnitario(), 2) }}</strong></td>
                            <td role="cell" class="text-right" data-label="Equiv. PEN">{{ $equivalente !== null ? 'S/ '.number_format($equivalente, 2) : '—' }}</td>
                            <td role="cell" class="table-card__wide" data-label="Cotización"><a href="{{ route('cotizaciones-proveedor.show', $precio->cotizacion_id) }}" class="table-primary-link">{{ $precio->cotizacion->codigo }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="product-detail-empty"><strong>Sin precios registrados</strong><span>Las ofertas aparecerán al registrar cotizaciones de proveedor.</span></div>
    @endif
</section>

@if ($puedeVerPrecios && $referenciasCosteo->isNotEmpty())
    <section class="panel product-detail-section" aria-label="Costos de referencia del Excel">
        <header class="panel__header">
            <div>
                <p class="eyebrow">Costeo importado</p>
                <h2>Referencias del Excel Volvo</h2>
                <p>Estimaciones del ingeniero en soles; no son compras, stock ni cotizaciones confirmadas del proveedor.</p>
            </div>
        </header>
        <div class="table-wrap table-wrap--cards">
            <table class="data-table product-detail-table data-table--cards" role="table">
                <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Fila</th><th role="columnheader" scope="col">Proveedor indicado</th><th role="columnheader" scope="col" class="text-right">Costo unit. PEN</th><th role="columnheader" scope="col" class="text-right">Margen del Excel</th><th role="columnheader" scope="col">Revisión</th></tr></thead>
                <tbody role="rowgroup">
                    @foreach ($referenciasCosteo as $referencia)
                        <tr role="row">
                            <td role="cell" data-label="Fila">{{ $referencia->fila_excel }}</td>
                            <td role="cell" class="table-card__wide" data-label="Proveedor indicado">{{ $referencia->proveedor_nombre ?: 'No identificado en el Excel' }}</td>
                            <td role="cell" class="text-right" data-label="Costo unit. PEN">S/ {{ rtrim(rtrim(number_format((float) $referencia->costo_unitario_pen, 6, '.', ','), '0'), '.') }}</td>
                            <td role="cell" class="text-right" data-label="Margen del Excel">{{ number_format((float) $referencia->margen_porcentaje, 2) }} %</td>
                            <td role="cell" data-label="Revisión">{{ $referencia->observacion ?: 'Dato de referencia' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
