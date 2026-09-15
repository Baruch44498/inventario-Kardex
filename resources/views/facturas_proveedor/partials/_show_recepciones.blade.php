<section class="panel supplier-invoice-receipts-panel">
    <div class="panel-heading panel-heading--split">
        <div><p class="eyebrow">Trazabilidad física</p><h2>Notas de Ingreso conciliadas</h2><p>La factura no modifica el stock; las existencias se actualizan mediante recepciones confirmadas.</p></div>
        @if ($puedeRegistrarIngreso && ! $factura->estaAnulada() && ! $factura->tieneRecepcionFisica() && $factura->ordenCompra->permiteRecepcion())
            <a href="{{ route('notas-ingreso.create', ['motivo_ingreso' => 'COMPRA', 'orden_compra_id' => $factura->orden_compra_id, 'factura_proveedor_id' => $factura->id]) }}" class="button button--primary button--small"><x-ui.icon name="entry" :size="16" /> Completar recepción anterior</a>
        @endif
    </div>

    @if ($notasVinculadas->isNotEmpty())
        <div class="purchase-order-related__list">
            @foreach ($notasVinculadas as $nota)
                <a href="{{ route('notas-ingreso.show', $nota) }}"><strong>{{ $nota->codigo }}</strong><span>{{ $nota->fecha_ingreso?->format('d/m/Y') }} · <x-ui.quantity :value="$nota->detalles->sum('cantidad')" /> recibido</span></a>
            @endforeach
        </div>
    @else
        <div class="empty-table-state">
            <span class="empty-state__icon"><x-ui.icon name="entry" :size="28" /></span>
            <strong>Factura pendiente de recepción</strong>
            <span>Cuando llegue la mercadería, Almacén podrá registrar la Nota de Ingreso vinculando esta factura.</span>
        </div>
    @endif
</section>
