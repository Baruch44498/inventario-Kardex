@extends('layouts.app')

@section('title', 'Historial de precios')
@section('page-kicker', 'Compras')
@section('page-title', 'Historial de precios')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Inteligencia de compras</p>
            <h1>Historial de precios de proveedores</h1>
            <p>Consulta precios por producto y proveedor, con su decisión y documento de origen.</p>
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
            <div><span>Registros de precios</span><strong>{{ $estadisticas['ofertas'] }}</strong></div>
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

    @php
        $filtrosSecundariosActivos = collect(['requisicion_id', 'moneda', 'estado', 'desde', 'hasta'])
            ->filter(fn ($nombre) => filled(request($nombre)))
            ->count() + (request()->boolean('solo_utilizables') ? 1 : 0);
    @endphp

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('historial-precios.index') }}" class="price-history-filter price-history-filter--compact">
            <label class="form-field price-history-filter__search">
                <span>Búsqueda libre</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Código o descripción"
                        data-suggest-table=".price-history-table" data-suggest-cell="2">
                </div>
                <small>Busca por código o descripción del producto.</small>
            </label>

            <div class="form-field price-history-filter__product">
                <label for="historial_producto_busqueda">Producto específico</label>
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

            <div class="form-field price-history-filter__provider">
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

            <details class="price-history-more" @if ($filtrosSecundariosActivos > 0) open @endif>
                <summary>
                    <span>Más filtros</span>
                    @if ($filtrosSecundariosActivos > 0)
                        <span class="price-history-more__count">{{ $filtrosSecundariosActivos }} {{ $filtrosSecundariosActivos === 1 ? 'activo' : 'activos' }}</span>
                    @endif
                </summary>
                <div class="price-history-more__fields">
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
                        @foreach (['REGISTRADA' => 'Pendiente de decisión', 'SELECCIONADA' => 'Aprobada para compra', 'NO_REQUERIDA' => 'No requerida', 'NO_UTILIZADA' => 'No utilizada', 'ANULADA' => 'Invalidada'] as $valor => $texto)
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
                </div>
            </details>

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
        @if (request('estado') === 'ANULADA')
            <p><strong>Registros invalidados para auditoría.</strong> Sus precios aparecen en el historial, pero quedan fuera de los indicadores y de la comparación por proveedor.</p>
        @else
            <p><strong>El historial conserva las ofertas válidas aunque no se compren.</strong> “No requerida” y “No utilizada” bloquean su uso en una OC, pero mantienen el precio como referencia. “Invalidada” se reserva para documentos erróneos y nunca participa en comparaciones.</p>
        @endif
    </div>

    <section class="price-reference-grid">
        <article class="price-reference-card">
            <span>PEN — mínimo</span>
            <strong>{{ $estadisticas['pen_minimo'] !== null ? 'S/ '.number_format($estadisticas['pen_minimo'], 2) : 'Sin registros en PEN' }}</strong>
            <small>{{ $estadisticas['pen_promedio'] !== null ? 'Promedio: S/ '.number_format($estadisticas['pen_promedio'], 2) : 'Sin promedio con estos filtros.' }}</small>
        </article>

        <article class="price-reference-card">
            <span>USD — mínimo</span>
            <strong>{{ $estadisticas['usd_minimo'] !== null ? 'US$ '.number_format($estadisticas['usd_minimo'], 2) : 'Sin registros en USD' }}</strong>
            <small>{{ $estadisticas['usd_promedio'] !== null ? 'Promedio: US$ '.number_format($estadisticas['usd_promedio'], 2) : 'Sin promedio con estos filtros.' }}</small>
        </article>

        <article class="price-reference-note">
            <x-ui.icon name="info" :size="20" />
            <p>Los indicadores incluyen los registros válidos que cumplen los filtros. Pueden corresponder a productos distintos; PEN y USD se muestran por separado. El tipo de cambio solo genera una referencia en soles.</p>
        </article>
    </section>

    <section class="panel price-comparison-panel">
        <header class="price-history-heading">
            <div>
                <p class="eyebrow">Resumen por proveedor</p>
                <h2>{{ $productoFiltro ? 'Comparación de ofertas del producto' : 'Panorama histórico por proveedor' }}</h2>
                @if ($productoFiltro)
                    <p>{{ $productoFiltro->codigo }} — {{ $productoFiltro->descripcion }}. Mínimo, promedio y máximo por proveedor y moneda según los filtros.</p>
                @else
                    <p>Mínimos y promedios pueden mezclar productos distintos; no indican qué proveedor es más barato para un mismo artículo.</p>
                @endif
            </div>
        </header>

        @if ($comparacion->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>Moneda</th>
                            <th>Registros de precios</th>
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
                <span>{{ request('estado') === 'ANULADA' ? 'Los documentos invalidados se muestran abajo para auditoría, sin participar en estos cálculos.' : 'Registra cotizaciones o ajusta los filtros.' }}</span>
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
            <div class="table-wrap table-wrap--responsive price-history-table-wrap" data-responsive-table>
                <table class="data-table data-table--responsive price-history-table">
                    <thead>
                        <tr>
                            <th class="table-details-heading"><span class="sr-only">Detalles</span></th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Proveedor</th>
                            <th>Moneda</th>
                            <th class="text-right">Precio final</th>
                            <th>Decisión</th>
                            <th>Cotización</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($historial as $detalle)
                            @php
                                $equivalente = $detalle->precioEquivalentePen();
                                $detalleFilaId = 'price-history-details-'.$detalle->id;
                            @endphp
                            <tr class="price-history-row">
                                <td class="table-details-cell">
                                    <x-ui.table-details-toggle
                                        :target="$detalleFilaId"
                                        :label="'Ver detalles del precio de '.$detalle->producto?->codigo.' en '.$detalle->cotizacion->codigo.', línea '.$detalle->id"
                                    />
                                </td>
                                <td data-label="Fecha">{{ $detalle->cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                                <td class="price-history-row__product" data-label="Producto">
                                    <strong>{{ $detalle->producto?->codigo }}</strong>
                                    <span>{{ $detalle->producto?->descripcion }}</span>
                                </td>
                                <td class="price-history-row__supplier" data-label="Proveedor">
                                    <a href="{{ route('proveedores.show', $detalle->cotizacion->proveedor_id) }}"
                                        class="table-primary-link">
                                        {{ $detalle->cotizacion->proveedor->nombreVisible() }}
                                    </a>
                                </td>
                                <td data-label="Moneda"><span class="currency-chip">{{ $detalle->cotizacion->moneda }}</span></td>
                                <td class="text-right price-history-row__price" data-label="Precio final">
                                    <strong class="price-history-money">
                                        {{ $detalle->cotizacion->simboloMoneda() }}
                                        {{ number_format($detalle->precioFinalUnitario(), 2) }}
                                    </strong>
                                </td>
                                <td data-label="Decisión">
                                    <span class="badge badge--{{ $detalle->cotizacion->estadoClase() }}">{{ $detalle->cotizacion->estadoVisible() }}</span>
                                </td>
                                <td class="price-history-row__quote" data-label="Cotización">
                                    <a href="{{ route('cotizaciones-proveedor.show', $detalle->cotizacion_id) }}"
                                        class="button button--ghost button--small">
                                        {{ $detalle->cotizacion->codigo }}
                                    </a>
                                </td>
                            </tr>
                            <x-ui.table-row-details :id="$detalleFilaId" :colspan="8">
                                <dl class="table-details-grid price-history-details">
                                    <div>
                                        <dt>Requerimiento</dt>
                                        <dd>
                                            @if ($detalle->cotizacion->requisicion)
                                                <a href="{{ route('requerimientos-compra.show', $detalle->cotizacion->requisicion) }}" class="table-primary-link">{{ $detalle->cotizacion->requisicion->codigo }}</a>
                                            @else
                                                Sin requerimiento
                                            @endif
                                        </dd>
                                    </div>
                                    <div><dt>Marca</dt><dd>{{ $detalle->marca_ofertada ?: 'No especificada' }}</dd></div>
                                    <div>
                                        <dt>Precio informado</dt>
                                        <dd class="price-history-money">{{ $detalle->cotizacion->simboloMoneda() }} {{ number_format((float) $detalle->precio_unitario, 2) }}</dd>
                                    </div>
                                    <div><dt>Descuento</dt><dd>{{ $detalle->descuentoVisible() }}</dd></div>
                                    <div><dt>Condición de IGV</dt><dd>{{ $detalle->igvVisible() }}</dd></div>
                                    <div>
                                        <dt>Base sin IGV</dt>
                                        <dd class="price-history-money">{{ $detalle->cotizacion->simboloMoneda() }} {{ number_format($detalle->precioBaseUnitario(), 2) }}</dd>
                                    </div>
                                    <div><dt>Equiv. PEN</dt><dd class="price-history-money">{{ $equivalente !== null ? 'S/ '.number_format($equivalente, 2) : '—' }}</dd></div>
                                    <div>
                                        <dt>Solicitud de compra</dt>
                                        <dd>
                                            @if ($detalle->cotizacion->solicitudCompra)
                                                <a href="{{ route('solicitudes-compra.show', $detalle->cotizacion->solicitudCompra) }}" class="table-primary-link">{{ $detalle->cotizacion->solicitudCompra->codigo }}</a>
                                            @else
                                                Sin solicitud
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </x-ui.table-row-details>
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
