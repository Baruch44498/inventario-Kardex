@if ($requerimiento->cotizaciones->isNotEmpty())
    <section class="panel purchase-requirement-quotes-panel">
        <div class="panel-heading purchase-requirement-section-heading">
            <div class="purchase-requirement-section-heading__copy">
                <p class="eyebrow">Compras</p>
                <div class="purchase-requirement-section-heading__title-row">
                    <h2>Cotizaciones recibidas</h2>
                </div>
            </div>
            <div class="purchase-requirement-section-heading__meta" aria-label="Cantidad de cotizaciones recibidas">
                <span class="count-chip">{{ $requerimiento->cotizaciones->count() }}</span>
            </div>
        </div>
        <div class="table-wrap table-wrap--cards">
            <table class="data-table data-table--cards" role="table">
                <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Código</th><th role="columnheader" scope="col">Proveedor</th><th role="columnheader" scope="col">Fecha</th><th role="columnheader" scope="col">Cobertura</th><th role="columnheader" scope="col">Total</th><th role="columnheader" scope="col">Estado</th><th role="columnheader" scope="col">Acción</th></tr></thead>
                <tbody role="rowgroup">
                    @foreach ($requerimiento->cotizaciones as $cotizacion)
                        <tr role="row">
                            <td role="cell" data-label="Código"><strong>{{ $cotizacion->codigo }}</strong></td>
                            <td role="cell" class="table-card__wide" data-label="Proveedor">{{ $cotizacion->proveedor?->nombreVisible() ?? '—' }}</td>
                            <td role="cell" data-label="Fecha">{{ $cotizacion->fecha_cotizacion?->format('d/m/Y') }}</td>
                            <td role="cell" data-label="Cobertura">
                                @php
                                    $cubiertas = $cotizacion->detalles
                                        ->filter(fn ($detalle) => $detalle->tipoVinculacionEfectivo() === 'SOLICITADO')
                                        ->count();
                                    $alternativas = $cotizacion->detalles
                                        ->filter(fn ($detalle) => $detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA')
                                        ->count();
                                @endphp
                                <strong>{{ $cubiertas }}/{{ $requerimiento->detalles->count() }}</strong> producto{{ $cubiertas === 1 ? '' : 's' }}
                                @if ($alternativas > 0)
                                    <span>{{ $alternativas }} alternativa{{ $alternativas === 1 ? '' : 's' }} por revisar</span>
                                @endif
                            </td>
                            <td role="cell" data-label="Total">{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2, '.', ',') }}</td>
                            <td role="cell" data-label="Estado"><span class="badge badge--{{ $cotizacion->estadoClase() }}">{{ $cotizacion->estadoVisible() }}</span></td>
                            <td role="cell" class="table-card__wide" data-label="Acción"><a href="{{ route('cotizaciones-proveedor.show', $cotizacion) }}" class="icon-button" title="Ver cotización"><x-ui.icon name="eye" :size="16" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
