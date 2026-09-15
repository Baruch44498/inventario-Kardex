<section class="panel supplier-invoice-lines-panel">
    <div class="panel-heading"><p class="eyebrow">Productos</p><h2>Detalle facturado</h2><p>Los importes corresponden a las condiciones autorizadas en la Orden de Compra.</p></div>
    <div class="table-wrap table-wrap--wide">
        <table class="data-table supplier-invoice-detail-table">
            <thead><tr><th>Producto</th><th>Recepción conciliada</th><th class="text-right">Cantidad</th><th class="text-right">Base unitaria</th><th class="text-right">Base</th><th class="text-right">IGV</th><th class="text-right">Total con IGV</th></tr></thead>
            <tbody>
                @foreach ($factura->detalles as $detalle)
                    <tr>
                        <td><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->descripcion }}</span></td>
                        <td>
                            @if ($detalle->notaIngresoDetalle?->notaIngreso)
                                <a href="{{ route('notas-ingreso.show', $detalle->notaIngresoDetalle->notaIngreso) }}"><strong>{{ $detalle->notaIngresoDetalle->notaIngreso->codigo }}</strong><span>{{ $detalle->notaIngresoDetalle->notaIngreso->fecha_ingreso?->format('d/m/Y') }}</span></a>
                            @elseif ($factura->notasIngreso->isNotEmpty())
                                <span>Recepción vinculada desde la factura</span>
                            @else
                                <span>Pendiente de recepción</span>
                            @endif
                        </td>
                        <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /></td>
                        <td class="text-right"><x-ui.money :value="$detalle->precio_unitario" :currency="$factura->moneda" /></td>
                        <td class="text-right"><x-ui.money :value="$detalle->subtotal" :currency="$factura->moneda" /></td>
                        <td class="text-right"><x-ui.money :value="$detalle->impuesto" :currency="$factura->moneda" /></td>
                        <td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$factura->moneda" /></strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
