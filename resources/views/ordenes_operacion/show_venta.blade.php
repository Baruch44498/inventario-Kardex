@extends('layouts.app')

@section('title', $orden->codigo_orden)
@section('page-kicker', 'Ventas')
@section('page-title', 'Orden de Venta')

@section('content')
    @php
        $cotizacion = $orden->cotizacionCliente;
        $tono = match ($orden->estado) {
            'ABIERTA' => 'info',
            'EN_PROCESO' => 'warning',
            'CERRADA' => 'success',
            'ANULADA' => 'danger',
            default => 'neutral',
        };
        $puedeAnular = auth()->user()->puede('ordenes.anular_venta')
            && ! in_array($orden->estado, ['CERRADA', 'ANULADA'], true);
    @endphp

    <a href="{{ route('ordenes-operacion.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a Órdenes de Venta
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div>
            <p class="eyebrow">Orden de Venta</p>
            <h1>{{ $orden->codigo_orden }}</h1>
            <p>{{ $orden->cliente?->razon_social ?: 'Cliente no disponible' }} · {{ $orden->fecha_apertura?->format('d/m/Y') }}</p>
        </div>
        <x-ui.status-badge :tone="$tono" class="badge--large">{{ str_replace('_', ' ', $orden->estado) }}</x-ui.status-badge>
    </section>

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Documento</p><h2>Datos de la venta</h2></div></header>
            <dl class="supplier-info-grid">
                <div><dt>Cliente</dt><dd>{{ $orden->cliente?->razon_social ?: 'No disponible' }}</dd></div>
                <div><dt>RUC</dt><dd>{{ $orden->cliente?->ruc ?: 'No registrado' }}</dd></div>
                <div><dt>Cotización de origen</dt><dd>@if ($cotizacion)<a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}">{{ $cotizacion->codigo }}</a>@else No disponible @endif</dd></div>
                <div><dt>Registrada por</dt><dd>{{ $orden->creador?->nombreVisible() ?: 'No registrado' }}</dd></div>
                <div><dt>Dirección de entrega</dt><dd>{{ $orden->clienteDireccion?->direccion ?: $orden->clienteDireccion?->destino ?: 'No especificada' }}</dd></div>
                <div><dt>Notas de salida</dt><dd>{{ (int) $orden->notas_salida_count }}</dd></div>
                <div class="supplier-info-grid__wide"><dt>Descripción</dt><dd>{{ $orden->descripcion }}</dd></div>
            </dl>
        </article>

        @if ($cotizacion)
            <article class="panel supplier-quote-total-card">
                <p class="eyebrow">Importe aprobado</p>
                <div><span>Subtotal</span><strong><x-ui.money :value="$cotizacion->subtotal" :currency="$cotizacion->moneda" /></strong></div>
                <div><span>IGV</span><strong><x-ui.money :value="$cotizacion->impuesto" :currency="$cotizacion->moneda" /></strong></div>
                <div class="supplier-quote-total-card__main"><span>Total</span><strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong></div>
            </article>
        @endif
    </section>

    @if ($cotizacion)
        <section class="panel supplier-quote-detail-lines">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Detalle</p><h2>Productos vendidos</h2></div></header>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Precio unitario</th><th>IGV</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($cotizacion->detalles as $detalle)
                            <tr>
                                <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                                <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                                <td class="text-right"><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion->moneda" /></td>
                                <td>{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                                <td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" /></strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($orden->notasSalida->isNotEmpty())
        <section class="panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Despacho</p><h2>Notas de salida vinculadas</h2></div></header>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Nota</th><th>Fecha</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($orden->notasSalida as $nota)
                            <tr>
                                <td><strong>{{ $nota->codigo }}</strong></td>
                                <td>{{ $nota->fecha_salida?->format('d/m/Y') }}</td>
                                <td>{{ $nota->estado }}</td>
                                <td><a href="{{ route('notas-salida.show', $nota) }}" class="button button--ghost button--small">Ver nota</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($puedeAnular)
        <section class="panel commercial-quote-actions">
            <header class="panel-heading"><p class="eyebrow">Control documental</p><h2>Anular Orden de Venta</h2></header>
            <form method="POST" action="{{ route('ordenes-operacion.anular', $orden) }}" data-confirm="¿Anular esta Orden de Venta?" data-confirm-tone="danger">
                @csrf
                @method('PATCH')
                <label class="form-field"><span>Motivo <span class="required-mark">*</span></span><input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required></label>
                <button type="submit" class="button button--danger"><x-ui.icon name="error" :size="17" /> Anular OV</button>
            </form>
        </section>
    @endif

    @if ($orden->estado === 'ANULADA')
        <section class="notice notice--danger notice--block">
            <x-ui.icon name="error" :size="20" />
            <div><strong>Orden de Venta anulada</strong><span>{{ $orden->motivo_anulacion }} · {{ $orden->anulado_en?->format('d/m/Y H:i') }}</span></div>
        </section>
    @endif
@endsection
