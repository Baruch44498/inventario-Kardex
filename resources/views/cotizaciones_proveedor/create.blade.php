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
    </section>

    <form method="POST" action="{{ route('cotizaciones-proveedor.store') }}" data-dirty-form>
        @csrf
        @include('cotizaciones_proveedor._form')
    </form>
@endsection
