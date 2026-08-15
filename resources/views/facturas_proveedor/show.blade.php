@extends('layouts.app')

@section('title', $factura->tipo_documento.' '.$factura->numeroVisible())
@section('page-kicker', 'Factura de proveedor')
@section('page-title', $factura->numeroVisible())

@section('content')
    <a href="{{ route('facturas-proveedor.index') }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a facturas</a>

    <section class="module-header supplier-invoice-show-header">
        <div><p class="eyebrow">{{ $factura->tipo_documento }}</p><h1>{{ $factura->numeroVisible() }}</h1><p>{{ $factura->proveedor?->nombreVisible() }} · OC {{ $factura->ordenCompra?->codigo }}</p></div>
        <div class="module-header__actions"><span class="badge badge--{{ $conciliacion['clase'] }}">{{ $conciliacion['etiqueta'] }}</span><span class="badge badge--{{ $factura->estadoClase() }}">{{ $factura->estadoVisible() }}</span></div>
    </section>

    <section class="supplier-invoice-detail-grid">
        <article class="panel">
            <div class="panel-heading"><p class="eyebrow">Documento fiscal</p><h2>Datos registrados</h2></div>
            <dl class="supplier-info-grid">
                <div><dt>Proveedor</dt><dd>{{ $factura->proveedor?->nombreVisible() }}</dd></div><div><dt>RUC</dt><dd>{{ $factura->proveedor?->ruc }}</dd></div>
                <div><dt>Emisión</dt><dd>{{ $factura->fecha_emision?->format('d/m/Y') }}</dd></div><div><dt>Vencimiento</dt><dd>{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? 'No registrado' }}</dd></div>
                <div><dt>Moneda</dt><dd>{{ $factura->moneda }}</dd></div><div><dt>Tipo de cambio</dt><dd>{{ $factura->moneda === 'USD' ? number_format((float) $factura->tipo_cambio, 2) : 'No aplica' }}</dd></div>
                <div><dt>Registrada por</dt><dd>{{ $factura->registrador?->nombreVisible() }}</dd></div><div><dt>Archivo</dt><dd>{{ $factura->archivo_original_nombre }}</dd></div>
                @if ($factura->observacion)<div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $factura->observacion }}</dd></div>@endif
            </dl>
            <div class="supplier-invoice-document-actions"><a href="{{ route('facturas-proveedor.documento-original', $factura) }}" class="button button--ghost button--small"><x-ui.icon name="download" :size="16" /> Descargar original</a><a href="{{ route('ordenes-compra.show', $factura->ordenCompra) }}" class="button button--ghost button--small">Ver Orden de Compra</a></div>
        </article>

        <article class="panel supplier-invoice-fiscal-card">
            <p class="eyebrow">Resumen fiscal</p>
            <div><span>Base imponible</span><strong><x-ui.money :value="$factura->subtotal" :currency="$factura->moneda" /></strong></div>
            <div><span>{{ $factura->permiteCreditoFiscal() ? 'IGV 18% / crédito fiscal' : 'IGV informado (sin crédito fiscal)' }}</span><strong><x-ui.money :value="$factura->impuesto" :currency="$factura->moneda" /></strong></div>
            @if (abs((float) $factura->ajuste_redondeo) >= 0.005)<div><span>Ajuste de redondeo</span><strong><x-ui.money :value="$factura->ajuste_redondeo" :currency="$factura->moneda" /></strong></div>@endif
            <div class="supplier-invoice-fiscal-card__total"><span>Total pagado</span><strong><x-ui.money :value="$factura->total" :currency="$factura->moneda" /></strong></div>
            <small>Equivalente para Almacén: S/ {{ number_format($factura->totalEnSoles(), 2, '.', ',') }}</small>
            @if (abs($factura->ajusteInventarioSoles()) >= 0.00005)<small>Ajuste aplicado al inventario: S/ {{ number_format($factura->ajusteInventarioSoles(), 2, '.', ',') }}</small>@endif
            @if (abs($factura->diferenciaContableSoles()) >= 0.00005)<small>Diferencia pendiente contable: S/ {{ number_format($factura->diferenciaContableSoles(), 2, '.', ',') }}</small>@endif
        </article>
    </section>

    <div class="notice notice--{{ $conciliacion['clase'] }} notice--block"><x-ui.icon name="info" :size="19" /><div><strong>{{ $conciliacion['etiqueta'] }}</strong><p>Total acumulado facturado: <x-ui.money :value="$factura->ordenCompra->totalFacturadoDocumento()" :currency="$factura->ordenCompra->moneda" /> · Total autorizado: <x-ui.money :value="$factura->ordenCompra->total" :currency="$factura->ordenCompra->moneda" />.</p></div></div>

    <section class="panel supplier-invoice-lines-panel">
        <div class="panel-heading"><p class="eyebrow">Productos</p><h2>Detalle facturado</h2></div>
        <div class="table-wrap table-wrap--wide"><table class="data-table supplier-invoice-detail-table"><thead><tr><th>Producto</th><th>Recepción conciliada</th><th class="text-right">Cantidad</th><th class="text-right">Base unitaria</th><th class="text-right">Base</th><th class="text-right">IGV</th><th class="text-right">Total con IGV</th></tr></thead><tbody>
            @foreach ($factura->detalles as $detalle)
                <tr><td><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->descripcion }}</span></td><td>@if ($detalle->notaIngresoDetalle?->notaIngreso)<a href="{{ route('notas-ingreso.show', $detalle->notaIngresoDetalle->notaIngreso) }}"><strong>{{ $detalle->notaIngresoDetalle->notaIngreso->codigo }}</strong><span>{{ $detalle->notaIngresoDetalle->notaIngreso->fecha_ingreso?->format('d/m/Y') }}</span></a>@elseif($factura->notasIngreso->isNotEmpty())<span>Recepción vinculada desde la factura</span>@else<span>Pendiente de recepción</span>@endif</td><td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /></td><td class="text-right"><x-ui.money :value="$detalle->precio_unitario" :currency="$factura->moneda" /></td><td class="text-right"><x-ui.money :value="$detalle->subtotal" :currency="$factura->moneda" /></td><td class="text-right"><x-ui.money :value="$detalle->impuesto" :currency="$factura->moneda" /></td><td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$factura->moneda" /></strong></td></tr>
            @endforeach
        </tbody></table></div>
    </section>

    <section class="panel supplier-invoice-receipts-panel">
        @php($notasVinculadas = $factura->notasIngreso->merge($notasConciliadas)->unique('id')->values())
        <div class="panel-heading panel-heading--split"><div><p class="eyebrow">Trazabilidad física</p><h2>Notas de Ingreso conciliadas</h2></div>@if ($puedeRegistrarIngreso && ! $factura->estaAnulada() && ! $factura->tieneRecepcionFisica() && $factura->ordenCompra->permiteRecepcion())<a href="{{ route('notas-ingreso.create', ['motivo_ingreso' => 'COMPRA', 'orden_compra_id' => $factura->orden_compra_id, 'factura_proveedor_id' => $factura->id]) }}" class="button button--primary button--small"><x-ui.icon name="entry" :size="16" /> Completar recepción anterior</a>@endif</div>
        @if ($notasVinculadas->isNotEmpty())<div class="purchase-order-related__list">@foreach ($notasVinculadas as $nota)<a href="{{ route('notas-ingreso.show', $nota) }}"><strong>{{ $nota->codigo }}</strong><span>{{ $nota->fecha_ingreso?->format('d/m/Y') }} · <x-ui.quantity :value="$nota->detalles->sum('cantidad')" /> recibido</span></a>@endforeach</div>@else<div class="empty-table-state"><span class="empty-state__icon"><x-ui.icon name="entry" :size="28" /></span><strong>Factura pendiente de recepción</strong><span>Cuando llegue la mercadería, Almacén podrá registrar la Nota de Ingreso vinculando esta factura.</span></div>@endif
    </section>

    @if ($factura->estaAnulada())
        <section class="notice notice--danger notice--block"><x-ui.icon name="error" :size="18" /><div><strong>Factura anulada</strong><p>{{ $factura->motivo_anulacion }} · {{ $factura->anulado_en?->format('d/m/Y H:i') }}</p></div></section>
    @elseif ($puedeAnular && ! $factura->tieneRecepcionFisica())
        <section class="supplier-quote-danger-zone"><div><p class="eyebrow">Control documental</p><h2>Anular registro incorrecto</h2><p>El archivo se conserva. Una factura vinculada a recepción ya no puede anularse.</p></div><form method="POST" action="{{ route('facturas-proveedor.anular', $factura) }}" data-confirm="¿Confirmas anular esta factura?"><input type="hidden" name="_token" value="{{ csrf_token() }}">@method('PATCH')<input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required placeholder="Motivo de la anulación"><button class="button button--danger" type="submit">Anular factura</button></form></section>
    @endif
@endsection
