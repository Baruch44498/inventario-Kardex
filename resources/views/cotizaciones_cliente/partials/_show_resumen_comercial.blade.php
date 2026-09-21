    @if ($puedeGestionar && ! $cotizacion->proforma)
        <section class="notice notice--info notice--block">
            <x-ui.icon name="orders" :size="20" />
            <div>
                <strong>Una orden principal con áreas de trabajo</strong>
                <span>Los componentes anteriores se conservan como referencia, pero sus materiales se consolidarán dentro de una sola {{ $codigoTipoOrden ?: 'OM, OS u OP' }} principal.</span>
            </div>
            <a href="{{ route('cotizaciones-cliente.componentes.show', $cotizacion) }}" class="button button--secondary">
                Ver datos anteriores
            </a>
        </section>
    @endif

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Información</p><h2>Datos comerciales</h2></div></header>
            <dl class="supplier-info-grid">
                <div>
                    <dt>Origen</dt>
                    <dd>
                        @if ($cotizacion->proforma)
                            <a href="{{ route('proformas.show', $cotizacion->proforma) }}">{{ $cotizacion->proforma->codigo }}</a>
                        @else
                            Cotización directa de Logística
                        @endif
                    </dd>
                </div>
                <div><dt>Cliente</dt><dd>{{ $cotizacion->cliente_nombre }}</dd></div>
                <div><dt>Documento</dt><dd>{{ $cotizacion->cliente_documento ?: 'No registrado' }}</dd></div>
                <div><dt>Tipo de cliente</dt><dd>{{ $cotizacion->cliente?->tipoCliente?->nombre ?: 'No definido' }}</dd></div>
                @if ($cotizacion->proforma)
                    <div><dt>Destino</dt><dd>Valorización para cobro · Sin OV</dd></div>
                    <div><dt>Vehículo</dt><dd>No aplica</dd></div>
                @else
                    <div>
                        <dt>Orden principal</dt>
                        <dd>{{ $codigoTipoOrden ?: 'Pendiente' }} · {{ $cotizacion->descripcion_trabajo ?: $componentes->first()?->descripcion_componente }}</dd>
                    </div>
                    <div><dt>Vehículo</dt><dd>{{ $cotizacion->vehiculo?->identificadorVisible() ?: 'No aplica' }}</dd></div>
                @endif
                <div><dt>Cotizada por</dt><dd>{{ $cotizacion->cotizador?->nombreVisible() }}</dd></div>
                <div><dt>Cerrada por</dt><dd>{{ $cotizacion->cerrador?->nombreVisible() ?: 'Aún abierta' }}</dd></div>
                @unless ($cotizacion->proforma)
                    <div>
                        <dt>Órdenes vinculadas</dt>
                        <dd>
                            @if ($cotizacion->ordenesOperacion->isNotEmpty())
                                @foreach ($cotizacion->ordenesOperacion as $ordenVinculada)
                                    <a href="{{ route('ordenes-operacion.show', $ordenVinculada) }}">{{ $ordenVinculada->codigo_orden }}</a>{{ ! $loop->last ? ' · ' : '' }}
                                @endforeach
                            @else
                                Aún no generadas
                            @endif
                        </dd>
                    </div>
                @endunless
                <div><dt>Condiciones de pago</dt><dd>{{ $cotizacion->condiciones_pago ?: 'No especificadas' }}</dd></div>
                <div><dt>Condiciones de entrega</dt><dd>{{ $cotizacion->condiciones_entrega ?: 'No especificadas' }}</dd></div>
                <div class="supplier-info-grid__wide"><dt>{{ $cotizacion->proforma ? 'Referencia' : 'Descripción del trabajo' }}</dt><dd>{{ $cotizacion->descripcion_trabajo ?: 'Sin referencia adicional' }}</dd></div>
                @if ($cotizacion->observacion)<div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $cotizacion->observacion }}</dd></div>@endif
            </dl>
        </article>
        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Resumen económico</p>
            <div><span>Subtotal</span><strong><x-ui.money :value="$cotizacion->subtotal" :currency="$cotizacion->moneda" /></strong></div>
            <div><span>IGV</span><strong><x-ui.money :value="$cotizacion->impuesto" :currency="$cotizacion->moneda" /></strong></div>
            <div class="supplier-quote-total-card__main"><span>Total</span><strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong></div>
            @if ($cotizacion->moneda === 'USD')<small>Tipo de cambio (PEN por USD): {{ rtrim(rtrim(number_format((float) $cotizacion->tipo_cambio, 2, '.', ''), '0'), '.') }}</small>@endif
        </article>
    </section>
