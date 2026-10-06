    @if ($puedeGestionar && ! $cotizacion->proforma && $esMultiComponente)
        <section class="notice notice--info notice--block">
            <x-ui.icon name="orders" :size="20" />
            <div>
                <strong>Una orden principal con áreas de trabajo</strong>
                <span>Esta cotización conserva asignaciones anteriores. Todas las áreas y materiales se consolidan en una sola {{ $codigoTipoOrden ?: 'OM, OS u OP' }} principal.</span>
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

    @if ($puedeGestionar && $cotizacion->esEditable() && ! $cotizacion->proforma && $valorizaDesdeCosteo)
        @php
            $sufijoCosteo = $cotizacion->moneda === 'USD' ? 'dolares' : 'soles';
            $partidasPrecio = $cotizacion->presupuestos()->where('estado', 'VIGENTE')->get();
            $ventaEstimada = round((float) $partidasPrecio->sum('precio_venta_total_'.$sufijoCosteo), 2);
            $costoNetoEstimado = round((float) $partidasPrecio->sum('costo_neto_'.$sufijoCosteo), 2);
        @endphp
        <section class="panel commercial-price-agreement" aria-labelledby="commercial-price-agreement-title">
            <header class="supplier-panel-heading">
                <div>
                    <p class="eyebrow">Uso interno</p>
                    <h2 id="commercial-price-agreement-title">Precio final pactado</h2>
                    <p>La estimación de las partidas se conserva. Al guardar, el precio comercial se reparte entre los conceptos y se mantiene al sincronizar.</p>
                </div>
            </header>
            <dl class="commercial-price-agreement__figures">
                <div><dt>Venta estimada</dt><dd><x-ui.money :value="$ventaEstimada" :currency="$cotizacion->moneda" /></dd></div>
                <div><dt>Costo neto estimado</dt><dd><x-ui.money :value="$costoNetoEstimado" :currency="$cotizacion->moneda" /></dd></div>
                <div><dt>Utilidad estimada con el precio vigente</dt><dd><x-ui.money :value="(float) $cotizacion->subtotal - $costoNetoEstimado" :currency="$cotizacion->moneda" /></dd></div>
                <div><dt>Margen efectivo estimado</dt><dd>{{ $costoNetoEstimado > 0 ? number_format(((float) $cotizacion->subtotal / $costoNetoEstimado - 1) * 100, 2) . '%' : 'N/D' }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('cotizaciones-cliente.precio-final', $cotizacion) }}" class="commercial-price-agreement__form">
                @csrf
                @method('PATCH')
                <label class="form-field" for="precio_final_pactado">
                    <span>Total pactado con IGV ({{ $cotizacion->moneda }})</span>
                    <input id="precio_final_pactado" name="precio_final_pactado" type="number" min="0.01" step="0.01" value="{{ old('contexto_precio') === 'moneda' ? $cotizacion->precio_final_pactado : old('precio_final_pactado', $cotizacion->precio_final_pactado) }}" placeholder="Sin ajuste">
                </label>
                <button type="submit" class="button button--primary">Guardar precio</button>
                @if ($cotizacion->precio_final_pactado !== null)
                    <span>Deja el campo vacío y guarda para recuperar la estimación.</span>
                @endif
                @if (old('contexto_precio') !== 'moneda')
                    @error('precio_final_pactado')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                @endif
            </form>
        </section>

        @php
            $monedaDestino = $cotizacion->moneda === 'PEN' ? 'USD' : 'PEN';
            $tcSugerido = (float) ($cotizacion->tipo_cambio ?: $partidasPrecio->first()?->tipo_cambio);
            $tcFormulario = old('contexto_precio') === 'moneda' ? old('tipo_cambio') : $tcSugerido;
            $precioConvertido = $tcSugerido > 0
                ? round($cotizacion->moneda === 'PEN'
                    ? (float) $cotizacion->total / $tcSugerido
                    : (float) $cotizacion->total * $tcSugerido, 2)
                : null;
            $costoNetoDestino = round((float) $partidasPrecio->sum(
                'costo_neto_'.($monedaDestino === 'USD' ? 'dolares' : 'soles')
            ), 2);
        @endphp
        <section class="panel commercial-currency-change" aria-labelledby="commercial-currency-title">
            <header class="supplier-panel-heading">
                <div>
                    <p class="eyebrow">Acuerdo con el cliente</p>
                    <h2 id="commercial-currency-title">Cambiar moneda de esta versión</h2>
                    <p>El tipo de cambio propone un importe; puedes pactar otro. Se recalculan venta e IGV, conservando el costeo. Si ya compartiste esta versión, ciérrala y crea una nueva antes del cambio.</p>
                </div>
            </header>
            <form method="POST" action="{{ route('cotizaciones-cliente.moneda-comercial', $cotizacion) }}"
                class="commercial-currency-change__form" data-commercial-currency-form
                data-source-currency="{{ $cotizacion->moneda }}" data-source-total="{{ $cotizacion->total }}"
                data-target-cost-net="{{ $costoNetoDestino }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="contexto_precio" value="moneda">
                <div class="commercial-currency-change__fields">
                    <div class="form-field">
                        <span>Nueva moneda</span>
                        <strong class="commercial-currency-change__target">{{ $cotizacion->moneda }} → {{ $monedaDestino === 'USD' ? 'USD — Dólares' : 'PEN — Soles' }}</strong>
                        <input type="hidden" name="moneda" value="{{ $monedaDestino }}" data-currency-target>
                    </div>
                    <label class="form-field" for="tipo_cambio_comercial">
                        <span>Tipo de cambio (PEN por USD)</span>
                        <input id="tipo_cambio_comercial" name="tipo_cambio" type="number" min="0.1" max="100" step="0.000001"
                            value="{{ $tcFormulario ?: '' }}" required data-currency-rate>
                    </label>
                    <label class="form-field" for="precio_final_moneda">
                        <span>Total pactado con IGV ({{ $monedaDestino }})</span>
                        <input id="precio_final_moneda" name="precio_final_pactado" type="number" min="0.01" max="9999999999.99" step="0.01"
                            value="{{ old('contexto_precio') === 'moneda' ? old('precio_final_pactado') : ($precioConvertido !== null ? number_format($precioConvertido, 2, '.', '') : '') }}"
                            required data-currency-price @if (old('contexto_precio') !== 'moneda') data-currency-auto="true" @endif>
                    </label>
                </div>
                <p class="commercial-currency-change__hint">Confirma el tipo de cambio y el total final antes de guardar. Los costos mantienen el TC de cada partida; los Excel y el documento se emitirán en {{ $monedaDestino }}.</p>
                <output class="commercial-currency-change__preview" data-currency-preview aria-live="polite">
                    Total actual: {{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2) }}.
                    @if ($precioConvertido !== null) Equivalente sugerido: {{ $monedaDestino === 'USD' ? 'US$' : 'S/' }} {{ number_format($precioConvertido, 2) }}. @endif
                </output>
                @error('moneda')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                @error('tipo_cambio')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                @if (old('contexto_precio') === 'moneda')
                    @error('precio_final_pactado')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                @endif
                <button type="submit" class="button button--secondary">Aplicar {{ $monedaDestino }} a esta versión</button>
            </form>
        </section>
    @endif
