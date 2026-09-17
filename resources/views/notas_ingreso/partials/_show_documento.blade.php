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
            <div>
                <dt>Factura vinculada</dt>
                <dd>
                    @if ($nota->facturaProveedor)
                        <a href="{{ route('facturas-proveedor.show', $nota->facturaProveedor) }}">{{ $nota->facturaProveedor->tipo_documento }} {{ $nota->facturaProveedor->serie }}-{{ $nota->facturaProveedor->numero }}</a>
                        <small>Costo real del documento aplicado</small>
                    @elseif ($facturasPosteriores->isNotEmpty())
                        @foreach ($facturasPosteriores as $facturaPosterior)
                            <a href="{{ route('facturas-proveedor.show', $facturaPosterior) }}">{{ $facturaPosterior->tipo_documento }} {{ $facturaPosterior->serie }}-{{ $facturaPosterior->numero }}</a>@if (! $loop->last), @endif
                        @endforeach
                        <small>Factura registrada posteriormente; ajuste de costo trazable</small>
                    @else
                        No vinculada · costo provisional de la OC
                    @endif
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
</section>
