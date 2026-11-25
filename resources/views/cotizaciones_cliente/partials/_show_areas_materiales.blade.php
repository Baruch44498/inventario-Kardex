    @if ($cotizacion->proforma)
        <section class="panel supplier-quote-detail-lines">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Detalle</p><h2>Productos de la venta</h2></div></header>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Sugerido</th><th class="text-right">Cotizado</th><th>IGV</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($cotizacion->detalles as $detalle)
                            <tr>
                                <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                                <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                                <td class="text-right"><x-ui.money :value="$detalle->precio_sugerido" :currency="$cotizacion->moneda" /></td>
                                <td class="text-right"><strong><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion->moneda" /></strong>@if ($detalle->precioFueAjustado())<span>Ajustado por Logística</span>@endif</td>
                                <td>{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                                <td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" /></strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        @if ($esMultiComponente)
            <section class="panel supplier-quote-detail-lines commercial-client-summary">
                <header class="supplier-panel-heading">
                    <div><p class="eyebrow">Compatibilidad comercial</p><h2>Distribución heredada de la cotización</h2><p>Esta agrupación se conserva para documentos anteriores, pero al aprobar se consolidará en una sola orden principal.</p></div>
                </header>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Registro anterior</th><th>Concepto</th><th class="text-right">Importe</th></tr></thead>
                        <tbody>
                            @foreach ($componentes as $componente)
                                @php
                                    $lineasComponente = $cotizacion->detalles->where('componente_id', $componente->id);
                                    $esOpComponente = $componente->tipoOrden?->codigo === 'OP';
                                @endphp
                                @if ($esOpComponente || $lineasComponente->isEmpty())
                                    <tr>
                                        <td><strong>{{ $componente->tipoOrden?->codigo }} {{ $componente->orden_secuencia }}</strong></td>
                                        <td>{{ $componente->descripcion_componente }}</td>
                                        <td class="text-right"><strong><x-ui.money :value="$lineasComponente->sum('total')" :currency="$cotizacion->moneda" /></strong></td>
                                    </tr>
                                @else
                                    @foreach ($lineasComponente as $detalle)
                                        <tr>
                                            <td><strong>{{ $componente->tipoOrden?->codigo }} {{ $componente->orden_secuencia }}</strong><span>{{ $componente->descripcion_componente }}</span></td>
                                            <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }} · <x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</span></td>
                                            <td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" /></strong></td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @elseif ($esServicioMantenimiento)
            <section class="panel supplier-quote-detail-lines commercial-client-summary commercial-client-materials">
                <header class="supplier-panel-heading">
                    <div>
                        <p class="eyebrow">Propuesta comercial</p>
                        <h2>{{ $codigoTipoOrden === 'OM' ? 'Trabajo, materiales y repuestos para el cliente' : 'Servicio cotizado al cliente' }}</h2>
                        <p>{{ $codigoTipoOrden === 'OM'
                            ? 'En OM el cliente ve los materiales catalogados y el servicio agrupado. Los costos y márgenes permanecen internos.'
                            : 'En OS el cliente ve el concepto y precio del servicio. Su composición de costos permanece interna.' }}</p>
                    </div>
                </header>
                <div class="notice notice--info notice--block">
                    <x-ui.icon name="orders" :size="18" />
                    <div>
                        <strong>Trabajo cotizado</strong>
                        <span>{{ $cotizacion->descripcion_trabajo }}</span>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Material / repuesto</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Precio unitario</th>
                                <th>IGV</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cotizacion->detalles as $detalle)
                                <tr>
                                    <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                                    <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                                    <td class="text-right"><strong><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion->moneda" /></strong></td>
                                    <td>{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                                    <td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" /></strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="panel supplier-quote-detail-lines commercial-client-summary">
                <header class="supplier-panel-heading">
                    <div>
                        <p class="eyebrow">Propuesta comercial</p>
                        <h2>Resumen que corresponde al cliente</h2>
                        <p>{{ $esProduccion
                            ? 'En Producción el cliente ve la capacidad o descripción del trabajo y el importe final. La composición de materiales permanece interna.'
                            : 'El documento comercial utiliza el concepto del trabajo y el importe final.' }}</p>
                    </div>
                </header>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Concepto</th><th class="text-right">Importe</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><strong>{{ $cotizacion->descripcion_trabajo }}</strong><span>{{ $cotizacion->tipoOrden?->codigo }} · {{ $cotizacion->tipoOrden?->nombre }}</span></td>
                                <td class="text-right"><strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

    @endif
