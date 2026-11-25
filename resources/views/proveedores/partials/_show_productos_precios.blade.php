    <section class="panel supplier-related-panel">
        <header class="supplier-panel-heading supplier-panel-heading--split">
            <div>
                <p class="eyebrow">Trazabilidad por producto</p>
                <h2>Precios ofrecidos</h2>
            </div>
            <a href="{{ route('historial-precios.index', ['proveedor_id' => $proveedor->id]) }}"
                class="button button--ghost button--small">Ver comparación</a>
        </header>

        @if ($precios->isNotEmpty())
            <div class="table-wrap table-wrap--cards">
                <table class="data-table data-table--cards" role="table">
                    <thead role="rowgroup">
                        <tr role="row">
                            <th role="columnheader" scope="col">Fecha</th>
                            <th role="columnheader" scope="col">Producto</th>
                            <th role="columnheader" scope="col">Marca</th>
                            <th role="columnheader" scope="col">Moneda</th>
                            <th role="columnheader" scope="col" class="text-right">Precio informado</th>
                            <th role="columnheader" scope="col">Descuento</th>
                            <th role="columnheader" scope="col">IGV</th>
                            <th role="columnheader" scope="col" class="text-right">Precio final</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        @foreach ($precios as $precio)
                            <tr role="row">
                                <td role="cell" data-label="Fecha">{{ $precio->cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                                <td role="cell" class="table-card__wide" data-label="Producto">
                                    <strong>{{ $precio->producto?->codigo }}</strong>
                                    <span>{{ $precio->producto?->descripcion }}</span>
                                </td>
                                <td role="cell" data-label="Marca">{{ $precio->marca_ofertada ?: 'No especificada' }}</td>
                                <td role="cell" data-label="Moneda">{{ $precio->cotizacion->moneda }}</td>
                                <td role="cell" class="text-right" data-label="Precio informado">
                                    {{ $precio->cotizacion->simboloMoneda() }}
                                    {{ number_format((float) $precio->precio_unitario, 2) }}
                                </td>
                                <td role="cell" data-label="Descuento">{{ $precio->descuentoVisible() }}</td>
                                <td role="cell" data-label="IGV">{{ $precio->igvVisible() }}</td>
                                <td role="cell" class="text-right" data-label="Precio final">
                                    <strong>
                                        {{ $precio->cotizacion->simboloMoneda() }}
                                        {{ number_format($precio->precioFinalUnitario(), 2) }}
                                    </strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$precios" />
        @else
            <div class="empty-table-state supplier-related-empty">
                <span class="empty-state__icon"><x-ui.icon name="banknote" :size="29" /></span>
                <strong>Sin precios históricos</strong>
                <span>Los precios aparecerán al registrar una cotización.</span>
            </div>
        @endif
    </section>
