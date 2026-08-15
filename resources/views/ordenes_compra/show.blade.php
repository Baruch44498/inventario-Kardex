@extends('layouts.app')

@section('title', $orden->codigo)
@section('page-kicker', 'Orden de compra')
@section('page-title', $orden->codigo)

@section('content')
    @php
        $solicitud = $orden->solicitudCompra;
        $cotizacion = $solicitud?->cotizacion;
        $lineasCompletas = $orden->detalles->filter->estaCompletamenteRecibido()->count();
        $lineasPendientes = $orden->detalles->count() - $lineasCompletas;
        $avancePromedio = $orden->detalles->isNotEmpty()
            ? (float) $orden->detalles->avg(fn ($detalle) => $detalle->porcentajeRecibido())
            : 0;
        $tienePendienteFacturar = $orden->detalles->contains(
            fn ($detalle) => $detalle->cantidadPendienteFacturar() > 0.0001
        );
    @endphp
    <a href="{{ route('ordenes-compra.index') }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a órdenes</a>

    <section class="module-header purchase-order-hero">
        <div>
            <p class="eyebrow">Compra autorizada</p>
            <h1>{{ $orden->codigo }}</h1>
            <p>{{ $orden->proveedor?->nombreVisible() }} · Emitida el {{ $orden->fecha_emision?->format('d/m/Y') }}</p>
        </div>
        <div class="module-header__actions">
            <span class="badge badge--{{ $orden->estadoClase() }}">{{ $orden->estadoVisible() }}</span>
            @if ($puedeRegistrarFactura && ! $orden->estaAnulada() && $tienePendienteFacturar)
                <a href="{{ route('facturas-proveedor.create', $orden) }}" class="button button--primary"><x-ui.icon name="invoice" :size="17" /> Registrar factura</a>
            @endif
            @if ($orden->permiteRecepcion() && $puedeRegistrarIngreso)
                <a href="{{ route('notas-ingreso.create', ['motivo_ingreso' => 'COMPRA', 'orden_compra_id' => $orden->id]) }}" class="button button--ghost"><x-ui.icon name="entry" :size="17" /> Registrar recepción</a>
            @endif
        </div>
    </section>

    @if ($orden->esCompraDirecta())
        <div class="notice notice--{{ $orden->origenClase() }} notice--block">
            <x-ui.icon name="warning" :size="20" />
            <div>
                <strong>{{ $orden->origenVisible() }} sin requerimiento previo</strong>
                <p>{{ $orden->justificacion_origen }}</p>
            </div>
        </div>
    @endif

    @if ($orden->permiteRecepcion())
        <div class="purchase-approval-gate" role="note">
            <span><x-ui.icon name="info" :size="19" /></span>
            <div>
                <strong>{{ $orden->estado === 'PARCIALMENTE_RECIBIDA' ? 'Recepción parcial: queda saldo pendiente' : 'Lista para la primera recepción' }}</strong>
                <p>La Nota de Ingreso mostrará únicamente las cantidades pendientes y actualizará automáticamente el estado de la orden.</p>
            </div>
        </div>
    @elseif ($orden->estaRecibida())
        <div class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div><strong>Recepción completada</strong><p>Todas las líneas de la orden fueron ingresadas al Almacén.</p></div>
        </div>
    @endif

    <div class="notice notice--{{ $conciliacionFacturas['clase'] }} notice--block purchase-order-invoice-status">
        <x-ui.icon name="invoice" :size="20" />
        <div>
            <strong>{{ $conciliacionFacturas['etiqueta'] }}</strong>
            <p>Facturado: <x-ui.money :value="$orden->totalFacturadoDocumento()" :currency="$orden->moneda" /> · Autorizado: <x-ui.money :value="$orden->total" :currency="$orden->moneda" />. Contabilidad consulta este resultado; no aprueba ni rechaza.</p>
        </div>
        <a href="{{ route('facturas-proveedor.index', ['q' => $orden->codigo]) }}" class="button button--ghost button--small">Ver facturas</a>
    </div>

    <section class="summary-strip summary-strip--four purchase-order-receipt-summary" aria-label="Resumen de recepción">
        @foreach ([
            ['Líneas ordenadas', 'info', 'purchase-order', $orden->detalles->count()],
            ['Líneas completas', 'success', 'check-circle', $lineasCompletas],
            ['Líneas pendientes', 'warning', 'inventory', $lineasPendientes],
            ['Avance promedio', 'info', 'activity', number_format($avancePromedio, 0).'%'],
        ] as [$titulo, $tono, $icono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}"><x-ui.icon :name="$icono" :size="20" /></span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Trazabilidad</p><h2>Datos de la compra</h2></div></header>
            <dl class="supplier-info-grid">
                <div><dt>Proveedor</dt><dd>{{ $orden->proveedor?->nombreVisible() }}</dd></div>
                <div><dt>RUC</dt><dd>{{ $orden->proveedor?->ruc }}</dd></div>
                <div><dt>Documento proveedor</dt><dd>{{ $orden->numero_documento_proveedor ?: 'No especificado' }}</dd></div>
                <div><dt>Origen de compra</dt><dd><span class="badge badge--{{ $orden->origenClase() }}">{{ $orden->origenVisible() }}</span></dd></div>
                <div><dt>Moneda</dt><dd>{{ $orden->moneda }}</dd></div>
                <div><dt>Fecha de emisión</dt><dd>{{ $orden->fecha_emision?->format('d/m/Y') }}</dd></div>
                <div><dt>Entrega requerida</dt><dd>{{ $orden->fecha_entrega_requerida?->format('d/m/Y') ?? 'No especificada' }}</dd></div>
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
                <div class="supplier-info-grid__wide"><dt>Condiciones</dt><dd>Pago: {{ $orden->condiciones_pago ?: 'No especificado' }} · Entrega: {{ $orden->condiciones_entrega ?: 'No especificada' }}</dd></div>
                @if ($orden->observacion)<div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $orden->observacion }}</dd></div>@endif
            </dl>
            @if ($puedeVerOrigen && ($cotizacion?->archivo_original_path || $cotizacion?->importacionAsistida))
                <div class="purchase-approval-document-action"><a class="button button--ghost button--small" href="{{ route('cotizaciones-proveedor.documento-original', $cotizacion) }}"><x-ui.icon name="quotes" :size="16" /> Descargar cotización original</a></div>
            @endif
        </article>

        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Importe de la orden</p>
            <div><span>Subtotal sin IGV</span><strong><x-ui.money :value="$orden->subtotal" :currency="$orden->moneda" /></strong></div>
            <div><span>IGV</span><strong><x-ui.money :value="$orden->impuesto" :currency="$orden->moneda" /></strong></div>
            @if ($orden->tieneAjusteRedondeo())
                <div><span>Ajuste documental</span><strong>{{ (float) $orden->ajuste_redondeo > 0 ? '+' : '−' }} <x-ui.money :value="abs((float) $orden->ajuste_redondeo)" :currency="$orden->moneda" /></strong></div>
            @endif
            <div class="supplier-quote-total-card__main"><span>Total autorizado</span><strong><x-ui.money :value="$orden->total" :currency="$orden->moneda" /></strong></div>
            @if ($orden->moneda === 'USD')<small>Tipo de cambio referencial: {{ number_format((float) $orden->tipo_cambio, 2) }}</small>@endif
        </article>
    </section>

    <section class="panel purchase-order-lines">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Seguimiento de recepción</p><h2>Productos ordenados</h2></div><span class="count-chip">{{ $orden->detalles->count() }}</span></header>
        <div class="table-wrap">
            <table class="data-table purchase-order-detail-table">
                <thead><tr><th>Producto</th><th class="text-right">Ordenado</th><th class="text-right">Recibido</th><th class="text-right">Pendiente</th><th class="text-right">Precio unitario</th><th class="text-right">Importe</th><th>Avance</th></tr></thead>
                <tbody>
                    @foreach ($orden->detalles as $detalle)
                        @php
                            $pendiente = $detalle->cantidadPendiente();
                            $porcentaje = $detalle->porcentajeRecibido();
                        @endphp
                        <tr>
                            <td><strong>{{ $detalle->producto?->codigo }}</strong><span>{{ $detalle->producto?->descripcion }}</span></td>
                            <td class="text-right"><x-ui.quantity :value="$detalle->cantidad_ordenada" /></td>
                            <td class="text-right"><x-ui.quantity :value="$detalle->cantidad_recibida" /></td>
                            <td class="text-right"><strong><x-ui.quantity :value="$pendiente" /></strong></td>
                            <td class="text-right"><x-ui.money :value="$detalle->precio_unitario" :currency="$orden->moneda" /></td>
                            <td class="text-right"><strong><x-ui.money :value="$detalle->subtotal" :currency="$orden->moneda" /></strong></td>
                            <td><div class="purchase-order-progress"><span style="width: {{ number_format($porcentaje, 2, '.', '') }}%"></span></div><small>{{ number_format($porcentaje, 0) }}%</small></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($orden->notasIngreso->isNotEmpty())
        <section class="panel purchase-order-related">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Documentos posteriores</p><h2>Recepciones registradas</h2></div></header>
            <div class="purchase-order-related__list">
                @foreach ($orden->notasIngreso->sortByDesc('fecha_ingreso') as $nota)
                    <a href="{{ route('notas-ingreso.show', $nota) }}"><strong>{{ $nota->codigo }}</strong><span>{{ $nota->fecha_ingreso?->format('d/m/Y') }} · <x-ui.quantity :value="$nota->detalles->sum('cantidad')" /> recibido</span></a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($orden->facturasProveedor->isNotEmpty())
        <section class="panel purchase-order-related">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Documentos fiscales</p><h2>Facturas registradas</h2></div></header>
            <div class="purchase-order-related__list">
                @foreach ($orden->facturasProveedor->sortByDesc('fecha_emision') as $factura)
                    <a href="{{ route('facturas-proveedor.show', $factura) }}"><strong>{{ $factura->tipo_documento }} {{ $factura->numeroVisible() }}</strong><span>{{ $factura->fecha_emision?->format('d/m/Y') }} · <x-ui.money :value="$factura->total" :currency="$factura->moneda" /> · {{ $factura->estadoVisible() }}</span></a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($orden->estaAnulada())
        <section class="notice notice--danger notice--block"><x-ui.icon name="error" :size="20" /><div><strong>Orden anulada</strong><p>{{ $orden->motivo_anulacion }} · {{ $orden->anulado_en?->format('d/m/Y H:i') }}</p></div></section>
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
@endsection
