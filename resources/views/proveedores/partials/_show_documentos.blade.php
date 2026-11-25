    <section class="panel supplier-related-panel">
        <header class="supplier-panel-heading supplier-panel-heading--split">
            <div>
                <p class="eyebrow">Documentos registrados</p>
                <h2>Cotizaciones del proveedor</h2>
            </div>
            <a href="{{ route('cotizaciones-proveedor.create', ['proveedor_id' => $proveedor->id]) }}"
                class="button button--primary button--small">
                <x-ui.icon name="plus" :size="16" /> Nueva cotización
            </a>
        </header>

        @if ($cotizaciones->isNotEmpty())
            <div class="table-wrap table-wrap--cards">
                <table class="data-table data-table--actions data-table--cards" role="table">
                    <thead role="rowgroup">
                        <tr role="row">
                            <th role="columnheader" scope="col">Código</th>
                            <th role="columnheader" scope="col">Documento proveedor</th>
                            <th role="columnheader" scope="col">Fecha</th>
                            <th role="columnheader" scope="col">Moneda</th>
                            <th role="columnheader" scope="col">Productos</th>
                            <th role="columnheader" scope="col" class="text-right">Total</th>
                            <th role="columnheader" scope="col">Estado</th>
                            <th role="columnheader" scope="col">Acción</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        @foreach ($cotizaciones as $cotizacion)
                            <tr role="row">
                                <td role="cell" class="table-card__wide" data-label="Código">
                                    <a href="{{ route('cotizaciones-proveedor.show', $cotizacion->id) }}"
                                        class="table-primary-link">{{ $cotizacion->codigo }}</a>
                                </td>
                                <td role="cell" data-label="Documento proveedor">{{ $cotizacion->numero_documento ?: 'Sin número' }}</td>
                                <td role="cell" data-label="Fecha">{{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                                <td role="cell" data-label="Moneda">{{ $cotizacion->moneda }}</td>
                                <td role="cell" data-label="Productos">{{ $cotizacion->detalles_count }}</td>
                                <td role="cell" class="text-right" data-label="Total">
                                    {{ $cotizacion->simboloMoneda() }}
                                    {{ number_format((float) $cotizacion->total, 2) }}
                                </td>
                                <td role="cell" data-label="Estado">
                                    <span class="badge badge--{{ $cotizacion->estadoClase() }}">
                                        {{ $cotizacion->estadoVisible() }}
                                    </span>
                                </td>
                                <td role="cell" class="table-card__wide" data-label="Acción">
                                    <a href="{{ route('cotizaciones-proveedor.show', $cotizacion->id) }}"
                                        class="button button--ghost button--small">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$cotizaciones" />
        @else
            <div class="empty-table-state supplier-related-empty">
                <span class="empty-state__icon"><x-ui.icon name="quotes" :size="29" /></span>
                <strong>Sin cotizaciones registradas</strong>
                <span>Registra la primera oferta recibida de este proveedor.</span>
            </div>
        @endif
    </section>
