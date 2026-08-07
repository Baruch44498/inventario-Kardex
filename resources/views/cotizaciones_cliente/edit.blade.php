@extends('layouts.app')

@section('title', 'Editar '.$cotizacion->codigo)
@section('page-kicker', 'Cotización al cliente')
@section('page-title', 'Negociación abierta')

@section('content')
    <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver al detalle
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Versión editable por Logística</p>
            <h1>{{ $cotizacion->codigo }}</h1>
            <p>{{ $cotizacion->proforma ? 'Ajusta precios, moneda, IGV y condiciones de las líneas de venta. Esta cotización no genera OV.' : 'Ajusta el trabajo, productos, cantidades, precios, moneda e IGV. La versión quedará vinculada a la orden al aprobarla.' }}</p>
        </div>
        <span class="badge badge--info badge--large">ABIERTA</span>
    </section>

    <form method="POST" action="{{ route('cotizaciones-cliente.update', $cotizacion) }}" data-dirty-form data-loading-form>
        @csrf
        @method('PUT')
        @include('cotizaciones_cliente._form')
    </form>
@endsection
