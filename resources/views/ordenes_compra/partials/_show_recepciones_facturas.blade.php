<div class="notice notice--{{ $conciliacionFacturas['clase'] }} notice--block purchase-order-invoice-status">
    <x-ui.icon name="invoice" :size="20" />
    <div>
        <strong>{{ $conciliacionFacturas['etiqueta'] }}</strong>
        <p>Facturado: <x-ui.money :value="$orden->totalFacturadoDocumento()" :currency="$orden->moneda" /> · Autorizado: <x-ui.money :value="$orden->total" :currency="$orden->moneda" />. Contabilidad consulta este resultado; no aprueba ni rechaza.</p>
    </div>
    <a href="{{ route('facturas-proveedor.index', ['q' => $orden->codigo]) }}" class="button button--ghost button--small">Ver facturas</a>
</div>

<div class="purchase-order-documents-grid">
    <section class="panel purchase-order-related">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Documentos posteriores</p><h2>Recepciones registradas</h2></div><span class="count-chip">{{ $orden->notasIngreso->count() }}</span></header>
        @if ($orden->notasIngreso->isNotEmpty())
            <div class="purchase-order-related__list">
                @foreach ($orden->notasIngreso->sortByDesc('fecha_ingreso') as $nota)
                    <a href="{{ route('notas-ingreso.show', $nota) }}"><strong>{{ $nota->codigo }}</strong><span>{{ $nota->fecha_ingreso?->format('d/m/Y') }} · <x-ui.quantity :value="$nota->detalles->sum('cantidad')" /> recibido</span></a>
                @endforeach
            </div>
        @else
            <div class="empty-table-state purchase-order-documents-empty">
                <strong>Sin recepciones registradas</strong>
                <span>Las notas de ingreso aparecerán aquí.</span>
            </div>
        @endif
    </section>

    <section class="panel purchase-order-related">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Documentos fiscales</p><h2>Facturas registradas</h2></div><span class="count-chip">{{ $orden->facturasProveedor->count() }}</span></header>
        @if ($orden->facturasProveedor->isNotEmpty())
            <div class="purchase-order-related__list">
                @foreach ($orden->facturasProveedor->sortByDesc('fecha_emision') as $factura)
                    <a href="{{ route('facturas-proveedor.show', $factura) }}"><strong>{{ $factura->tipo_documento }} {{ $factura->numeroVisible() }}</strong><span>{{ $factura->fecha_emision?->format('d/m/Y') }} · <x-ui.money :value="$factura->total" :currency="$factura->moneda" /> · {{ $factura->estadoVisible() }}</span></a>
                @endforeach
            </div>
        @else
            <div class="empty-table-state purchase-order-documents-empty">
                <strong>Sin facturas registradas</strong>
                <span>Los comprobantes vinculados aparecerán aquí.</span>
            </div>
        @endif
    </section>
</div>
