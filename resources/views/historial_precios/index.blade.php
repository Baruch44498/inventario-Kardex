@extends('layouts.app')

@section('title', 'Historial de precios')
@section('page-kicker', 'Compras')
@section('page-title', 'Historial de precios')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Inteligencia de compras</p>
            <h1>Historial de precios de proveedores</h1>
            <p>Compara el costo final ofrecido sin mezclar monedas y conserva el documento de origen.</p>
        </div>

        <div class="module-header__actions">
            <a href="{{ route('cotizaciones-proveedor.index') }}" class="button button--ghost">
                <x-ui.icon name="quotes" :size="17" /> Ver cotizaciones
            </a>
            <a href="{{ route('cotizaciones-proveedor.create') }}" class="button button--primary">
                <x-ui.icon name="plus" :size="18" /> Registrar oferta
            </a>
        </div>
    </section>

    <section class="summary-strip summary-strip--four">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="quotes" :size="21" /></span>
            <div><span>Ofertas</span><strong>{{ $estadisticas['ofertas'] }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="products" :size="21" /></span>
            <div><span>Productos</span><strong>{{ $estadisticas['productos'] }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning"><x-ui.icon name="suppliers" :size="21" /></span>
            <div><span>Proveedores</span><strong>{{ $estadisticas['proveedores'] }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="clipboard" :size="21" /></span>
            <div>
                <span>Última oferta</span>
                <strong>
                    {{ $estadisticas['ultima_fecha']
                        ? \Illuminate\Support\Carbon::parse($estadisticas['ultima_fecha'])->format('d/m/Y')
                        : '—' }}
                </strong>
            </div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('historial-precios.index') }}" class="price-history-filter">
            <label class="form-field price-history-filter__search">
                <span>Buscar producto</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Código o descripción">
                </div>
            </label>

            <div class="form-field">
                <label for="historial_producto_busqueda">Producto</label>
                <x-ui.remote-combobox
                    name="producto_id"
                    search-id="historial_producto_busqueda"
                    value-id="historial_producto_id"
                    :search-url="route('catalogos.productos.buscar', ['todos' => 1])"
                    :selected-id="$productoFiltro?->id"
                    :selected-label="$productoFiltro
                        ? $productoFiltro->codigo.' — '.$productoFiltro->descripcion
                        : ''"
                    placeholder="Código o descripción"
                    empty-text="No se encontró el producto."
                />
            </div>

            <div class="form-field">
                <label for="historial_proveedor_busqueda">Proveedor</label>
                <x-ui.remote-combobox
                    name="proveedor_id"
                    search-id="historial_proveedor_busqueda"
                    value-id="historial_proveedor_id"
                    :search-url="route('catalogos.proveedores.buscar', ['todos' => 1])"
                    :selected-id="$proveedorFiltro?->id"
                    :selected-label="$proveedorFiltro
                        ? $proveedorFiltro->ruc.' — '.$proveedorFiltro->nombreVisible()
                        : ''"
                    placeholder="RUC o razón social"
                    empty-text="No se encontró el proveedor."
                />
            </div>

            <div class="form-field">
                <label for="historial_requisicion_busqueda">Requerimiento</label>
                <x-ui.remote-combobox
                    name="requisicion_id"
                    search-id="historial_requisicion_busqueda"
                    value-id="historial_requisicion_id"
                    :search-url="route('catalogos.requisiciones.buscar')"
                    :selected-id="$requisicionFiltro?->id"
                    :selected-label="$requisicionFiltro?->codigo ?? ''"
                    placeholder="Código del requerimiento"
                    empty-text="No se encontró el requerimiento."
                />
            </div>

            <label class="form-field">
                <span>Moneda</span>
                <select name="moneda">
                    <option value="">Todas</option>
                    <option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option>
                    <option value="USD" @selected(request('moneda') === 'USD')>USD</option>
                </select>
            </label>

            <label class="form-field">
                <span>Decisión</span>
                <select name="estado">
                    <option value="">Todas las válidas</option>
                    @foreach (['REGISTRADA' => 'Pendiente de decisión', 'SELECCIONADA' => 'Enviada a Contabilidad', 'NO_REQUERIDA' => 'No requerida', 'NO_UTILIZADA' => 'No utilizada', 'ANULADA' => 'Invalidada'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-field">
                <span>Desde</span>
                <input type="date" name="desde" value="{{ request('desde') }}">
            </label>

            <label class="form-field">
                <span>Hasta</span>
                <input type="date" name="hasta" value="{{ request('hasta') }}">
            </label>

            <label class="price-history-available-toggle">
                <input type="checkbox" name="solo_utilizables" value="1" @checked(request()->boolean('solo_utilizables'))>
                <span><strong>Solo disponibles para compra</strong><small>Excluye enviadas, archivadas e invalidadas.</small></span>
            </label>

            <div class="filter-actions">
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="filter" :size="17" /> Comparar
                </button>
                <a href="{{ route('historial-precios.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
    </section>

    <div class="price-history-decision-note" role="note">
        <x-ui.icon name="info" :size="19" />
        <p><strong>El historial conserva las ofertas válidas aunque no se compren.</strong> “No requerida” y “No utilizada” bloquean su uso en una OC, pero mantienen el precio como referencia. “Invalidada” se reserva para documentos erróneos y nunca participa en comparaciones.</p>
    </div>

    <section class="price-reference-grid">
        <article class="price-reference-card">
            <span>PEN — mínimo</span>
            <strong>{{ $estadisticas['pen_minimo'] !== null ? 'S/ '.number_format($estadisticas['pen_minimo'], 2) : 'Sin datos' }}</strong>
            <small>Promedio: {{ $estadisticas['pen_promedio'] !== null ? 'S/ '.number_format($estadisticas['pen_promedio'], 2) : '—' }}</small>
        </article>

        <article class="price-reference-card">
            <span>USD — mínimo</span>
            <strong>{{ $estadisticas['usd_minimo'] !== null ? 'US$ '.number_format($estadisticas['usd_minimo'], 2) : 'Sin datos' }}</strong>
            <small>Promedio: {{ $estadisticas['usd_promedio'] !== null ? 'US$ '.number_format($estadisticas['usd_promedio'], 2) : '—' }}</small>
        </article>

        <article class="price-reference-note">
            <x-ui.icon name="info" :size="20" />
            <p>PEN y USD se comparan por separado usando el precio final con IGV. El tipo de cambio solo genera una referencia en soles.</p>
        </article>
    </section>

    <section class="panel price-comparison-panel">
        <header class="price-history-heading">
            <div>
                <p class="eyebrow">Resumen por proveedor</p>
                <h2>Comparación de ofertas</h2>
                <p>Mínimo, promedio y máximo según los filtros aplicados.</p>
            </div>
        </header>

        @if ($comparacion->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Moneda</th>
                            <th>Ofertas</th>
                            <th class="text-right">Mínimo</th>
                            <th class="text-right">Promedio</th>
                            <th class="text-right">Máximo</th>
                            <th>Última fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparacion as $fila)
                            @php $simbolo = $fila->moneda === 'USD' ? 'US$' : 'S/'; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('proveedores.show', $fila->proveedor_id) }}"
                                        class="table-primary-link">
                                        {{ $fila->nombre_comercial ?: $fila->razon_social }}
                                    </a>
                                </td>
                                <td><span class="currency-chip">{{ $fila->moneda }}</span></td>
                                <td>{{ $fila->ofertas }}</td>
                                <td class="text-right"><strong>{{ $simbolo }} {{ number_format((float) $fila->precio_minimo, 2) }}</strong></td>
                                <td class="text-right">{{ $simbolo }} {{ number_format((float) $fila->precio_promedio, 2) }}</td>
                                <td class="text-right">{{ $simbolo }} {{ number_format((float) $fila->precio_maximo, 2) }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($fila->ultima_fecha)->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-table-state price-history-empty">
                <span class="empty-state__icon"><x-ui.icon name="banknote" :size="30" /></span>
                <strong>Sin precios para comparar</strong>
                <span>Registra cotizaciones o ajusta los filtros.</span>
            </div>
        @endif
    </section>

    <section class="panel price-history-panel">
        <header class="price-history-heading">
            <div>
                <p class="eyebrow">Trazabilidad completa</p>
                <h2>Historial de ofertas</h2>
                <p>Cada precio conserva proveedor, fecha, moneda, descuento y cotización de origen.</p>
            </div>
        </header>

        @if ($historial->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table price-history-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Proveedor</th>
                            <th>Requerimiento</th>
                            <th>Marca</th>
                            <th>Moneda</th>
                            <th class="text-right">Precio informado</th>
                            <th>Descuento</th>
                            <th>IGV</th>
                            <th class="text-right">Base sin IGV</th>
                            <th class="text-right">Precio final</th>
                            <th class="text-right">Equiv. PEN</th>
                            <th>Decisión</th>
                            <th>Cotización</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($historial as $detalle)
                            @php $equivalente = $detalle->precioEquivalentePen(); @endphp
                            <tr>
                                <td>{{ $detalle->cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                                <td>
                                    <strong>{{ $detalle->producto?->codigo }}</strong>
                                    <span>{{ $detalle->producto?->descripcion }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('proveedores.show', $detalle->cotizacion->proveedor_id) }}"
                                        class="table-primary-link">
                                        {{ $detalle->cotizacion->proveedor->nombreVisible() }}
                                    </a>
                                </td>
                                <td>
                                    @if ($detalle->cotizacion->requisicion)
                                        <a href="{{ route('requerimientos-compra.show', $detalle->cotizacion->requisicion) }}" class="table-primary-link">{{ $detalle->cotizacion->requisicion->codigo }}</a>
                                    @else
                                        <span>Sin requerimiento</span>
                                    @endif
                                </td>
                                <td>{{ $detalle->marca_ofertada ?: 'No especificada' }}</td>
                                <td><span class="currency-chip">{{ $detalle->cotizacion->moneda }}</span></td>
                                <td class="text-right">
                                    {{ $detalle->cotizacion->simboloMoneda() }}
                                    {{ number_format((float) $detalle->precio_unitario, 2) }}
                                </td>
                                <td>{{ $detalle->descuentoVisible() }}</td>
                                <td>{{ $detalle->igvVisible() }}</td>
                                <td class="text-right">
                                    {{ $detalle->cotizacion->simboloMoneda() }}
                                    {{ number_format($detalle->precioBaseUnitario(), 2) }}
                                </td>
                                <td class="text-right">
                                    <strong>
                                        {{ $detalle->cotizacion->simboloMoneda() }}
                                        {{ number_format($detalle->precioFinalUnitario(), 2) }}
                                    </strong>
                                </td>
                                <td class="text-right">{{ $equivalente !== null ? 'S/ '.number_format($equivalente, 2) : '—' }}</td>
                                <td>
                                    <span class="badge badge--{{ $detalle->cotizacion->estadoClase() }}">{{ $detalle->cotizacion->estadoVisible() }}</span>
                                    @if ($detalle->cotizacion->solicitudCompra)
                                        <a href="{{ route('solicitudes-compra.show', $detalle->cotizacion->solicitudCompra) }}" class="price-history-request-link">{{ $detalle->cotizacion->solicitudCompra->codigo }}</a>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('cotizaciones-proveedor.show', $detalle->cotizacion_id) }}"
                                        class="button button--ghost button--small">
                                        {{ $detalle->cotizacion->codigo }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$historial" />
        @else
            <div class="empty-table-state price-history-empty">
                <span class="empty-state__icon"><x-ui.icon name="quotes" :size="30" /></span>
                <strong>Sin historial registrado</strong>
                <span>Los precios aparecerán al registrar cotizaciones.</span>
            </div>
        @endif
    </section>
@endsection
