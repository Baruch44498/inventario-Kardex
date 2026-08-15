@extends('layouts.app')

@section('title', $solicitud->codigo)
@section('page-kicker', 'Solicitud de compra')
@section('page-title', $solicitud->codigo)

@section('content')
    @php $cotizacion = $solicitud->cotizacion; @endphp

    <a href="{{ route('solicitudes-compra.index') }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver al listado</a>

    <section class="module-header purchase-approval-hero">
        <div>
            <p class="eyebrow">Compra aprobada por Logística / Compras</p>
            <h1>{{ $solicitud->codigo }}</h1>
            <p>{{ $cotizacion?->proveedor?->nombreVisible() }} · {{ $solicitud->fecha_solicitud?->format('d/m/Y') }}</p>
        </div>
        <div class="module-header__actions">
            <span class="badge badge--{{ $solicitud->origenClase() }}">{{ $solicitud->origenVisible() }}</span>
            <span class="badge badge--{{ $solicitud->estadoClase() }}">{{ $solicitud->estadoVisible() }}</span>
        </div>
    </section>

    @if ($solicitud->esCompraDirecta())
        <div class="notice notice--{{ $solicitud->origenClase() }} notice--block">
            <x-ui.icon name="warning" :size="20" />
            <div>
                <strong>{{ $solicitud->origenVisible() }} sin requerimiento previo</strong>
                <p>{{ $solicitud->justificacion_origen }}</p>
            </div>
        </div>
    @endif

    <div class="purchase-approval-gate" role="note">
        <span><x-ui.icon name="info" :size="19" /></span>
        <div>
            <strong>Registro de trazabilidad</strong>
            <p>Este registro conserva quién eligió la cotización y qué productos originaron la OC. Contabilidad puede consultarlo, pero no aprobar ni rechazar la compra.</p>
        </div>
    </div>

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Documento seleccionado</p><h2>Información de la compra</h2></div></header>
            <dl class="supplier-info-grid">
                <div><dt>Cotización interna</dt><dd>{{ $cotizacion?->codigo }}</dd></div>
                <div><dt>Documento del proveedor</dt><dd>{{ $cotizacion?->numero_documento ?: 'No registrado' }}</dd></div>
                <div><dt>Proveedor</dt><dd>{{ $cotizacion?->proveedor?->nombreVisible() }}</dd></div>
                <div><dt>RUC</dt><dd>{{ $cotizacion?->proveedor?->ruc }}</dd></div>
                <div><dt>Fecha de cotización</dt><dd>{{ $cotizacion?->fecha_cotizacion?->format('d/m/Y') }}</dd></div>
                <div><dt>Vigencia</dt><dd>{{ $cotizacion?->fecha_validez?->format('d/m/Y') ?? 'No especificada' }}</dd></div>
                <div><dt>Requerimiento</dt><dd>{{ $cotizacion?->requisicion?->codigo ?? 'Sin requerimiento' }}</dd></div>
                <div><dt>Origen de compra</dt><dd>{{ $solicitud->origenVisible() }}</dd></div>
                <div><dt>Aprobado por Compras</dt><dd>{{ $solicitud->aprobador?->nombreVisible() ?? $solicitud->solicitante?->nombreVisible() ?? '—' }}</dd></div>
                @if ($solicitud->esCompraDirecta())
                    <div class="supplier-info-grid__wide"><dt>Justificación de la excepción</dt><dd>{{ $solicitud->justificacion_origen }}</dd></div>
                @endif
                <div class="supplier-info-grid__wide"><dt>Motivo de elección</dt><dd>{{ $solicitud->descripcion ?: 'Sin comentario adicional.' }}</dd></div>
                <div class="supplier-info-grid__wide"><dt>Condiciones</dt><dd>Pago: {{ $cotizacion?->condiciones_pago ?: 'No especificado' }} · Entrega: {{ $cotizacion?->condiciones_entrega ?: 'No especificada' }}</dd></div>
            </dl>
            @if ($cotizacion?->archivo_original_path || $cotizacion?->importacionAsistida)
                <div class="purchase-approval-document-action">
                    <a class="button button--ghost button--small" href="{{ route('cotizaciones-proveedor.documento-original', $cotizacion) }}">
                        <x-ui.icon name="quotes" :size="16" /> Descargar cotización original
                    </a>
                </div>
            @endif
        </article>

        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Importe aprobado</p>
            <div><span>Suma de líneas seleccionadas</span><strong><x-ui.money :value="$solicitud->total_lineas" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            @if ($solicitud->tieneAjusteRedondeo())
                <div><span>Ajuste documental</span><strong>{{ (float) $solicitud->ajuste_redondeo > 0 ? '+' : '−' }} <x-ui.money :value="abs((float) $solicitud->ajuste_redondeo)" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            @endif
            <div class="supplier-quote-total-card__main"><span>Total seleccionado</span><strong><x-ui.money :value="$solicitud->total_seleccionado" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            <div><span>Total del documento completo</span><strong><x-ui.money :value="$cotizacion?->total" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            <small>La futura orden usará únicamente las líneas seleccionadas que aparecen abajo; no copiará productos adicionales del PDF.</small>
            @if ($cotizacion?->moneda === 'USD')
                <small>Tipo de cambio referencial: {{ number_format((float) $cotizacion->tipo_cambio, 2) }}</small>
            @endif
        </article>
    </section>

    <section class="panel purchase-approval-lines">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Alcance autorizado por Compras</p><h2>Productos aprobados</h2></div><span class="count-chip">{{ $solicitud->detalles->count() }}</span></header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Producto</th><th>Cantidad</th><th>Vínculo</th><th class="text-right">Precio final unitario</th><th class="text-right">Importe</th><th>Observación</th></tr></thead>
                <tbody>
                    @foreach ($solicitud->detalles as $detalle)
                        <tr>
                            <td><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->producto?->descripcion }}</span></td>
                            <td><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->abreviatura ?? 'und.' }}</td>
                            <td>
                                @if ($solicitud->esCompraDirecta())
                                    <span class="badge badge--info">Compra directa</span>
                                @else
                                    <span class="badge badge--{{ $detalle->cotizacionDetalle?->tipoVinculacionEfectivo() === 'ALTERNATIVA' ? 'warning' : 'success' }}">{{ $detalle->cotizacionDetalle?->vinculacionVisible() ?? 'Producto seleccionado' }}</span>
                                @endif
                            </td>
                            <td class="text-right"><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion?->moneda ?? 'PEN'" /></td>
                            <td class="text-right"><strong><x-ui.money :value="$detalle->subtotal" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></td>
                            <td>{{ $detalle->observacion ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if (($solicitud->estaPendiente() || $solicitud->estaAprobada()) && $puedeGestionarCompras)
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div>
                <strong>{{ $solicitud->estaPendiente() ? 'Registro anterior listo para generar OC' : 'Lista para generar orden de compra' }}</strong>
                @if ($solicitud->estaPendiente())
                    <p>Este registro proviene del flujo anterior. Al generar la OC se registrará la aprobación del usuario de Compras.</p>
                @else
                    <p>Aprobada por {{ $solicitud->aprobador?->nombreVisible() ?? 'Compras' }} el {{ $solicitud->aprobado_en?->format('d/m/Y H:i') ?? '—' }}.</p>
                @endif
            </div>
            @if ($solicitud->puedeConvertirseEnOrden())
                <a href="{{ route('ordenes-compra.create', $solicitud) }}" class="button button--primary"><x-ui.icon name="purchase-order" :size="17" /> Generar orden de compra</a>
            @endif
        </section>
    @elseif (($solicitud->estaPendiente() || $solicitud->estaAprobada()) && $esContabilidad)
        <section class="notice notice--info notice--block">
            <x-ui.icon name="info" :size="20" />
            <div><strong>Solo consulta</strong><p>Compras aún no ha generado la orden correspondiente a este registro anterior.</p></div>
        </section>
    @elseif ($solicitud->estaConvertida() && $solicitud->ordenCompra)
        <section class="notice notice--success notice--block">
            <x-ui.icon name="purchase-order" :size="20" />
            <div><strong>Convertida en orden de compra</strong><p>La solicitud ya originó {{ $solicitud->ordenCompra->codigo }}.</p></div>
            <a href="{{ route('ordenes-compra.show', $solicitud->ordenCompra) }}" class="button button--ghost button--small">Ver orden</a>
        </section>
    @elseif ($solicitud->estaRechazada())
        <section class="notice notice--danger notice--block">
            <x-ui.icon name="error" :size="20" />
            <div><strong>Registro anterior no utilizado</strong><p>{{ $solicitud->motivo_rechazo }} · {{ $solicitud->rechazado_en?->format('d/m/Y H:i') }}</p></div>
        </section>
    @endif
@endsection
