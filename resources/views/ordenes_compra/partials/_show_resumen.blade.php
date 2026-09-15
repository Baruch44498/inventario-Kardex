@if ($orden->esCompraDirecta())
    <div class="notice notice--{{ $orden->origenClase() }} notice--block">
        <x-ui.icon name="warning" :size="20" />
        <div>
            <strong>{{ $orden->origenVisible() }} sin requerimiento previo</strong>
            <p>{{ $orden->justificacion_origen }}</p>
        </div>
    </div>
@endif

<section class="summary-strip summary-strip--four purchase-order-receipt-summary" aria-label="Resumen de recepción">
    @foreach ([
        ['Líneas ordenadas', 'info', 'purchase-order', $orden->detalles->count()],
        ['Líneas completas', 'success', 'check-circle', $lineasCompletas],
        ['Líneas pendientes', 'warning', 'inventory', $lineasPendientes],
        ['Avance promedio', 'info', 'activity', number_format($avancePromedio, 0).'%'],
    ] as [$titulo, $tono, $icono, $valor])
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--{{ $tono }}"><x-ui.icon :name="$icono" :size="20" /></span>
            <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
        </article>
    @endforeach
</section>

<section class="supplier-quote-detail-grid purchase-order-summary-grid">
    <article class="panel supplier-quote-info-panel">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Información principal</p><h2>Datos de la compra</h2></div></header>
        <dl class="supplier-info-grid">
            <div><dt>Proveedor</dt><dd>{{ $orden->proveedor?->nombreVisible() }}</dd></div>
            <div><dt>RUC</dt><dd>{{ $orden->proveedor?->ruc }}</dd></div>
            <div><dt>Documento proveedor</dt><dd>{{ $orden->numero_documento_proveedor ?: 'No especificado' }}</dd></div>
            <div><dt>Moneda</dt><dd>{{ $orden->moneda }}</dd></div>
            <div><dt>Fecha de emisión</dt><dd>{{ $orden->fecha_emision?->format('d/m/Y') }}</dd></div>
            <div>
                <dt>Entrega requerida</dt>
                <dd>
                    {{ $orden->fecha_entrega_requerida?->format('d/m/Y') ?? 'No especificada' }}
                    @if ($orden->permiteRecepcion())
                        · <span class="badge badge--{{ $orden->situacionEntregaClase() }}">{{ $orden->detallePlazoEntrega() }}</span>
                    @endif
                </dd>
            </div>
            <div class="supplier-info-grid__wide"><dt>Condiciones</dt><dd>Pago: {{ $orden->condiciones_pago ?: 'No especificado' }} · Entrega: {{ $orden->condiciones_entrega ?: 'No especificada' }}</dd></div>
            @if ($orden->observacion)
                <div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $orden->observacion }}</dd></div>
            @endif
        </dl>
    </article>

    <article class="panel supplier-quote-total-card">
        <p class="eyebrow">Importe de la orden</p>
        <div><span>Subtotal sin IGV</span><strong><x-ui.money :value="$orden->subtotal" :currency="$orden->moneda" /></strong></div>
        <div><span>IGV</span><strong><x-ui.money :value="$orden->impuesto" :currency="$orden->moneda" /></strong></div>
        @if ($orden->tieneAjusteRedondeo())
            <div><span>Ajuste documental</span><strong>{{ (float) $orden->ajuste_redondeo > 0 ? '+' : '−' }} <x-ui.money :value="abs((float) $orden->ajuste_redondeo)" :currency="$orden->moneda" /></strong></div>
        @endif
        <div class="supplier-quote-total-card__main"><span>Total autorizado</span><strong><x-ui.money :value="$orden->total" :currency="$orden->moneda" /></strong></div>
        @if ($orden->moneda === 'USD')
            <small>Tipo de cambio referencial: {{ number_format((float) $orden->tipo_cambio, 2) }}</small>
        @endif
    </article>
</section>
