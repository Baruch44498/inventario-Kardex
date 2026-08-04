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
            <p>Ajusta el trabajo, productos, cantidades, precios, moneda e IGV. La versión quedará bloqueada al aprobarla y generar la orden.</p>
        </div>
        <span class="badge badge--info badge--large">ABIERTA</span>
    </section>

    <form method="POST" action="{{ route('cotizaciones-cliente.update', $cotizacion) }}" data-dirty-form data-loading-form>
        @csrf
        @method('PUT')
        @include('cotizaciones_cliente._form')
    </form>
@endsection
