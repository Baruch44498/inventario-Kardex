@extends('layouts.app')

@section('title', $factura->tipo_documento.' '.$factura->numeroVisible())
@section('page-kicker', 'Factura de proveedor')
@section('page-title', $factura->numeroVisible())

@section('content')
    @php
        $notasVinculadas = $factura->notasIngreso->merge($notasConciliadas)->unique('id')->values();
        $pestanaInicial = match (true) {
            $errors->any(), $factura->estaAnulada() => 'control',
            ! $factura->tieneRecepcionFisica() => 'recepciones',
            default => 'resumen',
        };
    @endphp

    <a href="{{ route('facturas-proveedor.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a facturas
    </a>

    <section class="module-header supplier-invoice-show-header">
        <div>
            <p class="eyebrow">{{ $factura->tipo_documento }}</p>
            <h1>{{ $factura->numeroVisible() }}</h1>
            <p>{{ $factura->proveedor?->nombreVisible() }} · OC {{ $factura->ordenCompra?->codigo }}</p>
        </div>
        <div class="module-header__actions">
            <span class="badge badge--{{ $conciliacion['clase'] }}">{{ $conciliacion['etiqueta'] }}</span>
            <span class="badge badge--{{ $factura->estadoClase() }}">{{ $factura->estadoVisible() }}</span>
        </div>
    </section>

    <div class="supplier-invoice-workspace" data-supplier-invoice-tabs data-default-tab="{{ $pestanaInicial }}">
        <nav class="supplier-invoice-tabs" role="tablist" aria-label="Secciones de la factura de proveedor">
            @foreach ([
                'resumen' => ['Resumen', null],
                'productos' => ['Productos facturados', $factura->detalles->count()],
                'recepciones' => ['Recepciones', $notasVinculadas->count()],
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
@endsection

@push('scripts')
    <script src="{{ asset('js/supplier-invoice-tabs.js') }}" defer></script>
@endpush
