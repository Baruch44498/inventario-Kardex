@extends('layouts.app')

@section('title', $proforma->codigo)
@section('page-kicker', 'Proformas')
@section('page-title', 'Detalle de proforma')

@section('content')
    @php
        $tono = match ($proforma->estado) {
            'ANULADA' => 'danger',
            'COTIZADA', 'CONVERTIDA_EN_ORDEN' => 'success',
            'ENVIADA_A_LOGISTICA' => 'warning',
            default => 'info',
        };
        $ultimaCotizacion = $proforma->cotizacionesCliente->last();
    @endphp

    <a href="{{ route('proformas.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a proformas
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div>
            <p class="eyebrow">{{ $proforma->origenVisible() }}</p>
            <h1>{{ $proforma->codigo }}</h1>
            <p>
                {{ $proforma->cliente?->nombreVisible() ?: 'Cliente pendiente de definir' }}
                · {{ $proforma->fecha_emision->format('d/m/Y') }}
                · {{ $proforma->moneda }}
            </p>
        </div>
        <div class="supplier-quote-hero__actions">
            <span class="badge badge--{{ $tono }} badge--large">{{ str_replace('_', ' ', $proforma->estado) }}</span>
            @if (auth()->user()->puede('proformas.crear') && $proforma->esEditable())
                <a href="{{ route('proformas.edit', $proforma) }}" class="button button--ghost">
                    <x-ui.icon name="edit" :size="17" /> Editar borrador
                </a>
            @endif
        </div>
    </section>

    <section class="commercial-flow" aria-label="Flujo documental">
        @foreach ([
            ['Proforma de Almacén', true],
            ['Cotización abierta', $proforma->cotizacionesCliente->isNotEmpty()],
            ['Cotización cerrada', $proforma->cotizacionesCliente->contains('estado', 'CERRADA') || $proforma->estado === 'CONVERTIDA_EN_ORDEN'],
            ['Orden de venta', $proforma->estado === 'CONVERTIDA_EN_ORDEN'],
        ] as [$etiqueta, $completado])
            <div class="commercial-flow__step {{ $completado ? 'is-complete' : '' }}">
                <span><x-ui.icon :name="$completado ? 'check-circle' : 'clipboard'" :size="18" /></span>
                <strong>{{ $etiqueta }}</strong>
            </div>
        @endforeach
    </section>

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading">
                <div><p class="eyebrow">Información</p><h2>Datos de la solicitud</h2></div>
            </header>
            <dl class="supplier-info-grid">
                <div><dt>Origen</dt><dd>{{ $proforma->origenVisible() }}</dd></div>
                <div><dt>Cliente</dt><dd>{{ $proforma->cliente?->nombreVisible() ?: 'Por definir en Logística' }}</dd></div>
                <div><dt>Tipo de cliente</dt><dd>{{ $proforma->cliente?->tipoCliente?->nombre ?: 'Pendiente' }}</dd></div>
                <div><dt>Margen sugerido</dt><dd>{{ number_format((float) $proforma->margen_cliente_porcentaje, 2) }} %</dd></div>
                <div><dt>Registrada por</dt><dd>{{ $proforma->registrador?->nombreVisible() }}</dd></div>
                <div><dt>Enviada por</dt><dd>{{ $proforma->enviador?->nombreVisible() ?: 'Todavía no enviada' }}</dd></div>
                <div><dt>Condiciones de pago</dt><dd>{{ $proforma->condiciones_pago ?: 'No especificadas' }}</dd></div>
                <div><dt>Condiciones de entrega</dt><dd>{{ $proforma->condiciones_entrega ?: 'No especificadas' }}</dd></div>
                @if ($proforma->observacion)
                    <div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $proforma->observacion }}</dd></div>
                @endif
            </dl>
        </article>

        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Valor sugerido</p>
            <div><span>Subtotal</span><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $proforma->subtotal, 2) }}</strong></div>
            <div><span>IGV</span><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $proforma->impuesto, 2) }}</strong></div>
            <div class="supplier-quote-total-card__main"><span>Total</span><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $proforma->total, 2) }}</strong></div>
            <small>Referencia interna; no reserva ni descuenta inventario.</small>
        </article>
    </section>

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div><p class="eyebrow">Productos</p><h2>Detalle preparado por Almacén</h2></div>
        </header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Costo ref.</th><th class="text-right">Sugerido</th><th>IGV</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @foreach ($proforma->detalles as $detalle)
                        <tr>
                            <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                            <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                            <td class="text-right">S/ {{ number_format((float) $detalle->costo_referencia, 2) }}</td>
                            <td class="text-right"><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $detalle->precio_sugerido, 2) }}</strong></td>
                            <td>{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                            <td class="text-right"><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $detalle->total, 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($proforma->cotizacionesCliente->isNotEmpty())
        <section class="panel commercial-history-panel">
            <header class="supplier-panel-heading">
                <div><p class="eyebrow">Trazabilidad</p><h2>Historial de cotizaciones</h2></div>
            </header>
            <div class="commercial-version-list">
                @foreach ($proforma->cotizacionesCliente as $cotizacion)
                    @php
                        $tonoVersion = match ($cotizacion->estado) {
                            'ANULADA' => 'danger',
                            'CERRADA', 'CONVERTIDA_EN_ORDEN' => 'success',
                            default => 'info',
                        };
                    @endphp
                    <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}" class="commercial-version-card">
                        <span class="commercial-version-card__number">VRS{{ $cotizacion->version }}</span>
                        <span><strong>{{ $cotizacion->codigo }}</strong><small>{{ $cotizacion->fecha_emision->format('d/m/Y') }} · {{ $cotizacion->cliente_nombre }}</small></span>
                        <span class="badge badge--{{ $tonoVersion }}">{{ $cotizacion->estado }}</span>
                        <strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2) }}</strong>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if (auth()->user()->puede('proformas.crear') && $proforma->puedeEnviarse())
        <section class="commercial-action-panel commercial-action-panel--primary">
            <div><p class="eyebrow">Enviar a Logística</p><h2>La proforma está lista</h2><p>Después de enviarla quedará bloqueada para Almacén.</p></div>
            <form method="POST" action="{{ route('proformas.enviar', $proforma) }}" data-confirm="¿Enviar esta proforma a Logística?">
                @csrf @method('PATCH')
                <button class="button button--primary" type="submit"><x-ui.icon name="check-circle" :size="17" /> Enviar a Logística</button>
            </form>
        </section>
    @endif

    @if (auth()->user()->puede('proformas.cotizar') && $proforma->estado === 'ENVIADA_A_LOGISTICA')
        <section class="commercial-action-panel commercial-action-panel--primary">
            <div>
                <p class="eyebrow">Acción de Logística</p>
                <h2>Crear cotización abierta</h2>
                <p>Se generará {{ $ultimaCotizacion ? 'una nueva cotización' : 'la VRS1' }} para negociar precios.</p>
            </div>
            <form method="POST" action="{{ route('proformas.cotizar', $proforma) }}" class="commercial-quote-start">
                @csrf
                @if (! $proforma->cliente_id)
                    <div class="form-field">
                        <label for="cliente_cotizacion_busqueda">Cliente <span class="required-mark">*</span></label>
                        <x-ui.remote-combobox
                            name="cliente_id"
                            search-id="cliente_cotizacion_busqueda"
                            value-id="cliente_cotizacion_id"
                            :search-url="route('catalogos.clientes.buscar')"
                            placeholder="Documento o nombre"
                            empty-text="Cliente no encontrado. Regístralo primero."
                            :required="true"
                        />
                    </div>
                @endif
                <button class="button button--primary" type="submit"><x-ui.icon name="quotes" :size="17" /> Cotizar ahora</button>
            </form>
        </section>
    @endif

    @if ($proforma->estaAnulada())
        <section class="notice notice--danger notice--block commercial-cancelled">
            <x-ui.icon name="error" :size="20" />
            <div><strong>Proforma anulada</strong><span>{{ $proforma->motivo_anulacion }} · {{ $proforma->anulado_en?->format('d/m/Y H:i') }}</span></div>
        </section>
    @elseif (auth()->user()->puede('proformas.cotizar') && ! in_array($proforma->estado, ['BORRADOR', 'CONVERTIDA_EN_ORDEN'], true))
        <section class="commercial-danger-zone">
            <div><p class="eyebrow">Control documental</p><h2>Anular proforma</h2><p>El motivo, usuario y fecha quedarán registrados. Las cotizaciones cerradas seguirán visibles.</p></div>
            <form method="POST" action="{{ route('proformas.anular', $proforma) }}" class="supplier-quote-cancel-form" data-confirm="¿Confirmas anular esta proforma?">
                @csrf @method('PATCH')
                <input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required placeholder="Motivo obligatorio">
                <button type="submit" class="button button--danger"><x-ui.icon name="error" :size="17" /> Anular</button>
            </form>
        </section>
    @endif
@endsection
