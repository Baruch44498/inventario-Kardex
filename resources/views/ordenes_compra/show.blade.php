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
        $pestanaInicial = match (true) {
            $errors->any(), $orden->estaAnulada() => 'historial',
            $orden->permiteRecepcion() => 'productos',
            $orden->notasIngreso->isNotEmpty(), $orden->facturasProveedor->isNotEmpty() => 'documentos',
            default => 'resumen',
        };
    @endphp

    <a href="{{ route('ordenes-compra.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a órdenes
    </a>

    <section class="module-header purchase-order-hero">
        <div>
            <p class="eyebrow">Compra autorizada</p>
            <h1>{{ $orden->codigo }}</h1>
            <p>{{ $orden->proveedor?->nombreVisible() }} · Emitida el {{ $orden->fecha_emision?->format('d/m/Y') }}</p>
        </div>
        <div class="module-header__actions">
            <span class="badge badge--{{ $orden->estadoClase() }}">{{ $orden->estadoVisible() }}</span>
            @if ($orden->permiteRecepcion())
                <span class="badge badge--{{ $orden->situacionEntregaClase() }}">{{ $orden->situacionEntregaVisible() }}</span>
            @endif
            @if ($puedeRegistrarFactura && ! $orden->estaAnulada() && $tieneRecepcionPendienteFacturar)
                <a href="{{ route('facturas-proveedor.create', $orden) }}" class="button button--primary"><x-ui.icon name="invoice" :size="17" /> Registrar factura</a>
            @endif
            @if ($orden->permiteRecepcion() && $puedeRegistrarIngreso)
                <a href="{{ route('notas-ingreso.create', ['motivo_ingreso' => 'COMPRA', 'orden_compra_id' => $orden->id]) }}" class="button button--ghost"><x-ui.icon name="entry" :size="17" /> Registrar recepción</a>
            @endif
        </div>
    </section>

    <div class="purchase-order-workspace"
        data-purchase-order-tabs
        data-default-tab="{{ $pestanaInicial }}">
        <nav class="purchase-order-tabs" role="tablist" aria-label="Secciones de la orden de compra">
            @foreach ([
                'resumen' => ['Resumen', null],
                'productos' => ['Productos y entregas', $orden->detalles->count()],
                'documentos' => ['Recepciones y facturas', $orden->notasIngreso->count() + $orden->facturasProveedor->count()],
                'historial' => ['Historial', null],
            ] as $pestana => [$etiqueta, $cantidad])
                <button type="button"
                    id="purchase-order-tab-{{ $pestana }}"
                    class="purchase-order-tab"
                    role="tab"
                    aria-controls="purchase-order-panel-{{ $pestana }}"
                    aria-selected="{{ $pestana === $pestanaInicial ? 'true' : 'false' }}"
                    tabindex="{{ $pestana === $pestanaInicial ? '0' : '-1' }}"
                    data-purchase-order-tab="{{ $pestana }}">
                    {{ $etiqueta }}
                    @if ($cantidad !== null)
                        <span class="purchase-order-tab__count">{{ $cantidad }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        <div class="purchase-order-tab-panels">
            @foreach ([
                'resumen' => '_show_resumen',
                'productos' => '_show_productos_entregas',
                'documentos' => '_show_recepciones_facturas',
                'historial' => '_show_historial',
            ] as $pestana => $parcial)
                <section id="purchase-order-panel-{{ $pestana }}"
                    class="purchase-order-tab-panel"
                    role="tabpanel"
                    aria-labelledby="purchase-order-tab-{{ $pestana }}"
                    data-purchase-order-panel="{{ $pestana }}">
                    @include('ordenes_compra.partials.'.$parcial)
                </section>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/purchase-order-tabs.js') }}" defer></script>
@endpush
