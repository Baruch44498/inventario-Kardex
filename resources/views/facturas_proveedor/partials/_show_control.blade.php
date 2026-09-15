<section class="panel supplier-invoice-control-panel">
    <div class="panel-heading"><p class="eyebrow">Control documental</p><h2>Archivo e historial del registro</h2></div>
    <dl class="supplier-info-grid">
        <div><dt>Registrada por</dt><dd>{{ $factura->registrador?->nombreVisible() }}</dd></div>
        <div><dt>Estado</dt><dd><span class="badge badge--{{ $factura->estadoClase() }}">{{ $factura->estadoVisible() }}</span></dd></div>
        <div class="supplier-info-grid__wide"><dt>Archivo original</dt><dd>{{ $factura->archivo_original_nombre }}</dd></div>
        @if ($factura->observacion)
            <div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $factura->observacion }}</dd></div>
        @endif
    </dl>
    <div class="supplier-invoice-document-actions">
        <a href="{{ route('facturas-proveedor.documento-original', $factura) }}" class="button button--ghost button--small" data-file-download><x-ui.icon name="download" :size="16" /> Descargar comprobante original</a>
        <a href="{{ route('ordenes-compra.show', $factura->ordenCompra) }}" class="button button--ghost button--small">Ver Orden de Compra</a>
    </div>
</section>

@if ($factura->estaAnulada())
    <section class="notice notice--danger notice--block"><x-ui.icon name="error" :size="18" /><div><strong>Factura anulada</strong><p>{{ $factura->motivo_anulacion }} · {{ $factura->anulado_en?->format('d/m/Y H:i') }}</p></div></section>
@elseif ($puedeAnular && ! $factura->tieneRecepcionFisica())
    <section class="supplier-quote-danger-zone">
        <div><p class="eyebrow">Control documental</p><h2>Anular registro incorrecto</h2><p>El archivo se conserva. Una factura vinculada a recepción ya no puede anularse.</p></div>
        <form method="POST" action="{{ route('facturas-proveedor.anular', $factura) }}" data-confirm="¿Confirmas anular esta factura?">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            @method('PATCH')
            <input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required placeholder="Motivo de la anulación">
            <button class="button button--danger" type="submit">Anular factura</button>
        </form>
    </section>
@endif
