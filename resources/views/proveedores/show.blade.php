@extends('layouts.app')

@section('title', $proveedor->nombreVisible())
@section('page-kicker', 'Proveedores')
@section('page-title', 'Detalle del proveedor')

@section('content')
    <a href="{{ route('proveedores.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a proveedores
    </a>

    <section class="supplier-detail-hero">
        <div>
            <p class="eyebrow">Proveedor registrado</p>
            <h1>{{ $proveedor->nombreVisible() }}</h1>
            <p>
                RUC {{ $proveedor->ruc }}
                @if ($proveedor->nombre_comercial)
                    · {{ $proveedor->razon_social }}
                @endif
            </p>
        </div>

        <div class="supplier-detail-hero__actions">
            <span class="badge badge--{{ $proveedor->estado ? 'success' : 'danger' }}">
                {{ $proveedor->estado ? 'ACTIVO' : 'INACTIVO' }}
            </span>
            <a href="{{ route('cotizaciones-proveedor.create', ['proveedor_id' => $proveedor->id]) }}"
                class="button button--primary">
                <x-ui.icon name="quotes" :size="17" /> Registrar cotización
            </a>
            <a href="{{ route('proveedores.edit', $proveedor->id) }}" class="button button--ghost">
                <x-ui.icon name="edit" :size="17" /> Editar
            </a>
        </div>
    </section>

    <div class="supplier-detail-sections" data-supplier-detail-tabs-root>
        <nav class="supplier-detail-tabs" role="tablist" aria-label="Secciones del proveedor" data-supplier-detail-tabs>
            <button id="supplier-tab-datos" type="button" role="tab" aria-selected="true" aria-controls="datos-proveedor" tabindex="0" data-supplier-detail-tab="datos">
                Datos
            </button>
            <button id="supplier-tab-productos" type="button" role="tab" aria-selected="false" aria-controls="productos-precios" tabindex="-1" data-supplier-detail-tab="productos">
                Productos y precios
            </button>
            <button id="supplier-tab-documentos" type="button" role="tab" aria-selected="false" aria-controls="documentos-proveedor" tabindex="-1" data-supplier-detail-tab="documentos">
                Documentos
            </button>
            <button id="supplier-tab-historial" type="button" role="tab" aria-selected="false" aria-controls="historial-proveedor" tabindex="-1" data-supplier-detail-tab="historial">
                Historial
            </button>
        </nav>

        <section id="datos-proveedor" class="supplier-detail-panel" role="tabpanel" aria-labelledby="supplier-tab-datos" data-supplier-detail-panel="datos">
            @include('proveedores.partials._show_datos')
        </section>

        <section id="productos-precios" class="supplier-detail-panel" role="tabpanel" aria-labelledby="supplier-tab-productos" data-supplier-detail-panel="productos">
            @include('proveedores.partials._show_productos_precios')
        </section>

        <section id="documentos-proveedor" class="supplier-detail-panel" role="tabpanel" aria-labelledby="supplier-tab-documentos" data-supplier-detail-panel="documentos">
            @include('proveedores.partials._show_documentos')
        </section>

        <section id="historial-proveedor" class="supplier-detail-panel" role="tabpanel" aria-labelledby="supplier-tab-historial" data-supplier-detail-panel="historial">
            @include('proveedores.partials._show_historial')
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/supplier-detail-tabs.js') }}" defer></script>
@endpush
