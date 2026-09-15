    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Detalle de precios</p>
                <h2>Productos cotizados</h2>
            </div>
        </header>

        <div class="table-wrap supplier-quote-detail-table-wrap"
             role="region"
             aria-label="Detalle de productos cotizados"
             tabindex="0">
            <table class="data-table supplier-quote-detail-table">
                <thead>
                    <tr>
                        <th scope="col">Producto</th>
                        <th scope="col">Requerimiento</th>
                        <th scope="col">Marca ofrecida</th>
                        <th scope="col" class="text-right">Cantidad</th>
                        <th scope="col" class="text-right">Precio informado</th>
                        <th scope="col">Descuento</th>
                        <th scope="col">IGV</th>
                        <th scope="col" class="text-right">Base sin IGV</th>
                        <th scope="col" class="text-right">IGV línea</th>
                        <th scope="col" class="text-right">Total línea</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cotizacion->detalles as $detalle)
                        <tr>
                            <td class="supplier-quote-detail-table__product">
                                <strong>{{ $detalle->producto?->codigo }}</strong>
                                <span>{{ $detalle->producto?->descripcion }}</span>
                            </td>
                            <td class="supplier-quote-detail-table__relation">
                                <span class="badge badge--{{ $detalle->tipoVinculacionEfectivo() === 'SOLICITADO' ? 'success' : ($detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA' ? 'warning' : 'neutral') }}">
                                    {{ $detalle->vinculacionVisible() }}
                                </span>
                                @if ($detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA' && $detalle->requisicionDetalle)
                                    <strong>
                                        Solicitado: {{ $detalle->requisicionDetalle->producto?->codigo }}
                                    </strong>
                                    <span>{{ $detalle->requisicionDetalle->producto?->descripcion }}</span>
                                    <span><x-ui.quantity :value="$detalle->requisicionDetalle->cantidad_solicitada" /> solicitado</span>
                                @elseif ($detalle->requisicionDetalle)
                                    <strong><x-ui.quantity :value="$detalle->requisicionDetalle->cantidad_solicitada" /> solicitado</strong>
                                    <span>Esta oferta: <x-ui.quantity :value="$detalle->cantidad" /></span>
                                @else
                                    <span class="text-muted">Producto adicional de la oferta</span>
                                @endif
                                @if ($detalle->codigo_documento || $detalle->descripcion_documento)
                                    <small>
                                        Documento: {{ collect([$detalle->codigo_documento, $detalle->descripcion_documento])->filter()->implode(' — ') }}
                                    </small>
                                @endif
                            </td>
                            <td class="supplier-quote-detail-table__brand">
                                <strong>{{ $detalle->marca_ofertada ?: 'No especificada' }}</strong>
                                @if ($detalle->observacion)<span>{{ $detalle->observacion }}</span>@endif
                            </td>
                            <td class="text-right supplier-quote-detail-table__quantity">
                                @if ($detalle->cantidad_presentacion !== null)
                                    <strong><x-ui.quantity :value="$detalle->cantidad_presentacion" /> {{ $detalle->presentacion_nombre }}</strong>
                                    <span>= <x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->codigo }}</span>
                                @else
                                    <x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->codigo }}
                                @endif
                            </td>
                            <td class="text-right supplier-quote-detail-table__money">
                                <x-ui.money :value="$detalle->precio_presentacion ?? $detalle->precio_unitario" :currency="$cotizacion->moneda" />
                                @if ((float) $detalle->factor_conversion !== 1.0)
                                    <span>por {{ $detalle->presentacion_nombre }}</span>
                                @endif
                            </td>
                            <td class="supplier-quote-detail-table__discount"><strong>{{ $detalle->descuentoVisible() }}</strong></td>
                            <td class="supplier-quote-detail-table__tax"><span>{{ $detalle->igvVisible() }}</span></td>
                            <td class="text-right supplier-quote-detail-table__money">
                                <x-ui.money :value="$detalle->subtotal" :currency="$cotizacion->moneda" />
                            </td>
                            <td class="text-right supplier-quote-detail-table__money">
                                <x-ui.money :value="$detalle->impuesto" :currency="$cotizacion->moneda" />
                            </td>
                            <td class="text-right supplier-quote-detail-table__money supplier-quote-detail-table__total">
                                <strong>
                                    <x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" />
                                </strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
