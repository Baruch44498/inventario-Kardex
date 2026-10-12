@extends('layouts.app')

@section('title', $factura->tipo_documento.' '.$factura->numeroVisible())
@section('page-kicker', 'Factura de proveedor')
@section('page-title', $factura->numeroVisible())

@section('content')
    @php
        $notasVinculadas = $factura->notasIngreso->merge($notasConciliadas)->unique('id')->values();
        $pestanaInicial = match (true) {
            $errors->hasAny(['fecha_pago', 'monto', 'medio_pago', 'referencia', 'observacion']) => 'pagos',
            $errors->any(), $factura->estaAnulada() => 'control',
            ! $factura->tieneRecepcionFisica() => 'recepciones',
            default => 'resumen',
        };
        $saldoFactura = $factura->saldoPendiente();
        $diasVencidos = $saldoFactura > 0 && $factura->fecha_vencimiento?->lt(today())
            ? $factura->fecha_vencimiento->diffInDays(today()) : 0;
    @endphp

    <div class="supplier-invoice-show">
    <a href="{{ route('facturas-proveedor.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a facturas
    </a>

    <section class="module-header supplier-invoice-show-header">
        <div>
            <div class="supplier-invoice-show__title"><h1>{{ $factura->numeroVisible() }}</h1>
                <x-ui.status-badge :tone="$factura->estaAnulada() ? 'danger' : ($saldoFactura <= 0 ? 'success' : ($factura->montoPagado() > 0 ? 'warning' : 'info'))">{{ $factura->estaAnulada() ? 'Anulada' : ($saldoFactura <= 0 ? 'Pagada' : ($factura->montoPagado() > 0 ? 'Pago parcial' : 'Pendiente')) }}</x-ui.status-badge>
                @if ($diasVencidos > 0)<x-ui.status-badge tone="danger">Vencida hace {{ $diasVencidos }} {{ $diasVencidos == 1 ? 'día' : 'días' }}</x-ui.status-badge>@endif
            </div>
            <p>{{ $factura->proveedor?->nombreVisible() }} · RUC {{ $factura->proveedor?->ruc }} · OC {{ $factura->ordenCompra?->codigo }} · Emisión {{ $factura->fecha_emision?->format('d/m/Y') }} · Vencimiento {{ $factura->fecha_vencimiento?->format('d/m/Y') ?? 'sin fecha' }}</p>
        </div>
        <div class="module-header__actions">
            @if ($puedeRegistrarPago && $saldoFactura > 0 && ! $factura->estaAnulada())
                <a href="#pagos-factura" class="button button--primary">Registrar pago</a>
            @endif
        </div>
    </section>

    <div class="supplier-invoice-workspace" data-supplier-invoice-tabs data-default-tab="{{ $pestanaInicial }}">
        <nav class="supplier-invoice-tabs" role="tablist" aria-label="Secciones de la factura de proveedor">
            @foreach ([
                'resumen' => ['Resumen', null],
                'productos' => ['Productos facturados', $factura->detalles->count()],
                'recepciones' => ['Recepciones', $notasVinculadas->count()],
                'pagos' => ['Pagos', $factura->pagosVigentes->count()],
                'control' => ['Historial y control', null],
            ] as $pestana => [$etiqueta, $cantidad])
                <button type="button"
                    id="supplier-invoice-tab-{{ $pestana }}"
                    class="supplier-invoice-tab"
                    role="tab"
                    aria-controls="supplier-invoice-panel-{{ $pestana }}"
                    aria-selected="{{ $pestana === $pestanaInicial ? 'true' : 'false' }}"
                    tabindex="{{ $pestana === $pestanaInicial ? '0' : '-1' }}"
                    data-supplier-invoice-tab="{{ $pestana }}">
                    {{ $etiqueta }}
                    @if ($cantidad !== null)
                        <span class="supplier-invoice-tab__count">{{ $cantidad }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        <div class="supplier-invoice-tab-panels">
            @foreach ([
                'resumen' => '_show_resumen',
                'productos' => '_show_productos',
                'recepciones' => '_show_recepciones',
                'pagos' => '_show_pagos',
                'control' => '_show_control',
            ] as $pestana => $parcial)
                <section id="supplier-invoice-panel-{{ $pestana }}"
                    class="supplier-invoice-tab-panel"
                    role="tabpanel"
                    aria-labelledby="supplier-invoice-tab-{{ $pestana }}"
                    data-supplier-invoice-panel="{{ $pestana }}">
                    @include('facturas_proveedor.partials.'.$parcial)
                </section>
            @endforeach
        </div>
    </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/supplier-invoice-tabs.js') }}" defer></script>
@endpush
