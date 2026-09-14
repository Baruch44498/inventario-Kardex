@if (! $requerimiento->esBorrador() && $seguimientoAbastecimiento['total_lineas'] > 0)
    <section class="panel purchase-requirement-supply-panel">
        <div class="panel-heading purchase-requirement-section-heading">
            <div class="purchase-requirement-section-heading__copy">
                <p class="eyebrow">Abastecimiento</p>
                <div class="purchase-requirement-section-heading__title-row">
                    <h2>Seguimiento por producto</h2>
                    <x-ui.collapsible-notice
                        class="purchase-requirement-section-heading__help"
                        title="Un estado distinto para cada producto"
                        label="Ver cómo funciona"
                    >
                        <span>El requerimiento puede dividirse entre varios proveedores. Por eso cada línea avanza de forma independiente desde la cotización hasta su recepción en Almacén.</span>
                    </x-ui.collapsible-notice>
                </div>
                <p class="purchase-requirement-section-heading__description">Consulta qué falta cotizar, qué ya tiene orden de compra y qué cantidad ingresó realmente.</p>
            </div>
            <div class="purchase-requirement-section-heading__meta">
                <span class="badge badge--{{ $abastecimientoClase }}">
                    {{ number_format((float) $seguimientoAbastecimiento['avance_porcentaje'], 0) }}% recibido
                </span>
            </div>
        </div>

        <section class="summary-strip summary-strip--four" aria-label="Resumen del abastecimiento">
            @foreach ([
                ['Pendiente de cotizar', 'neutral', 'quotes', $seguimientoAbastecimiento['pendientes_cotizar']],
                ['Con ofertas', 'warning', 'banknote', $seguimientoAbastecimiento['cotizadas']],
                ['Con OC por recibir', 'info', 'purchase-order', $seguimientoAbastecimiento['ordenadas'] + $seguimientoAbastecimiento['parciales']],
                ['Recibidos', 'success', 'check-circle', $seguimientoAbastecimiento['recibidas']],
            ] as [$titulo, $tono, $icono, $valor])
                <article class="summary-strip__item">
                    <span class="summary-strip__icon summary-strip__icon--{{ $tono }}"><x-ui.icon :name="$icono" :size="20" /></span>
                    <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
                </article>
            @endforeach
        </section>

        <div class="table-wrap table-wrap--wide">
            <table class="data-table purchase-requirement-detail-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Solicitado</th>
                        <th>Ordenado</th>
                        <th>Recibido</th>
                        <th>Pendiente de recibir</th>
                        <th>Estado</th>
                        <th>Documentos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($seguimientoAbastecimiento['lineas'] as $linea)
                        <tr>
                            <td>
                                <strong>{{ $linea['codigo'] ?? '—' }}</strong>
                                <span>{{ $linea['descripcion'] ?? 'Producto no disponible' }}</span>
                            </td>
                            <td><strong><x-ui.quantity :value="$linea['cantidad_solicitada']" /></strong> {{ $linea['unidad'] }}</td>
                            <td><x-ui.quantity :value="$linea['cantidad_ordenada']" /></td>
                            <td><strong><x-ui.quantity :value="$linea['cantidad_recibida']" /></strong></td>
                            <td><x-ui.quantity :value="$linea['cantidad_pendiente_recibir']" /></td>
                            <td><span class="badge badge--{{ $linea['estado_clase'] }}">{{ $linea['estado_visible'] }}</span></td>
                            <td>
                                @if ($linea['ordenes']->isNotEmpty())
                                    <details class="purchase-requirement-supplier-details">
                                        <summary>{{ $linea['ordenes']->count() }} OC vinculada{{ $linea['ordenes']->count() === 1 ? '' : 's' }}</summary>
                                        <div class="purchase-requirement-supplier-mini-list">
                                            @foreach ($linea['ordenes'] as $orden)
                                                <div>
                                                    <strong><a href="{{ route('ordenes-compra.show', $orden['orden_id']) }}">{{ $orden['orden_codigo'] }}</a></strong>
                                                    <span>{{ $orden['proveedor'] }}</span>
                                                    <small><x-ui.quantity :value="$orden['cantidad_recibida']" /> de <x-ui.quantity :value="$orden['cantidad_ordenada']" /> recibido</small>
                                                    @foreach ($orden['recepciones'] as $recepcion)
                                                        <small><a href="{{ route('notas-ingreso.show', $recepcion->nota_id) }}">{{ $recepcion->nota_codigo }}</a> · <x-ui.quantity :value="$recepcion->cantidad" /></small>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @elseif ($linea['ofertas']->isNotEmpty())
                                    <span>{{ $linea['ofertas']->count() }} oferta{{ $linea['ofertas']->count() === 1 ? '' : 's' }} disponible{{ $linea['ofertas']->count() === 1 ? '' : 's' }}</span>
                                @else
                                    <span class="text-muted">Sin documentos posteriores</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
