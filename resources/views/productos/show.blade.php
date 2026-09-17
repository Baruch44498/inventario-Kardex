@extends('layouts.app')

@section('title', $producto->codigo)
@section('page-kicker', 'Productos')
@section('page-title', 'Detalle del producto')

@section('content')
    @php
        $tabs = [
            'existencias' => [
                'label' => 'Existencias',
                'icon' => 'inventory',
                'partial' => '_show_existencias',
                'count' => $inventarios->count(),
            ],
            'presentaciones' => [
                'label' => 'Presentaciones',
                'icon' => 'box',
                'partial' => '_show_presentaciones',
                'count' => $presentaciones->count(),
            ],
            'ubicaciones' => [
                'label' => 'Ubicaciones',
                'icon' => 'shelf',
                'partial' => '_show_ubicaciones',
                'count' => $inventarios->count(),
            ],
            'precios' => [
                'label' => 'Precios',
                'icon' => 'banknote',
                'partial' => '_show_precios',
                'count' => $puedeVerPrecios ? $precios->count() : null,
            ],
            'movimientos' => [
                'label' => 'Movimientos',
                'icon' => 'movements',
                'partial' => '_show_movimientos',
                'count' => $puedeVerMovimientos ? $movimientos->count() : null,
            ],
        ];
    @endphp

    <a href="{{ route('productos.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a productos
    </a>

    <section class="module-header">
        <div>
            <div class="title-with-status">
                <h1>{{ $producto->codigo }}</h1>
                <span class="badge badge--{{ $producto->activo ? 'success' : 'neutral' }}">
                    {{ $producto->activo ? 'ACTIVO' : 'INACTIVO' }}
                </span>
            </div>
            <p>{{ $producto->descripcion }}</p>
        </div>

        <a href="{{ route('productos.edit', $producto->id_producto) }}" class="button button--primary">
            <x-ui.icon name="edit" :size="18" />
            Editar producto
        </a>
    </section>

    <section class="product-detail-workspace" data-product-detail-tabs data-default-tab="existencias">
        <div class="product-detail-tabs" role="tablist" aria-label="Secciones del producto">
            @foreach ($tabs as $tab => $config)
                <button
                    type="button"
                    id="producto-tab-{{ $tab }}"
                    class="product-detail-tab"
                    role="tab"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    aria-controls="producto-panel-{{ $tab }}"
                    tabindex="{{ $loop->first ? '0' : '-1' }}"
                    data-product-detail-tab="{{ $tab }}"
                >
                    <x-ui.icon :name="$config['icon']" :size="17" />
                    <span>{{ $config['label'] }}</span>
                    @if ($config['count'] !== null)
                        <span class="product-detail-tab__count">{{ $config['count'] }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @foreach ($tabs as $tab => $config)
            <div
                id="producto-panel-{{ $tab }}"
                class="product-detail-tab-panel"
                role="tabpanel"
                aria-labelledby="producto-tab-{{ $tab }}"
                data-product-detail-panel="{{ $tab }}"
                @if (! $loop->first) hidden @endif
            >
                @include('productos.partials.'.$config['partial'])
            </div>
        @endforeach
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/product-detail-tabs.js') }}" defer></script>
@endpush
