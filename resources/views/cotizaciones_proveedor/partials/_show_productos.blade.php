    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Detalle de precios</p>
                <h2>Productos cotizados</h2>
            </div>
        </header>
        <div class="supplier-quote-product-tools" data-supplier-quote-product-tools hidden>
            <label for="supplier-quote-product-search">Buscar producto por código o descripción</label>
            <div class="supplier-quote-product-tools__field">
                <input id="supplier-quote-product-search" type="search" autocomplete="off"
                    aria-controls="supplier-quote-product-results"
                    placeholder="Código o descripción" data-supplier-quote-product-search>
                <button type="button" class="button button--ghost" data-supplier-quote-product-clear hidden>Limpiar búsqueda</button>
            </div>
        </div>
        <p class="supplier-quote-detail-help">Si el precio incluye IGV, la base y el impuesto son su desglose, no cargos adicionales.</p>

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
                        <th scope="col">Condiciones</th>
                        <th scope="col" class="text-right">Importe</th>
                    </tr>
                </thead>
                <tbody id="supplier-quote-product-results">
                    @foreach ($cotizacion->detalles as $detalle)
                        <tr data-supplier-quote-product-row>
                            <td class="supplier-quote-detail-table__product" data-label="Producto">
                                <strong>{{ $detalle->producto?->codigo }}</strong>
                                <span>{{ $detalle->producto?->descripcion }}</span>
                            </td>
                            <td class="supplier-quote-detail-table__relation" data-label="Requerimiento">
                                <span class="badge badge--{{ $detalle->tipoVinculacionEfectivo() === 'SOLICITADO' ? 'success' : ($detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA' ? 'warning' : 'neutral') }}">
                                    {{ $detalle->vinculacionVisible() }}
                                </span>
                                @if ($detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA' && $detalle->requisicionDetalle)
                                    <small class="supplier-quote-detail-table__requested">
                                        Solicitado: {{ $detalle->requisicionDetalle->producto?->codigo }} — {{ $detalle->requisicionDetalle->producto?->descripcion }}
                                        · <x-ui.quantity :value="$detalle->requisicionDetalle->cantidad_solicitada" />
                                    </small>
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
                            <td class="supplier-quote-detail-table__brand" data-label="Marca y observaciones">
                                <strong>{{ $detalle->marca_ofertada ?: 'No especificada' }}</strong>
                                @if ($detalle->observacion)<span>{{ $detalle->observacion }}</span>@endif
                            </td>
                            <td class="text-right supplier-quote-detail-table__quantity" data-label="Cantidad">
                                @if ($detalle->cantidad_presentacion !== null)
                                    <strong><x-ui.quantity :value="$detalle->cantidad_presentacion" /> {{ $detalle->presentacion_nombre }}</strong>
                                    <span>= <x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->codigo }}</span>
                                @else
                                    <x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->codigo }}
                                @endif
                            </td>
                            <td class="text-right supplier-quote-detail-table__money" data-label="Precio informado">
                                <x-ui.money :value="$detalle->precio_presentacion ?? $detalle->precio_unitario" :currency="$cotizacion->moneda" />
                                @if ((float) $detalle->factor_conversion !== 1.0)
                                    <span>por {{ $detalle->presentacion_nombre }}</span>
                                @endif
                            </td>
                            <td class="supplier-quote-detail-table__conditions" data-label="Condiciones">
                                <span>Descuento: {{ $detalle->descuentoVisible() }}</span>
                                <span>IGV: {{ $detalle->igvVisible() }}</span>
                            </td>
                            <td class="text-right supplier-quote-detail-table__money supplier-quote-detail-table__total" data-label="Importe">
                                <strong>
                                    <x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" />
                                </strong>
                                <small>Base <x-ui.money :value="$detalle->subtotal" :currency="$cotizacion->moneda" /> · IGV <x-ui.money :value="$detalle->impuesto" :currency="$cotizacion->moneda" /></small>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="supplier-quote-product-empty" data-supplier-quote-product-empty hidden>No encontramos productos con ese código o descripción.</p>
        <div class="supplier-quote-product-footer" data-supplier-quote-product-footer>
            <p class="supplier-quote-product-range" data-supplier-quote-product-range role="status" aria-live="polite" aria-atomic="true">Mostrando {{ $cotizacion->detalles->count() ? '1–'.$cotizacion->detalles->count() : '0' }} de {{ $cotizacion->detalles->count() }} productos</p>
            <nav class="supplier-quote-product-pages" data-supplier-quote-product-pages aria-label="Páginas de productos cotizados" hidden>
                <button type="button" class="pagination__button" data-supplier-quote-product-prev>Anterior</button>
                <span class="supplier-quote-product-pages__numbers" data-supplier-quote-product-numbers></span>
                <button type="button" class="pagination__button" data-supplier-quote-product-next>Siguiente</button>
            </nav>
        </div>
        <div class="supplier-quote-product-grand-total">
            <span>Total de la cotización</span>
            <strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong>
        </div>
    </section>
