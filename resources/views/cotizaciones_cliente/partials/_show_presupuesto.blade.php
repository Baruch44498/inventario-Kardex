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
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Material / producto</th><th class="text-right">Cantidad prevista</th><th class="text-right">Referencia interna</th><th class="text-right">Precio cotizado</th><th>IGV</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                            @foreach ($cotizacion->detalles as $detalle)
                                <tr>
                                    <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                                    <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                                    <td class="text-right"><x-ui.money :value="$detalle->precio_sugerido" :currency="$cotizacion->moneda" /></td>
                                    <td class="text-right"><strong><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion->moneda" /></strong>@if ($detalle->precioFueAjustado())<span>Ajustado por Logística</span>@endif</td>
                                    <td>{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                                    <td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" /></strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endunless
