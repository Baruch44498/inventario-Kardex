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

    @php
        $pendientesComerciales = [];
        if (! $cotizacion->cliente_documento) $pendientesComerciales[] = 'documento';
        if (! $cotizacion->cliente?->tipoCliente?->nombre) $pendientesComerciales[] = 'tipo de cliente';
        if (! $cotizacion->cerrador?->nombreVisible()) $pendientesComerciales[] = 'cierre';
        if (! $cotizacion->proforma && $cotizacion->ordenesOperacion->isEmpty()) $pendientesComerciales[] = 'órdenes vinculadas';
        if (! $cotizacion->condiciones_pago) $pendientesComerciales[] = 'condiciones de pago';
        if (! $cotizacion->condiciones_entrega) $pendientesComerciales[] = 'condiciones de entrega';
        $rutaEditarDatos = $puedeGestionar && $cotizacion->esEditable() && ! $valorizaDesdeCosteo
            ? route('cotizaciones-cliente.edit', $cotizacion) : null;
    @endphp
    @if ($puedeGestionar && $cotizacion->esEditable() && ! $cotizacion->proforma && $valorizaDesdeCosteo)
        @php
            $sufijoCosteo = $cotizacion->moneda === 'USD' ? 'dolares' : 'soles';
            $partidasPrecio = $cotizacion->presupuestos()->where('estado', 'VIGENTE')->get();
            $ventaEstimada = round((float) $partidasPrecio->sum('precio_venta_total_'.$sufijoCosteo), 2);
            $costoNetoEstimado = round((float) $partidasPrecio->sum('costo_neto_'.$sufijoCosteo), 2);
            $utilidadVigente = round((float) $cotizacion->subtotal - $costoNetoEstimado, 2);
        @endphp
    @endif

    <section class="supplier-quote-detail-grid commercial-quote-detail__grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Información</p><h2>Datos comerciales</h2></div></header>
            <div class="commercial-quote-detail__info-groups">
                <section class="commercial-quote-detail__info-group" aria-labelledby="commercial-info-client">
                    <h3 id="commercial-info-client">Cliente</h3>
                    <dl class="supplier-info-grid">
                        <div><dt>Cliente</dt><dd>{{ $cotizacion->cliente_nombre }}</dd></div>
                        @if ($cotizacion->cliente_documento)<div><dt>Documento</dt><dd>{{ $cotizacion->cliente_documento }}</dd></div>@endif
                        @if ($cotizacion->cliente?->tipoCliente?->nombre)<div><dt>Tipo de cliente</dt><dd>{{ $cotizacion->cliente->tipoCliente->nombre }}</dd></div>@endif
                    </dl>
                </section>
                <section class="commercial-quote-detail__info-group" aria-labelledby="commercial-info-work">
                    <h3 id="commercial-info-work">Trabajo</h3>
                    <dl class="supplier-info-grid">
                        @if ($cotizacion->proforma)
                            <div><dt>Origen</dt><dd><a href="{{ route('proformas.show', $cotizacion->proforma) }}">{{ $cotizacion->proforma->codigo }}</a></dd></div>
                            <div><dt>Destino</dt><dd>Valorización para cobro · Sin OV</dd></div>
                        @else
                            <div><dt>Origen</dt><dd>Cotización directa de Logística</dd></div>
                            @if ($codigoTipoOrden)<div><dt>Orden principal</dt><dd>{{ $codigoTipoOrden }} · {{ $cotizacion->descripcion_trabajo ?: $componentes->first()?->descripcion_componente }}</dd></div>@endif
                            @if ($cotizacion->vehiculo?->identificadorVisible())<div><dt>Vehículo</dt><dd>{{ $cotizacion->vehiculo->identificadorVisible() }}</dd></div>@endif
                        @endif
                        @if ($cotizacion->descripcion_trabajo)<div class="supplier-info-grid__wide"><dt>{{ $cotizacion->proforma ? 'Referencia' : 'Descripción del trabajo' }}</dt><dd>{{ $cotizacion->descripcion_trabajo }}</dd></div>@endif
                        @if ($cotizacion->observacion)<div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $cotizacion->observacion }}</dd></div>@endif
                    </dl>
                </section>
                <section class="commercial-quote-detail__info-group" aria-labelledby="commercial-info-followup">
                    <h3 id="commercial-info-followup">Seguimiento</h3>
                    <dl class="supplier-info-grid">
                        @if ($cotizacion->cotizador?->nombreVisible())<div><dt>Cotizada por</dt><dd>{{ $cotizacion->cotizador->nombreVisible() }}</dd></div>@endif
                        @if ($cotizacion->cerrador?->nombreVisible())<div><dt>Cerrada por</dt><dd>{{ $cotizacion->cerrador->nombreVisible() }}</dd></div>@endif
                        @unless ($cotizacion->proforma)
                            @if ($cotizacion->ordenesOperacion->isNotEmpty())
                                <div class="supplier-info-grid__wide"><dt>Órdenes vinculadas</dt><dd>
                                    @foreach ($cotizacion->ordenesOperacion as $ordenVinculada)
                                        <a href="{{ route('ordenes-operacion.show', $ordenVinculada) }}">{{ $ordenVinculada->codigo_orden }}</a>{{ ! $loop->last ? ' · ' : '' }}
                                    @endforeach
                                </dd></div>
                            @endif
                        @endunless
                    </dl>
                </section>
                <section class="commercial-quote-detail__info-group" aria-labelledby="commercial-info-conditions">
                    <h3 id="commercial-info-conditions">Condiciones</h3>
                    <dl class="supplier-info-grid">
                        @if ($cotizacion->condiciones_pago)<div><dt>Pago</dt><dd>{{ $cotizacion->condiciones_pago }}</dd></div>@endif
                        @if ($cotizacion->condiciones_entrega)<div><dt>Entrega</dt><dd>{{ $cotizacion->condiciones_entrega }}</dd></div>@endif
                    </dl>
                </section>
            </div>
            @if ($pendientesComerciales)
                <p class="commercial-quote-detail__pending">
                    <strong>Pendiente de completar:</strong> {{ implode(', ', $pendientesComerciales) }}.
                    @if ($rutaEditarDatos)<a href="{{ $rutaEditarDatos }}">Editar datos comerciales</a>@endif
                </p>
            @endif
        </article>
        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Resumen económico</p>
            <div><span>Subtotal</span><strong><x-ui.money :value="$cotizacion->subtotal" :currency="$cotizacion->moneda" /></strong></div>
            <div><span>IGV</span><strong><x-ui.money :value="$cotizacion->impuesto" :currency="$cotizacion->moneda" /></strong></div>
            <div class="supplier-quote-total-card__main"><span>Total</span><strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong></div>
            @if ($cotizacion->moneda === 'USD')<small>Tipo de cambio (PEN por USD): {{ rtrim(rtrim(number_format((float) $cotizacion->tipo_cambio, 2, '.', ''), '0'), '.') }}</small>@endif
            @if ($puedeGestionar && $cotizacion->esEditable() && ! $cotizacion->proforma && $valorizaDesdeCosteo)
                <div class="commercial-quote-detail__internal">
                    <h3>Uso interno</h3>
                    <dl>
                        <div><dt>Costo neto estimado</dt><dd><x-ui.money :value="$costoNetoEstimado" :currency="$cotizacion->moneda" /></dd></div>
                        <div><dt>Utilidad estimada</dt><dd><x-ui.money :value="$utilidadVigente" :currency="$cotizacion->moneda" /></dd></div>
                        <div><dt>Margen sobre costo</dt><dd>{{ $costoNetoEstimado > 0 ? number_format($utilidadVigente / $costoNetoEstimado * 100, 2) . '%' : 'N/D' }}</dd></div>
                        <div><dt>Margen sobre venta</dt><dd>{{ (float) $cotizacion->subtotal > 0 ? number_format($utilidadVigente / (float) $cotizacion->subtotal * 100, 2) . '%' : 'N/D' }}</dd></div>
                    </dl>
                </div>
            @endif
        </article>
    </section>

    @if ($puedeGestionar && $cotizacion->esEditable() && ! $cotizacion->proforma && $valorizaDesdeCosteo)
        <section class="panel commercial-price-agreement" aria-labelledby="commercial-price-agreement-title">
            <header class="supplier-panel-heading">
                <div>
                    <p class="eyebrow">Uso interno</p>
                    <h2 id="commercial-price-agreement-title">Precio final pactado</h2>
                    <p>La estimación de las partidas se conserva. Al guardar, el precio comercial se reparte entre los conceptos y se mantiene al sincronizar.</p>
                </div>
            </header>
            <dl class="commercial-price-agreement__figures">
                <div><dt>Venta con IGV</dt><dd><x-ui.money :value="$ventaEstimada" :currency="$cotizacion->moneda" /></dd><small>Estimación de partidas</small></div>
                <div><dt>Costo neto estimado</dt><dd><x-ui.money :value="$costoNetoEstimado" :currency="$cotizacion->moneda" /></dd></div>
                <div><dt>Utilidad estimada</dt><dd><x-ui.money :value="$utilidadVigente" :currency="$cotizacion->moneda" /></dd><small>Con el precio vigente</small></div>
                <div><dt>Margen sobre costo</dt><dd>{{ $costoNetoEstimado > 0 ? number_format($utilidadVigente / $costoNetoEstimado * 100, 2) . '%' : 'N/D' }}</dd><small>Sobre venta: {{ (float) $cotizacion->subtotal > 0 ? number_format($utilidadVigente / (float) $cotizacion->subtotal * 100, 2) . '%' : 'N/D' }}</small></div>
            </dl>
            <p class="commercial-price-agreement__equation">Venta sin IGV <strong><x-ui.money :value="$cotizacion->subtotal" :currency="$cotizacion->moneda" /></strong> − Costo neto <strong><x-ui.money :value="$costoNetoEstimado" :currency="$cotizacion->moneda" /></strong> = Utilidad <strong><x-ui.money :value="$utilidadVigente" :currency="$cotizacion->moneda" /></strong></p>
            <form method="POST" action="{{ route('cotizaciones-cliente.precio-final', $cotizacion) }}" class="commercial-price-agreement__form"
                data-commercial-price-form data-price-currency="{{ $cotizacion->moneda }}" data-price-cost-net="{{ $costoNetoEstimado }}" data-price-current-total="{{ $cotizacion->total }}"
                data-confirm="El precio pactado quedará por debajo del costo neto estimado. ¿Deseas guardar esta cotización con pérdida?"
                data-confirm-title="Confirmar precio bajo costo" data-confirm-label="Guardar precio" data-confirm-tone="warning">
                @csrf
                @method('PATCH')
                <label class="form-field" for="precio_final_pactado">
                    <span>Total pactado con IGV ({{ $cotizacion->moneda }})</span>
                    <small>Total actual con IGV: <x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></small>
                    <input id="precio_final_pactado" name="precio_final_pactado" type="number" min="0.01" step="0.01" value="{{ old('contexto_precio') === 'moneda' ? $cotizacion->precio_final_pactado : old('precio_final_pactado', $cotizacion->precio_final_pactado) }}" placeholder="Sin ajuste">
                </label>
                <div class="commercial-price-agreement__actions">
                    <output class="commercial-price-agreement__preview" data-price-preview aria-live="polite">Utilidad estimada con el precio vigente: <x-ui.money :value="$utilidadVigente" :currency="$cotizacion->moneda" />. Margen sobre costo: {{ $costoNetoEstimado > 0 ? number_format($utilidadVigente / $costoNetoEstimado * 100, 2) . '%' : 'N/D' }}.</output>
                    <button type="submit" class="button button--primary">Guardar precio</button>
                </div>
                @if ($cotizacion->precio_final_pactado !== null)
                    <small class="commercial-price-agreement__restore">Deja el campo vacío y guarda para recuperar la estimación.</small>
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
        <details class="panel commercial-currency-change" @if (old('contexto_precio') === 'moneda' || $errors->has('moneda') || $errors->has('tipo_cambio') || $errors->has('precio_final_pactado')) open @endif>
            <summary class="commercial-currency-change__summary" id="commercial-currency-title">
                <span><small>Acuerdo con el cliente</small><strong>Cambiar moneda de esta versión</strong></span>
                <span aria-hidden="true">▾</span>
            </summary>
            <div class="commercial-currency-change__body">
                <div class="notice notice--warning notice--block"><x-ui.icon name="warning" :size="19" /><span>Si ya compartiste esta versión, ciérrala y crea una nueva antes del cambio.</span></div>
                <p class="commercial-currency-change__hint">El tipo de cambio propone un importe; puedes pactar otro. Se recalculan venta e IGV conservando el costeo.</p>
            <form method="POST" action="{{ route('cotizaciones-cliente.moneda-comercial', $cotizacion) }}"
                class="commercial-currency-change__form" data-commercial-currency-form
                data-source-currency="{{ $cotizacion->moneda }}" data-source-total="{{ $cotizacion->total }}"
                data-target-cost-net="{{ $costoNetoDestino }}"
                data-confirm="Se recalcularán la venta y el IGV. Los Excel y el documento se emitirán en {{ $monedaDestino }} para esta versión. ¿Deseas aplicar el cambio?"
                data-confirm-title="Confirmar cambio de moneda" data-confirm-label="Aplicar {{ $monedaDestino }}" data-confirm-tone="warning">
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
                <p class="commercial-currency-change__hint">Confirma el tipo de cambio y el total final antes de guardar. Los costos mantienen el TC de cada partida.</p>
                <div class="commercial-currency-change__preview">
                    <table><caption>Actual → Después</caption><thead><tr><th scope="col">Concepto</th><th scope="col">Actual</th><th scope="col">Después</th></tr></thead><tbody>
                        <tr><th scope="row">Total con IGV</th><td><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></td><td data-currency-after-total>{{ $precioConvertido !== null ? ($monedaDestino === 'USD' ? 'US$' : 'S/') . ' ' . number_format($precioConvertido, 2) : 'Ingresa TC' }}</td></tr>
                        <tr><th scope="row">Utilidad estimada <small>Antes de otros gastos</small></th><td><x-ui.money :value="$utilidadVigente" :currency="$cotizacion->moneda" /></td><td data-currency-after-profit>—</td></tr>
                        <tr><th scope="row">Tipo de cambio <small>PEN por USD</small></th><td>{{ $cotizacion->tipo_cambio ? number_format((float) $cotizacion->tipo_cambio, 2) : '—' }}</td><td data-currency-after-rate>{{ $tcFormulario ? number_format((float) $tcFormulario, 2) : '—' }}</td></tr>
                    </tbody></table>
                    <p>Equivalente sugerido: <strong data-currency-equivalent>{{ $precioConvertido !== null ? ($monedaDestino === 'USD' ? 'US$' : 'S/') . ' ' . number_format($precioConvertido, 2) : 'Ingresa TC' }}</strong></p>
                    <output class="commercial-currency-change__announcement sr-only" data-currency-preview aria-live="polite">Actual: {{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2) }}.</output>
                </div>
                @error('moneda')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                @error('tipo_cambio')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                @if (old('contexto_precio') === 'moneda')
                    @error('precio_final_pactado')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                @endif
                <button type="submit" class="button button--primary">Aplicar {{ $monedaDestino }} a esta versión</button>
            </form>
            </div>
        </details>
    @endif
