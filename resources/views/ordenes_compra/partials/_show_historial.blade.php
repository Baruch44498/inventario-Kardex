<section class="panel supplier-quote-info-panel purchase-order-trace-panel">
    <header class="supplier-panel-heading"><div><p class="eyebrow">Trazabilidad</p><h2>Origen y aprobación</h2></div></header>
    <dl class="supplier-info-grid">
        <div><dt>Origen de compra</dt><dd><span class="badge badge--{{ $orden->origenClase() }}">{{ $orden->origenVisible() }}</span></dd></div>
        <div>
            <dt>Solicitud origen</dt>
            <dd>
                @if ($puedeVerOrigen)
                    <a href="{{ route('solicitudes-compra.show', $solicitud) }}">{{ $solicitud?->codigo }}</a>
                @else
                    {{ $solicitud?->codigo }}
                @endif
            </dd>
        </div>
        <div>
            <dt>Cotización origen</dt>
            <dd>
                @if ($puedeVerOrigen)
                    <a href="{{ route('cotizaciones-proveedor.show', $cotizacion) }}">{{ $cotizacion?->codigo }}</a>
                @else
                    {{ $cotizacion?->codigo }}
                @endif
            </dd>
        </div>
        <div><dt>Emitida por</dt><dd>{{ $orden->emisor?->nombreVisible() ?? '—' }}</dd></div>
        <div><dt>Aprobada por</dt><dd>{{ $orden->aprobador?->nombreVisible() ?? '—' }}</dd></div>
        @if ($orden->esCompraDirecta())
            <div class="supplier-info-grid__wide"><dt>Justificación de la excepción</dt><dd>{{ $orden->justificacion_origen }}</dd></div>
        @endif
    </dl>
    @if ($puedeVerOrigen && ($cotizacion?->archivo_original_path || $cotizacion?->importacionAsistida))
        <div class="purchase-approval-document-action">
            <a class="button button--ghost button--small"
                href="{{ route('cotizaciones-proveedor.documento-original', $cotizacion) }}"
                data-file-download>
                <x-ui.icon name="quotes" :size="16" /> Descargar cotización original
            </a>
        </div>
    @endif
</section>

@if ($orden->estaAnulada())
    <section class="notice notice--danger notice--block">
        <x-ui.icon name="error" :size="20" />
        <div><strong>Orden anulada</strong><p>{{ $orden->motivo_anulacion }} · {{ $orden->anulado_en?->format('d/m/Y H:i') }}</p></div>
    </section>
@elseif ($puedeAnular && $orden->puedeAnularse())
    <section class="supplier-quote-danger-zone purchase-order-cancel-zone">
        <div><p class="eyebrow">Control documental</p><h2>Anular antes de la recepción</h2><p>Una orden con Nota de Ingreso o factura ya no podrá anularse desde aquí.</p></div>
        <form method="POST" action="{{ route('ordenes-compra.anular', $orden) }}" class="supplier-quote-cancel-form" data-confirm="¿Confirmas anular esta orden de compra?">
            @csrf @method('PATCH')
            <input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required placeholder="Motivo de la anulación">
            <button class="button button--danger" type="submit"><x-ui.icon name="error" :size="17" /> Anular orden</button>
        </form>
    </section>
@endif
