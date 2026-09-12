@extends('layouts.app')

@section('title', $cotizacion->codigo)
@section('page-kicker', 'Ventas')
@section('page-title', 'Cotización al cliente')

@section('content')
    @php
        $esUltima = $cotizacion->version === (int) $versiones->max('version');
        $puedeGestionar = auth()->user()->puede('proformas.cotizar');
        $esHistoricaAvanzada = $cotizacion->detalles->contains('origen_costeo', true);
        $puedeCerrar = $puedeGestionar && ! $esHistoricaAvanzada && $cotizacion->esEditable();
        $puedeGenerarOv = $puedeGestionar && ! $esHistoricaAvanzada && $cotizacion->puedeConvertirseEnOrden();
        $puedeAnular = $puedeGestionar
            && ! $cotizacion->estaAnulada()
            && $cotizacion->estado !== 'CONVERTIDA_EN_ORDEN';
    @endphp

    <a href="{{ route('cotizaciones-cliente.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a cotizaciones
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div>
            <p class="eyebrow">Venta simple · Versión {{ $cotizacion->version }}</p>
            <h1>{{ $cotizacion->codigo }}</h1>
            <p>{{ $cotizacion->cliente_nombre }} · {{ $cotizacion->fecha_emision->format('d/m/Y') }} · {{ $cotizacion->moneda }}</p>
        </div>
        <div class="supplier-quote-hero__actions">
            <x-ui.status-badge :tone="$cotizacion->tonoEstadoVisual()" class="badge--large">
                {{ $cotizacion->estadoVisual() }}
            </x-ui.status-badge>
            @if ($puedeCerrar)
                <a href="{{ route('cotizaciones-cliente.edit', $cotizacion) }}" class="button button--primary">
                    <x-ui.icon name="edit" :size="17" /> Editar cotización
                </a>
            @endif
        </div>
    </section>

    <nav class="commercial-version-tabs" aria-label="Versiones de cotización">
        @foreach ($versiones as $version)
            <a href="{{ route('cotizaciones-cliente.show', $version) }}" class="{{ $version->is($cotizacion) ? 'is-active' : '' }}">
                VRS{{ $version->version }} <span>{{ $version->estadoVisual() }}</span>
            </a>
        @endforeach
    </nav>

    @if ($esHistoricaAvanzada)
        <section class="notice notice--warning notice--block">
            <x-ui.icon name="warning" :size="20" />
            <div><strong>Cotización histórica</strong><span>Este documento conserva datos del costeo avanzado y queda disponible solo para consulta.</span></div>
        </section>
    @endif

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Información</p><h2>Datos comerciales</h2></div></header>
            <dl class="supplier-info-grid">
                <div><dt>Cliente</dt><dd>{{ $cotizacion->cliente_nombre }}</dd></div>
                <div><dt>Documento</dt><dd>{{ $cotizacion->cliente_documento ?: 'No registrado' }}</dd></div>
                <div><dt>Tipo de cliente</dt><dd>{{ $cotizacion->cliente?->tipoCliente?->nombre ?: 'No definido' }}</dd></div>
                <div><dt>Destino</dt><dd>Orden de Venta</dd></div>
                <div><dt>Dirección de entrega</dt><dd>{{ $cotizacion->clienteDireccion?->direccion ?: $cotizacion->clienteDireccion?->destino ?: 'No especificada' }}</dd></div>
                <div><dt>Cotizada por</dt><dd>{{ $cotizacion->cotizador?->nombreVisible() ?: 'No registrado' }}</dd></div>
                <div><dt>Cerrada por</dt><dd>{{ $cotizacion->cerrador?->nombreVisible() ?: 'Aún abierta' }}</dd></div>
                <div>
                    <dt>Orden de Venta</dt>
                    <dd>
                        @if ($cotizacion->ordenOperacion)
                            <a href="{{ route('ordenes-operacion.show', $cotizacion->ordenOperacion) }}">{{ $cotizacion->ordenOperacion->codigo_orden }}</a>
                        @else
                            Aún no generada
                        @endif
                    </dd>
                </div>
                <div><dt>Condiciones de pago</dt><dd>{{ $cotizacion->condiciones_pago ?: 'No especificadas' }}</dd></div>
                <div><dt>Condiciones de entrega</dt><dd>{{ $cotizacion->condiciones_entrega ?: 'No especificadas' }}</dd></div>
                <div class="supplier-info-grid__wide"><dt>Descripción de la venta</dt><dd>{{ $cotizacion->descripcion_trabajo }}</dd></div>
                @if ($cotizacion->observacion)
                    <div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $cotizacion->observacion }}</dd></div>
                @endif
            </dl>
        </article>

        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Resumen económico</p>
            <div><span>Subtotal</span><strong><x-ui.money :value="$cotizacion->subtotal" :currency="$cotizacion->moneda" /></strong></div>
            <div><span>IGV</span><strong><x-ui.money :value="$cotizacion->impuesto" :currency="$cotizacion->moneda" /></strong></div>
            <div class="supplier-quote-total-card__main"><span>Total</span><strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong></div>
            @if ($cotizacion->moneda === 'USD')
                <small>Tipo de cambio: {{ number_format((float) $cotizacion->tipo_cambio, 2) }}</small>
            @endif
        </article>
    </section>

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Detalle</p><h2>Productos cotizados</h2></div></header>
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

    @if ($puedeCerrar || $puedeGenerarOv || $puedeAnular)
        <section class="panel commercial-quote-actions">
            <header class="panel-heading"><p class="eyebrow">Control documental</p><h2>Acciones de la cotización</h2></header>
            <div class="commercial-quote-actions__grid">
                @if ($puedeCerrar)
                    <article class="commercial-quote-action commercial-quote-action--approve">
                        <div><h3>Cerrar cotización</h3><p>Verifica los productos, precios e IGV. Después de cerrar podrás generar la OV.</p></div>
                        <form method="POST" action="{{ route('cotizaciones-cliente.cerrar', $cotizacion) }}" data-confirm="¿Cerrar esta cotización?">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button button--primary"><x-ui.icon name="check-circle" :size="17" /> Cerrar cotización</button>
                        </form>
                    </article>
                @endif

                @if ($puedeGenerarOv)
                    <article class="commercial-quote-action commercial-quote-action--approve">
                        <div><h3>Generar Orden de Venta</h3><p>La OV copiará el cliente, la dirección, la descripción y los productos de esta versión.</p></div>
                        <form method="POST" action="{{ route('cotizaciones-cliente.convertir-orden', $cotizacion) }}" data-confirm="¿Generar la Orden de Venta?" data-confirm-label="Generar OV">
                            @csrf
                            <label class="form-field"><span>Fecha de apertura <span class="required-mark">*</span></span><input type="date" name="fecha_apertura" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required></label>
                            <button type="submit" class="button button--primary"><x-ui.icon name="orders" :size="17" /> Generar OV</button>
                        </form>
                    </article>
                @endif

                @if ($puedeAnular)
                    <article class="commercial-quote-action commercial-quote-action--cancel">
                        <div><h3>Anular esta versión</h3><p>La cotización continuará visible en el historial.</p></div>
                        <form method="POST" action="{{ route('cotizaciones-cliente.anular', $cotizacion) }}" data-confirm="¿Anular esta versión?" data-confirm-tone="danger">
                            @csrf
                            @method('PATCH')
                            <label class="form-field"><span>Motivo <span class="required-mark">*</span></span><input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required></label>
                            <button type="submit" class="button button--danger"><x-ui.icon name="error" :size="17" /> Anular versión</button>
                        </form>
                    </article>
                @endif
            </div>
        </section>
    @elseif ($cotizacion->ordenOperacion)
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div><strong>Orden de Venta generada</strong><span>Esta versión originó <a href="{{ route('ordenes-operacion.show', $cotizacion->ordenOperacion) }}">{{ $cotizacion->ordenOperacion->codigo_orden }}</a>.</span></div>
        </section>
    @endif

    @if ($puedeGestionar && ! $esHistoricaAvanzada && $cotizacion->puedeCrearVersion() && $esUltima)
        <section class="commercial-action-panel commercial-action-panel--primary">
            <div><p class="eyebrow">Nueva negociación</p><h2>Crear VRS{{ $cotizacion->version + 1 }}</h2><p>Se copiarán productos y condiciones en una nueva versión abierta.</p></div>
            <form method="POST" action="{{ route('cotizaciones-cliente.version', $cotizacion) }}">@csrf<button class="button button--ghost" type="submit"><x-ui.icon name="plus" :size="17" /> Nueva versión</button></form>
        </section>
    @endif

    @if ($cotizacion->estaAnulada())
        <section class="notice notice--danger notice--block"><x-ui.icon name="error" :size="20" /><div><strong>Versión anulada</strong><span>{{ $cotizacion->motivo_anulacion }} · {{ $cotizacion->anulado_en?->format('d/m/Y H:i') }}</span></div></section>
    @endif
@endsection
