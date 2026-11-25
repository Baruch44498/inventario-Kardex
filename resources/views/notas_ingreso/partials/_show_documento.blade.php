<section class="panel entry-document-card entry-document-panel">
    <div class="panel-heading">
        <p class="eyebrow">Documento adjunto</p>
        <h2>Documento y referencia del ingreso</h2>
        <p>Información documental asociada a la entrada física.</p>
    </div>

    <dl class="detail-list detail-list--entry">
        <div><dt>Fecha de ingreso</dt><dd>{{ $nota->fecha_ingreso?->format('d/m/Y') }}</dd></div>
        <div><dt>Motivo</dt><dd>{{ $nota->motivoVisible() }}</dd></div>
        <div>
            <dt>Documento origen</dt>
            <dd>
                @if ($nota->ordenCompra)
                    <a href="{{ route('ordenes-compra.show', $nota->ordenCompra) }}">{{ $origen }}</a>
                @else
                    {{ $origen }}
                @endif
            </dd>
        </div>

        @if ($nota->ordenCompra)
            <div><dt>Proveedor</dt><dd>{{ $nota->ordenCompra?->proveedor?->razon_social ?? '—' }}</dd></div>
            <div><dt>RUC</dt><dd>{{ $nota->ordenCompra?->proveedor?->ruc ?? '—' }}</dd></div>
            <div><dt>Guía de remisión</dt><dd>{{ $nota->numero_guia_remision ?: 'No registrada' }}</dd></div>
            <div class="entry-billing-status">
                <dt>Facturación vinculada a esta recepción</dt>
                <dd>
                    <strong>{{ $estadoFacturacionNota }}</strong>
                    @if ($cantidadFacturada > 0 && $cantidadRecibida - $cantidadFacturada > 0.0001)
                        <small><x-ui.quantity :value="$cantidadFacturada" /> de <x-ui.quantity :value="$cantidadRecibida" /> unidades facturadas</small>
                    @elseif ($cantidadFacturada <= 0)
                        <small>Costo provisional de la OC</small>
                    @endif
                    @if ($facturacionCompletaEnOrdenSinVinculo)
                        <small>La OC ya no tiene saldo facturable para estos productos. Este recuento solo incluye facturas vinculadas directamente a la recepción.</small>
                    @endif
                    @foreach ($facturasPosteriores as $facturaPosterior)
                        <a href="{{ route('facturas-proveedor.show', $facturaPosterior) }}">{{ $facturaPosterior->tipo_documento }} {{ $facturaPosterior->numeroVisible() }}</a>@if (! $loop->last), @endif
                    @endforeach
                </dd>
            </div>
        @elseif ($nota->notaSalidaOrigen)
            <div><dt>Salida original</dt><dd>{{ $nota->notaSalidaOrigen?->codigo }}</dd></div>
            <div><dt>Destino original</dt><dd>{{ $nota->notaSalidaOrigen?->ordenOperacion?->codigo_orden ?? $nota->notaSalidaOrigen?->proforma?->codigo ?? $nota->notaSalidaOrigen?->motivoVisible() }}</dd></div>
            <div><dt>Área</dt><dd>{{ $nota->area_trabajo ?: 'GENERAL' }}</dd></div>
            <div><dt>Devuelto por</dt><dd>{{ $nota->devuelto_por_nombre ?: 'No registrado' }}@if ($nota->devuelto_por_dni) · DNI {{ $nota->devuelto_por_dni }} @endif</dd></div>
        @elseif ($nota->proforma)
            <div><dt>Cliente</dt><dd>{{ $nota->proforma?->cliente?->razon_social ?? '—' }}</dd></div>
        @endif
    </dl>
    @if ($puedeRegistrarFactura)
        <a href="{{ route('facturas-proveedor.create', $nota->ordenCompra) }}" class="button button--primary entry-document__next">Registrar factura de lo recibido</a>
    @endif
</section>
