@extends('layouts.app')

@section('title', $cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Detalle de cotización')

@section('content')
    <a href="{{ route('cotizaciones-proveedor.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a cotizaciones
    </a>

    <section class="supplier-quote-hero">
        <div>
            <p class="eyebrow">Cotización de proveedor</p>
            <div class="supplier-quote-hero__identity">
                <h1>{{ $cotizacion->codigo }}</h1>
                <x-ui.status-badge :tone="$cotizacion->estadoClase()">{{ $cotizacion->estadoVisible() }}</x-ui.status-badge>
            </div>
            <p>
                {{ $cotizacion->proveedor->nombreVisible() }}
                · {{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}
                · {{ $cotizacion->moneda }}
            </p>
        </div>

        <div class="supplier-quote-hero__actions">
            @if ($cotizacion->solicitudCompra?->ordenCompra && $puedeGestionarCompras)
                <a href="{{ route('ordenes-compra.show', $cotizacion->solicitudCompra->ordenCompra) }}" class="button button--primary">
                    <x-ui.icon name="purchase-order" :size="17" /> Ver orden de compra
                </a>
            @endif
            @if ($cotizacion->puedeEditar())
                <a href="{{ route('cotizaciones-proveedor.edit', $cotizacion->id) }}"
                    class="button button--ghost">
                    <x-ui.icon name="edit" :size="17" /> Editar
                </a>
            @endif

            <a href="{{ route('historial-precios.index', ['proveedor_id' => $cotizacion->proveedor_id]) }}"
                class="button button--ghost">
                <x-ui.icon name="banknote" :size="17" /> Historial de precios
            </a>
        </div>
    </section>

    @php
        $pestanaInicial = $errors->has('motivo_anulacion') ? 'control' : ($errors->any() ? 'compra' : 'resumen');
    @endphp

    <div class="supplier-quote-workspace"
        data-supplier-quote-tabs
        data-default-tab="{{ $pestanaInicial }}">
        <nav class="supplier-quote-tabs" role="tablist" aria-label="Secciones de la cotización">
            @foreach ([
                'resumen' => 'Resumen',
                'productos' => 'Productos y precios',
                'compra' => 'Decisión de compra',
                'control' => 'Control documental',
            ] as $pestana => $etiqueta)
                <button
                    type="button"
                    id="supplier-quote-tab-{{ $pestana }}"
                    class="supplier-quote-tab"
                    role="tab"
                    aria-controls="supplier-quote-panel-{{ $pestana }}"
                    aria-selected="{{ $pestana === $pestanaInicial ? 'true' : 'false' }}"
                    tabindex="{{ $pestana === $pestanaInicial ? '0' : '-1' }}"
                    data-supplier-quote-tab="{{ $pestana }}">
                    {{ $etiqueta }}
                    @if ($pestana === 'productos')
                        <span class="supplier-quote-tab__count">{{ $cotizacion->detalles->count() }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        <div class="supplier-quote-tab-panels">
            @foreach ([
                'resumen' => '_show_resumen',
                'productos' => '_show_productos',
                'compra' => '_show_decision_compra',
                'control' => '_show_control',
            ] as $pestana => $parcial)
                <section
                    id="supplier-quote-panel-{{ $pestana }}"
                    class="supplier-quote-tab-panel"
                    role="tabpanel"
                    aria-labelledby="supplier-quote-tab-{{ $pestana }}"
                    data-supplier-quote-panel="{{ $pestana }}">
                    @include('cotizaciones_proveedor.partials.'.$parcial)
                </section>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/supplier-quote-tabs.js') }}" defer></script>
    <script src="{{ asset('js/supplier-quote-products.js') }}" defer></script>
@endpush
