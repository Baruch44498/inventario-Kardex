<section class="panel purchase-requirement-detail-panel">
    <div class="panel-heading purchase-requirement-section-heading">
        <div class="purchase-requirement-section-heading__copy">
            <p class="eyebrow">Productos</p>
            <div class="purchase-requirement-section-heading__title-row">
                <h2>Necesidad enviada</h2>
                @if ($requerimiento->esBorrador())
                    <x-ui.collapsible-notice
                        class="purchase-requirement-section-heading__help"
                        title="Todavía es un borrador de Almacén"
                        label="Ver qué ocurrirá al enviarlo"
                    >
                        <span>Al enviarlo, Logística podrá verlo en su bandeja junto con los proveedores que históricamente cotizaron estos productos. Enviar no compra ni mueve stock.</span>
                    </x-ui.collapsible-notice>
                @endif
            </div>
            <p class="purchase-requirement-section-heading__description">Productos y cantidades que Almacén solicita abastecer.</p>
        </div>
        <div class="purchase-requirement-section-heading__meta" aria-label="Cantidad de productos">
            <span class="count-chip">{{ $requerimiento->detalles->count() }}</span>
        </div>
    </div>

    <div class="prc-product-list">
        @foreach ($requerimiento->detalles as $detalle)
            @php
                $proveedores = $proveedoresPorProducto->get($detalle->producto_id, collect());
                $ofertas = $detalle->cotizacionDetalles
                    ->filter(fn ($linea) => $linea->cotizacion && $linea->cotizacion->estado !== 'ANULADA');
            @endphp
            <article class="prc-product-row">
                <div class="prc-product-row__identity">
                    <div class="prc-product-row__code-wrap">
                        <span class="prc-product-row__code">{{ $detalle->producto?->codigo ?? '—' }}</span>
                        <span class="prc-product-row__qty">
                            <strong><x-ui.quantity :value="$detalle->cantidad_solicitada" /></strong>
                            <small>{{ $detalle->producto?->unidadMedida?->abreviatura ?? '' }}</small>
                        </span>
                    </div>
                    <p class="prc-product-row__desc">{{ $detalle->producto?->descripcion ?? 'Producto no disponible' }}</p>
                    @if ($detalle->observacion)
                        <p class="prc-product-row__obs">{{ $detalle->observacion }}</p>
                    @endif
                </div>

                <div class="prc-product-row__stock">
                    <p class="prc-product-row__stock-label">Stock al registrar</p>
                    <div class="prc-stock-chips">
                        <span class="prc-stock-chip prc-stock-chip--suggested" title="Cantidad sugerida por el sistema">
                            <span class="prc-stock-chip__key">Sug.</span>
                            <strong><x-ui.quantity :value="$detalle->cantidad_sugerida ?? 0" /></strong>
                        </span>
                        <span class="prc-stock-chip" title="Stock físico real">
                            <span class="prc-stock-chip__key">Físico</span>
                            <strong><x-ui.quantity :value="$detalle->stock_fisico_snapshot ?? 0" /></strong>
                        </span>
                        <span class="prc-stock-chip" title="Cantidad reservada">
                            <span class="prc-stock-chip__key">Reserv.</span>
                            <x-ui.quantity :value="$detalle->reservado_snapshot ?? 0" />
                        </span>
                        <span class="prc-stock-chip {{ ($detalle->disponible_snapshot ?? 0) < 0 ? 'prc-stock-chip--danger' : 'prc-stock-chip--available' }}" title="Disponible libre">
                            <span class="prc-stock-chip__key">Disp.</span>
                            <strong><x-ui.quantity :value="$detalle->disponible_snapshot ?? 0" /></strong>
                        </span>
                        <span class="prc-stock-chip prc-stock-chip--min" title="Stock mínimo configurado">
                            <span class="prc-stock-chip__key">Mín.</span>
                            <x-ui.quantity :value="$detalle->stock_minimo_snapshot ?? 0" />
                        </span>
                    </div>
                </div>

                <div class="prc-product-row__contacts">
                    @if ($ofertas->isNotEmpty())
                        <details class="prc-expandable">
                            <summary class="prc-expandable__trigger prc-expandable__trigger--info">
                                <x-ui.icon name="check" :size="13" />
                                {{ $ofertas->count() }} oferta{{ $ofertas->count() === 1 ? '' : 's' }} recibida{{ $ofertas->count() === 1 ? '' : 's' }}
                            </summary>
                            <div class="prc-expandable__body">
                                @foreach ($ofertas as $oferta)
                                    <div class="prc-mini-card">
                                        <strong>{{ $oferta->cotizacion?->proveedor?->nombreVisible() ?? 'Proveedor' }}</strong>
                                        <span>{{ $oferta->cotizacion?->codigo ?? '—' }} &middot; {{ $oferta->cotizacion?->simboloMoneda() }} {{ number_format((float) $oferta->precio_unitario, 2) }}</span>
                                        <small><x-ui.quantity :value="$oferta->cantidad" /> cotizados</small>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <span class="prc-product-row__no-data">Sin cotizaciones aún</span>
                    @endif

                    @if ($proveedores->isNotEmpty())
                        <details class="prc-expandable">
                            <summary class="prc-expandable__trigger prc-expandable__trigger--neutral">
                                <x-ui.icon name="suppliers" :size="13" />
                                {{ $proveedores->count() }} proveedor{{ $proveedores->count() === 1 ? '' : 'es' }} conocido{{ $proveedores->count() === 1 ? '' : 's' }}
                            </summary>
                            <div class="prc-expandable__body">
                                @foreach ($proveedores as $proveedor)
                                    <div class="prc-mini-card">
                                        <strong>{{ $proveedor->nombre_comercial ?: $proveedor->razon_social }}</strong>
                                        <span>{{ $proveedor->telefono ?: 'Sin teléfono' }}</span>
                                        <small>{{ $proveedor->correo ?: 'Sin correo registrado' }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @else
                        <span class="prc-product-row__no-data">Sin proveedor histórico</span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
