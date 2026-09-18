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
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Código</th><th>Proveedor</th><th>Fecha</th><th>Cobertura</th><th>Total</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                    @foreach ($requerimiento->cotizaciones as $cotizacion)
                        <tr>
                            <td><strong>{{ $cotizacion->codigo }}</strong></td>
                            <td>{{ $cotizacion->proveedor?->nombreVisible() ?? '—' }}</td>
                            <td>{{ $cotizacion->fecha_cotizacion?->format('d/m/Y') }}</td>
                            <td>
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
                            <td>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2, '.', ',') }}</td>
                            <td><span class="badge badge--{{ $cotizacion->estadoClase() }}">{{ $cotizacion->estadoVisible() }}</span></td>
                            <td><a href="{{ route('cotizaciones-proveedor.show', $cotizacion) }}" class="icon-button" title="Ver cotización"><x-ui.icon name="eye" :size="16" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
