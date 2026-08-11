@extends('layouts.app')

@section('title', 'Nueva cotización de proveedor')
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Nueva cotización')

@section('content')
    <a href="{{ route('cotizaciones-proveedor.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a cotizaciones
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Oferta recibida</p>
            <h1>Registrar cotización de proveedor</h1>
            <p>Guarda los precios ofrecidos. No genera inventario ni compromete una compra.</p>
        </div>
        @if (! isset($importacionAsistida) && request('requisicion_id'))
            <div class="module-header__actions">
                <a href="{{ route('cotizaciones-proveedor.importacion.create', ['requisicion_id' => request('requisicion_id'), 'proveedor_id' => request('proveedor_id')]) }}" class="button button--ghost">
                    <x-ui.icon name="upload" :size="16" /> Importar PDF / Excel
                </a>
            </div>
        @endif
    </section>

    @if (isset($importacionAsistida) && $importacionAsistida)
        <div class="supplier-quote-import-review">
            <div class="supplier-quote-import-review__icon"><x-ui.icon name="file" :size="22" /></div>
            <div class="supplier-quote-import-review__copy">
                <p class="eyebrow">Vista previa obligatoria</p>
                <strong>{{ $importacionAsistida->nombre_original }}</strong>
                <span>Origen: {{ $importacionAsistida->tipo_archivo }}. Revisa proveedor, documento, productos, cantidades, precios, descuentos e IGV antes de registrar.</span>
                @if (collect($importacionAsistida->advertencias)->isNotEmpty())
                    <details>
                        <summary>{{ collect($importacionAsistida->advertencias)->count() }} advertencia{{ collect($importacionAsistida->advertencias)->count() === 1 ? '' : 's' }} de extracción</summary>
                        <ul>
                            @foreach (collect($importacionAsistida->advertencias) as $advertencia)
                                <li>{{ $advertencia }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
            <form method="POST" action="{{ route('cotizaciones-proveedor.importacion.destroy', $importacionAsistida) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="button button--ghost button--small">Descartar</button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('cotizaciones-proveedor.store') }}" data-dirty-form>
        @csrf
        @include('cotizaciones_proveedor._form')
    </form>
@endsection
