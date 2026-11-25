    @if ($puedeGestionar)
        <section class="notice notice--info notice--block">
            <x-ui.icon name="quotes" :size="20" />
            <div>
                <strong>Presupuesto interno de ejecución</strong>
                <span>Registra materiales, personal, servicios, transporte, viáticos y consumibles en PEN o USD. Esta información nunca se muestra en el documento del cliente.</span>
            </div>
            <a href="{{ route('cotizaciones-cliente.presupuesto.show', $cotizacion) }}" class="button button--secondary">
                Gestionar presupuesto
            </a>
        </section>
    @endif

    @unless ($cotizacion->proforma)
        @if ($puedeGestionar)
            <section class="panel supplier-quote-detail-lines commercial-internal-composition">
                <header class="supplier-panel-heading">
                    <div>
                        <p class="eyebrow">Uso interno · No incluir en documento al cliente</p>
                        <h2>{{ $valorizaDesdeCosteo ? 'Detalle comercial generado desde costeo' : ($esProduccion ? 'Composición interna valorizada' : 'Control interno de valorización') }}</h2>
                        <p>{{ $valorizaDesdeCosteo
                            ? 'La composición completa permanece en la hoja de costos. Sus materiales catalogados generan los requerimientos iniciales de cada orden.'
                            : ($esProduccion
                                ? 'Esta previsión forma el precio de la fabricación y genera la lista inicial de materiales de la OP.'
                                : 'Esta vista conserva la referencia sugerida y el control interno de Logística.') }}</p>
                    </div>
                </header>
                <div class="table-wrap table-wrap--cards">
                    <table class="data-table data-table--cards" role="table">
                        <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Material / producto</th><th role="columnheader" scope="col" class="text-right">Cantidad prevista</th><th role="columnheader" scope="col" class="text-right">Referencia interna</th><th role="columnheader" scope="col" class="text-right">Precio cotizado</th><th role="columnheader" scope="col">IGV</th><th role="columnheader" scope="col" class="text-right">Total</th></tr></thead>
                        <tbody role="rowgroup">
                            @foreach ($cotizacion->detalles as $detalle)
                                <tr role="row">
                                    <td role="cell" class="table-card__wide" data-label="Material / producto"><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                                    <td role="cell" class="text-right" data-label="Cantidad prevista"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                                    <td role="cell" class="text-right" data-label="Referencia interna"><x-ui.money :value="$detalle->precio_sugerido" :currency="$cotizacion->moneda" /></td>
                                    <td role="cell" class="text-right" data-label="Precio cotizado"><strong><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion->moneda" /></strong>@if ($detalle->precioFueAjustado())<span>Ajustado por Logística</span>@endif</td>
                                    <td role="cell" data-label="IGV">{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                                    <td role="cell" class="text-right" data-label="Total"><strong><x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" /></strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endunless
