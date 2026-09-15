<section class="supplier-invoice-detail-grid">
    <article class="panel">
        <div class="panel-heading"><p class="eyebrow">Documento fiscal</p><h2>Resumen del comprobante</h2></div>
        <dl class="supplier-info-grid">
            <div><dt>Proveedor</dt><dd>{{ $factura->proveedor?->nombreVisible() }}</dd></div>
            <div><dt>RUC</dt><dd>{{ $factura->proveedor?->ruc }}</dd></div>
            <div><dt>Emisión</dt><dd>{{ $factura->fecha_emision?->format('d/m/Y') }}</dd></div>
            <div><dt>Vencimiento</dt><dd>{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? 'No registrado' }}</dd></div>
            <div><dt>Moneda</dt><dd>{{ $factura->moneda }}</dd></div>
            <div><dt>Tipo de cambio</dt><dd>{{ $factura->moneda === 'USD' ? number_format((float) $factura->tipo_cambio, 2) : 'No aplica' }}</dd></div>
            <div class="supplier-info-grid__wide"><dt>Orden de Compra</dt><dd><a href="{{ route('ordenes-compra.show', $factura->ordenCompra) }}">{{ $factura->ordenCompra?->codigo }}</a></dd></div>
        </dl>
    </article>

    <article class="panel supplier-invoice-fiscal-card">
        <p class="eyebrow">Resumen fiscal</p>
        <div><span>Base imponible</span><strong><x-ui.money :value="$factura->subtotal" :currency="$factura->moneda" /></strong></div>
        <div><span>{{ $factura->permiteCreditoFiscal() ? 'IGV 18% / crédito fiscal' : 'IGV informado (sin crédito fiscal)' }}</span><strong><x-ui.money :value="$factura->impuesto" :currency="$factura->moneda" /></strong></div>
        @if (abs((float) $factura->ajuste_redondeo) >= 0.005)
            <div><span>Ajuste de redondeo</span><strong><x-ui.money :value="$factura->ajuste_redondeo" :currency="$factura->moneda" /></strong></div>
        @endif
        <div class="supplier-invoice-fiscal-card__total"><span>Total pagado</span><strong><x-ui.money :value="$factura->total" :currency="$factura->moneda" /></strong></div>
        <small>Equivalente para Almacén: S/ {{ number_format($factura->totalEnSoles(), 2, '.', ',') }}</small>
        @if (abs($factura->ajusteInventarioSoles()) >= 0.00005)
            <small>Ajuste aplicado al inventario: S/ {{ number_format($factura->ajusteInventarioSoles(), 2, '.', ',') }}</small>
        @endif
        @if (abs($factura->diferenciaContableSoles()) >= 0.00005)
            <small>Diferencia pendiente contable: S/ {{ number_format($factura->diferenciaContableSoles(), 2, '.', ',') }}</small>
        @endif
    </article>
</section>

<div class="notice notice--{{ $conciliacion['clase'] }} notice--block">
    <x-ui.icon name="info" :size="19" />
    <div><strong>{{ $conciliacion['etiqueta'] }}</strong><p>Total acumulado facturado: <x-ui.money :value="$factura->ordenCompra->totalFacturadoDocumento()" :currency="$factura->ordenCompra->moneda" /> · Total autorizado: <x-ui.money :value="$factura->ordenCompra->total" :currency="$factura->ordenCompra->moneda" />.</p></div>
</div>
