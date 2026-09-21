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
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Marca</th>
                            <th>Moneda</th>
                            <th class="text-right">Precio informado</th>
                            <th>Descuento</th>
                            <th>IGV</th>
                            <th class="text-right">Precio final</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($precios as $precio)
                            <tr>
                                <td>{{ $precio->cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                                <td>
                                    <strong>{{ $precio->producto?->codigo }}</strong>
                                    <span>{{ $precio->producto?->descripcion }}</span>
                                </td>
                                <td>{{ $precio->marca_ofertada ?: 'No especificada' }}</td>
                                <td>{{ $precio->cotizacion->moneda }}</td>
                                <td class="text-right">
                                    {{ $precio->cotizacion->simboloMoneda() }}
                                    {{ number_format((float) $precio->precio_unitario, 2) }}
                                </td>
                                <td>{{ $precio->descuentoVisible() }}</td>
                                <td>{{ $precio->igvVisible() }}</td>
                                <td class="text-right">
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
