@extends('layouts.app')

@section('title', $cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Cotización al cliente')

@section('content')
    @php
        $tono = match ($cotizacion->estado) {
            'ANULADA' => 'danger',
            'CERRADA', 'CONVERTIDA_EN_ORDEN' => 'success',
            default => 'info',
        };
        $esUltima = $cotizacion->version === (int) $versiones->max('version');
    @endphp

    <a href="{{ $cotizacion->proforma
        ? route('proformas.show', $cotizacion->proforma)
        : route('cotizaciones-cliente.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        {{ $cotizacion->proforma
            ? 'Volver a '.$cotizacion->proforma->codigo
            : 'Volver a cotizaciones' }}
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div>
            <p class="eyebrow">{{ $cotizacion->origenVisible() }} · Versión {{ $cotizacion->version }}</p>
            <h1>{{ $cotizacion->codigo }}</h1>
            <p>{{ $cotizacion->cliente_nombre }} · {{ $cotizacion->fecha_emision->format('d/m/Y') }} · {{ $cotizacion->moneda }}</p>
        </div>
        <div class="supplier-quote-hero__actions">
            <span class="badge badge--{{ $tono }} badge--large">{{ $cotizacion->estado }}</span>
            @if (auth()->user()->puede('proformas.cotizar') && $cotizacion->esEditable())
                <a href="{{ route('cotizaciones-cliente.edit', $cotizacion) }}" class="button button--primary"><x-ui.icon name="edit" :size="17" /> Continuar cotizando</a>
            @endif
        </div>
    </section>

    <nav class="commercial-version-tabs" aria-label="Versiones de cotización">
        @foreach ($versiones as $version)
            <a href="{{ route('cotizaciones-cliente.show', $version) }}" class="{{ $version->is($cotizacion) ? 'is-active' : '' }}">
                VRS{{ $version->version }} <span>{{ $version->estado }}</span>
            </a>
        @endforeach
    </nav>

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Información</p><h2>Datos comerciales</h2></div></header>
            <dl class="supplier-info-grid">
                <div>
                    <dt>Origen</dt>
                    <dd>
                        @if ($cotizacion->proforma)
                            <a href="{{ route('proformas.show', $cotizacion->proforma) }}">{{ $cotizacion->proforma->codigo }}</a>
                        @else
                            Cotización directa de Logística
                        @endif
                    </dd>
                </div>
                <div><dt>Cliente</dt><dd>{{ $cotizacion->cliente_nombre }}</dd></div>
                <div><dt>Documento</dt><dd>{{ $cotizacion->cliente_documento ?: 'No registrado' }}</dd></div>
                <div><dt>Tipo de cliente</dt><dd>{{ $cotizacion->cliente?->tipoCliente?->nombre ?: 'No definido' }}</dd></div>
                <div><dt>Trabajo</dt><dd>{{ $cotizacion->tipoOrden?->codigo }} · {{ $cotizacion->tipoOrden?->nombre ?: 'Pendiente de completar' }}</dd></div>
                <div><dt>Vehículo</dt><dd>{{ $cotizacion->vehiculo?->identificadorVisible() ?: 'No aplica' }}</dd></div>
                <div><dt>Cotizada por</dt><dd>{{ $cotizacion->cotizador?->nombreVisible() }}</dd></div>
                <div><dt>Cerrada por</dt><dd>{{ $cotizacion->cerrador?->nombreVisible() ?: 'Aún abierta' }}</dd></div>
                <div>
                    <dt>Orden vinculada</dt>
                    <dd>
                        @if ($cotizacion->ordenOperacion)
                            <a href="{{ route('ordenes-operacion.show', $cotizacion->ordenOperacion) }}">{{ $cotizacion->ordenOperacion->codigo_orden }}</a>
                        @else
                            Aún no convertida
                        @endif
                    </dd>
                </div>
                <div><dt>Condiciones de pago</dt><dd>{{ $cotizacion->condiciones_pago ?: 'No especificadas' }}</dd></div>
                <div><dt>Condiciones de entrega</dt><dd>{{ $cotizacion->condiciones_entrega ?: 'No especificadas' }}</dd></div>
                <div class="supplier-info-grid__wide"><dt>Descripción del trabajo</dt><dd>{{ $cotizacion->descripcion_trabajo ?: 'Pendiente de completar' }}</dd></div>
                @if ($cotizacion->observacion)<div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $cotizacion->observacion }}</dd></div>@endif
            </dl>
        </article>
        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Resumen económico</p>
            <div><span>Subtotal</span><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->subtotal, 2) }}</strong></div>
            <div><span>IGV</span><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->impuesto, 2) }}</strong></div>
            <div class="supplier-quote-total-card__main"><span>Total</span><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2) }}</strong></div>
            @if ($cotizacion->moneda === 'USD')<small>Tipo de cambio: {{ number_format((float) $cotizacion->tipo_cambio, 6) }}</small>@endif
        </article>
    </section>

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Detalle</p><h2>Productos cotizados</h2></div></header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Sugerido</th><th class="text-right">Cotizado</th><th>IGV</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @foreach ($cotizacion->detalles as $detalle)
                        <tr>
                            <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                            <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                            <td class="text-right">{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $detalle->precio_sugerido, 2) }}</td>
                            <td class="text-right"><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $detalle->precio_unitario, 2) }}</strong>@if ($detalle->precioFueAjustado())<span>Ajustado por Logística</span>@endif</td>
                            <td>{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                            <td class="text-right"><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $detalle->total, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if (auth()->user()->puede('proformas.cotizar') && $cotizacion->puedeConvertirseEnOrden())
        <section class="commercial-action-panel commercial-action-panel--primary">
            <div>
                <p class="eyebrow">Cierre del flujo comercial</p>
                <h2>Aprobar y generar {{ $cotizacion->tipoOrden?->codigo ?: 'la orden' }}</h2>
                <p>
                    La orden heredará cliente, trabajo, productos y vehículo cuando corresponda.
                    Aprobar no descuenta stock ni genera movimientos de Kardex.
                </p>
            </div>
            <form method="POST" action="{{ route('cotizaciones-cliente.convertir-orden', $cotizacion) }}"
                class="supplier-quote-cancel-form" data-confirm="¿Aprobar esta cotización y generar su orden?">
                @csrf
                @if (! $cotizacion->tieneContextoOperativoCompleto())
                    <div class="notice notice--warning notice--block">
                        <x-ui.icon name="warning" :size="18" />
                        <span>Esta cotización fue creada antes de la integración. Completa una sola vez sus datos operativos.</span>
                    </div>
                    <select name="tipo_orden_id" required>
                        <option value="">Tipo de orden</option>
                        @foreach ($tiposOrden as $tipo)
                            <option value="{{ $tipo->id }}" @selected($cotizacion->tipo_orden_id === $tipo->id)>
                                {{ $tipo->codigo }} — {{ $tipo->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <select name="cliente_direccion_id">
                        <option value="">Sin ubicación asociada</option>
                        @foreach ($direccionesCliente as $direccion)
                            <option value="{{ $direccion->id }}">{{ $direccion->destino ?: $direccion->direccion }}</option>
                        @endforeach
                    </select>
                    <select name="vehiculo_id">
                        <option value="">Sin vehículo asociado</option>
                        @foreach ($vehiculosCliente as $vehiculo)
                            <option value="{{ $vehiculo->id }}">{{ $vehiculo->identificadorVisible() }} · {{ $vehiculo->descripcionVisible() }}</option>
                        @endforeach
                    </select>
                    <textarea name="descripcion" minlength="5" maxlength="500" required
                        placeholder="Descripción del trabajo">{{ $cotizacion->descripcion_trabajo }}</textarea>
                @endif
                <input type="date" name="fecha_apertura" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="orders" :size="17" /> Aprobar y generar orden
                </button>
            </form>
        </section>
    @elseif ($cotizacion->ordenOperacion)
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div>
                <strong>Versión vinculada con una orden</strong>
                <span>
                    Esta cotización originó
                    <a href="{{ route('ordenes-operacion.show', $cotizacion->ordenOperacion) }}">{{ $cotizacion->ordenOperacion->codigo_orden }}</a>.
                </span>
            </div>
        </section>
    @endif

    @if (auth()->user()->puede('proformas.cotizar') && $cotizacion->puedeCrearVersion() && $esUltima)
        <section class="commercial-action-panel commercial-action-panel--primary">
            <div><p class="eyebrow">Solicitud posterior</p><h2>Crear VRS{{ $cotizacion->version + 1 }}</h2><p>Se copiará esta versión y la nueva quedará abierta para modificarla.</p></div>
            <form method="POST" action="{{ route('cotizaciones-cliente.version', $cotizacion) }}">@csrf<button class="button button--ghost" type="submit"><x-ui.icon name="plus" :size="17" /> Nueva versión</button></form>
        </section>
    @endif

    @if ($cotizacion->estaAnulada())
        <section class="notice notice--danger notice--block commercial-cancelled"><x-ui.icon name="error" :size="20" /><div><strong>Versión anulada</strong><span>{{ $cotizacion->motivo_anulacion }} · {{ $cotizacion->anulado_en?->format('d/m/Y H:i') }}</span></div></section>
    @elseif (auth()->user()->puede('proformas.cotizar') && $cotizacion->estado !== 'CONVERTIDA_EN_ORDEN')
        <section class="commercial-danger-zone">
            <div><p class="eyebrow">Control documental</p><h2>Anular esta versión</h2><p>No se eliminará: seguirá visible con usuario, fecha y motivo.</p></div>
            <form method="POST" action="{{ route('cotizaciones-cliente.anular', $cotizacion) }}" class="supplier-quote-cancel-form" data-confirm="¿Confirmas anular esta versión?">@csrf @method('PATCH')<input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required placeholder="Motivo obligatorio"><button type="submit" class="button button--danger"><x-ui.icon name="error" :size="17" /> Anular versión</button></form>
        </section>
    @endif
@endsection
