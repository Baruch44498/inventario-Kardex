@if ($orden)
    <section class="order-context-card output-order-context" data-note-origin-context>
        <div class="order-context-card__main"><span class="order-context-card__icon output-order-context__icon"><x-ui.icon name="orders" :size="25" /></span><div><span>Orden activa seleccionada</span><strong>{{ $orden->codigo_orden }}</strong><small>{{ $orden->cliente?->razon_social ?? 'Sin cliente asociado' }}</small></div></div>
        <dl class="order-context-card__facts"><div><dt>Tipo</dt><dd>{{ $orden->tipoOrden?->codigo ?? '—' }}</dd></div><div><dt>Área</dt><dd>{{ $areaTrabajo ?? 'GENERAL' }}</dd></div><div><dt>Apertura</dt><dd>{{ $orden->fecha_apertura?->format('d/m/Y') }}</dd></div><div><dt>Estado</dt><dd><span class="badge badge--info">{{ $orden->estado }}</span></dd></div></dl>
    </section>
@elseif ($proforma)
    <section class="order-context-card output-order-context" data-note-origin-context>
        <div class="order-context-card__main"><span class="order-context-card__icon output-order-context__icon"><x-ui.icon name="clipboard" :size="25" /></span><div><span>Proforma seleccionada</span><strong>{{ $proforma->codigo }}</strong><small>{{ $proforma->cliente?->nombreVisible() ?? 'Sin cliente' }}</small></div></div>
        <dl class="order-context-card__facts"><div><dt>Emisión</dt><dd>{{ $proforma->fecha_emision?->format('d/m/Y') }}</dd></div><div><dt>Estado</dt><dd><span class="badge badge--info">{{ $proforma->estado }}</span></dd></div></dl>
    </section>
@endif
