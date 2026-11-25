<section class="panel supplier-invoice-lines-panel">
    <div class="panel-heading"><h2>Detalle facturado</h2><p>Importes según la Orden de Compra autorizada.</p></div>
    <div class="table-wrap table-wrap--wide table-wrap--cards">
        <table class="data-table supplier-invoice-detail-table data-table--cards" role="table">
            <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Producto</th><th role="columnheader" scope="col">Recepción conciliada</th><th role="columnheader" scope="col" class="text-right">Cantidad</th><th role="columnheader" scope="col" class="text-right">Base unitaria</th><th role="columnheader" scope="col" class="text-right">Base</th><th role="columnheader" scope="col" class="text-right">IGV</th><th role="columnheader" scope="col" class="text-right">Total con IGV</th></tr></thead>
            <tbody role="rowgroup">
                @foreach ($factura->detalles as $detalle)
                    <tr role="row">
                        <td role="cell" class="table-card__wide" data-label="Producto"><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->descripcion }}</span></td>
                        <td role="cell" data-label="Recepción conciliada">
                            @if ($detalle->notaIngresoDetalle?->notaIngreso)
                                <a href="{{ route('notas-ingreso.show', $detalle->notaIngresoDetalle->notaIngreso) }}"><strong>{{ $detalle->notaIngresoDetalle->notaIngreso->codigo }}</strong><span>{{ $detalle->notaIngresoDetalle->notaIngreso->fecha_ingreso?->format('d/m/Y') }}</span></a>
                            @elseif ($factura->notasIngreso->isNotEmpty())
                                <span>Nota de Ingreso vinculada a la factura</span>
                            @else
                                <span>Pendiente de recepción</span>
                            @endif
                        </td>
                        <td role="cell" class="text-right" data-label="Cantidad"><x-ui.quantity :value="$detalle->cantidad" /></td>
                        <td role="cell" class="text-right" data-label="Base unitaria"><x-ui.money :value="$detalle->precio_unitario" :currency="$factura->moneda" /></td>
                        <td role="cell" class="text-right" data-label="Base"><x-ui.money :value="$detalle->subtotal" :currency="$factura->moneda" /></td>
                        <td role="cell" class="text-right" data-label="IGV"><x-ui.money :value="$detalle->impuesto" :currency="$factura->moneda" /></td>
                        <td role="cell" class="text-right table-card__wide" data-label="Total con IGV"><strong><x-ui.money :value="$detalle->total" :currency="$factura->moneda" /></strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
