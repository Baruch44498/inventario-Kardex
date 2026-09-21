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
            <div class="table-wrap">
                <table class="data-table data-table--actions">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Documento proveedor</th>
                            <th>Fecha</th>
                            <th>Moneda</th>
                            <th>Productos</th>
                            <th class="text-right">Total</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cotizaciones as $cotizacion)
                            <tr>
                                <td>
                                    <a href="{{ route('cotizaciones-proveedor.show', $cotizacion->id) }}"
                                        class="table-primary-link">{{ $cotizacion->codigo }}</a>
                                </td>
                                <td>{{ $cotizacion->numero_documento ?: 'Sin número' }}</td>
                                <td>{{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                                <td>{{ $cotizacion->moneda }}</td>
                                <td>{{ $cotizacion->detalles_count }}</td>
                                <td class="text-right">
                                    {{ $cotizacion->simboloMoneda() }}
                                    {{ number_format((float) $cotizacion->total, 2) }}
                                </td>
                                <td>
                                    <span class="badge badge--{{ $cotizacion->estadoClase() }}">
                                        {{ $cotizacion->estadoVisible() }}
                                    </span>
                                </td>
                                <td>
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
