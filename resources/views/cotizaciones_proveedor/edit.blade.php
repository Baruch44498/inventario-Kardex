@extends('layouts.app')

@section('title', 'Editar cotización')
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Editar cotización')

@section('content')
    <a href="{{ route('cotizaciones-proveedor.show', $cotizacion->id) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a la cotización
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">{{ $cotizacion->codigo }}</p>
            <h1>Editar cotización</h1>
            <p>Los cambios actualizan el historial mientras el documento no haya sido utilizado.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('cotizaciones-proveedor.update', $cotizacion->id) }}"
        data-dirty-form>
        @csrf
        @method('PUT')
        @include('cotizaciones_proveedor._form')
    </form>
@endsection
