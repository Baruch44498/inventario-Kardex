@extends('layouts.app')

@section('title', $solicitud->codigo)
@section('page-kicker', 'Solicitud de compra')
@section('page-title', $solicitud->codigo)

@section('content')
    @php $cotizacion = $solicitud->cotizacion; @endphp

    <a href="{{ route('solicitudes-compra.index') }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a solicitudes</a>

    <section class="module-header purchase-approval-hero">
        <div>
            <p class="eyebrow">Cotización enviada a Contabilidad</p>
            <h1>{{ $solicitud->codigo }}</h1>
            <p>{{ $cotizacion?->proveedor?->nombreVisible() }} · {{ $solicitud->fecha_solicitud?->format('d/m/Y') }}</p>
        </div>
        <span class="badge badge--{{ $solicitud->estadoClase() }}">{{ $solicitud->estadoVisible() }}</span>
    </section>

    <div class="purchase-approval-gate" role="note">
        <span><x-ui.icon name="info" :size="19" /></span>
        <div>
            <strong>Control previo a la orden de compra</strong>
            <p>La cotización histórica no puede convertirse directamente. La futura OC solo aceptará esta solicitud cuando figure como “Aprobada para compra”.</p>
        </div>
    </div>

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Documento seleccionado</p><h2>Información para revisión</h2></div></header>
            <dl class="supplier-info-grid">
                <div><dt>Cotización interna</dt><dd>{{ $cotizacion?->codigo }}</dd></div>
                <div><dt>Documento del proveedor</dt><dd>{{ $cotizacion?->numero_documento ?: 'No registrado' }}</dd></div>
                <div><dt>Proveedor</dt><dd>{{ $cotizacion?->proveedor?->nombreVisible() }}</dd></div>
                <div><dt>RUC</dt><dd>{{ $cotizacion?->proveedor?->ruc }}</dd></div>
                <div><dt>Fecha de cotización</dt><dd>{{ $cotizacion?->fecha_cotizacion?->format('d/m/Y') }}</dd></div>
                <div><dt>Vigencia</dt><dd>{{ $cotizacion?->fecha_validez?->format('d/m/Y') ?? 'No especificada' }}</dd></div>
                <div><dt>Requerimiento</dt><dd>{{ $cotizacion?->requisicion?->codigo ?? 'Sin requerimiento' }}</dd></div>
                <div><dt>Enviado por</dt><dd>{{ $solicitud->solicitante?->nombreVisible() ?? '—' }}</dd></div>
                <div class="supplier-info-grid__wide"><dt>Justificación</dt><dd>{{ $solicitud->descripcion ?: 'Sin comentario adicional.' }}</dd></div>
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
            <p class="eyebrow">Importe enviado a revisión</p>
            <div><span>Suma de líneas seleccionadas</span><strong><x-ui.money :value="$solicitud->total_lineas" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            @if ($solicitud->tieneAjusteRedondeo())
                <div><span>Ajuste documental</span><strong>{{ (float) $solicitud->ajuste_redondeo > 0 ? '+' : '−' }} <x-ui.money :value="abs((float) $solicitud->ajuste_redondeo)" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            @endif
            <div class="supplier-quote-total-card__main"><span>Total seleccionado</span><strong><x-ui.money :value="$solicitud->total_seleccionado" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            <div><span>Total del documento completo</span><strong><x-ui.money :value="$cotizacion?->total" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></div>
            <small>La futura orden usará únicamente las líneas seleccionadas que aparecen abajo; no copiará productos adicionales del PDF.</small>
            @if ($cotizacion?->moneda === 'USD')
                <small>Tipo de cambio referencial: {{ number_format((float) $cotizacion->tipo_cambio, 4) }}</small>
            @endif
        </article>
    </section>

    <section class="panel purchase-approval-lines">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Alcance autorizado por Compras</p><h2>Productos enviados a revisión</h2></div><span class="count-chip">{{ $solicitud->detalles->count() }}</span></header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Producto</th><th>Cantidad</th><th>Vínculo</th><th class="text-right">Precio final unitario</th><th class="text-right">Importe</th><th>Observación</th></tr></thead>
                <tbody>
                    @foreach ($solicitud->detalles as $detalle)
                        <tr>
                            <td><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->producto?->descripcion }}</span></td>
                            <td><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->abreviatura ?? 'und.' }}</td>
                            <td><span class="badge badge--{{ $detalle->cotizacionDetalle?->tipoVinculacionEfectivo() === 'ALTERNATIVA' ? 'warning' : 'success' }}">{{ $detalle->cotizacionDetalle?->vinculacionVisible() ?? 'Producto seleccionado' }}</span></td>
                            <td class="text-right"><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion?->moneda ?? 'PEN'" /></td>
                            <td class="text-right"><strong><x-ui.money :value="$detalle->subtotal" :currency="$cotizacion?->moneda ?? 'PEN'" /></strong></td>
                            <td>{{ $detalle->observacion ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($solicitud->estaPendiente() && $esContabilidad)
        <section class="panel purchase-approval-decision">
            <div class="purchase-approval-decision__copy"><p class="eyebrow">Decisión de Contabilidad</p><h2>Validar continuidad de la compra</h2><p>La aprobación habilita esta solicitud como origen de la futura OC. El rechazo la bloquea y conserva la cotización como precio histórico no utilizado.</p></div>
            <div class="purchase-approval-decision__actions">
                <form method="POST" action="{{ route('solicitudes-compra.aprobar', $solicitud) }}" data-confirm="¿Confirmas que esta compra puede continuar a orden de compra?">
                    @csrf @method('PATCH')
                    <button class="button button--primary" type="submit"><x-ui.icon name="check-circle" :size="17" /> Aprobar para compra</button>
                </form>
                <form method="POST" action="{{ route('solicitudes-compra.rechazar', $solicitud) }}" class="purchase-approval-reject-form" data-confirm="¿Confirmas rechazar esta solicitud?">
                    @csrf @method('PATCH')
                    <input type="text" name="motivo_rechazo" minlength="5" maxlength="500" required placeholder="Motivo del rechazo">
                    <button class="button button--danger" type="submit"><x-ui.icon name="error" :size="17" /> Rechazar</button>
                </form>
            </div>
        </section>
    @elseif ($solicitud->estaAprobada())
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div>
                <strong>Lista para generar orden de compra</strong>
                <p>Aprobada por {{ $solicitud->aprobador?->nombreVisible() ?? 'usuario no disponible' }} el {{ $solicitud->aprobado_en?->format('d/m/Y H:i') }}. La creación de la OC se habilitará en la siguiente etapa.</p>
            </div>
        </section>
    @elseif ($solicitud->estaRechazada())
        <section class="notice notice--danger notice--block">
            <x-ui.icon name="error" :size="20" />
            <div><strong>Compra rechazada</strong><p>{{ $solicitud->motivo_rechazo }} · {{ $solicitud->rechazado_en?->format('d/m/Y H:i') }}</p></div>
        </section>
    @endif
@endsection
