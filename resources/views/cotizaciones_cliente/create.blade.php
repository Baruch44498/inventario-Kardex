@extends('layouts.app')

@section('title', 'Nueva cotización')
@section('page-kicker', 'Ventas')
@section('page-title', 'Nueva cotización')

@section('content')
    <a href="{{ route('cotizaciones-cliente.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a cotizaciones
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Venta simple</p>
            <h1>Nueva cotización al cliente</h1>
            <p>Registra el cliente, los productos, precios, IGV y condiciones comerciales. Al aprobarla se generará una Orden de Venta.</p>
        </div>
        <span class="badge badge--info badge--large">ABIERTA</span>
    </section>

    <form method="POST" action="{{ route('cotizaciones-cliente.store') }}" data-dirty-form data-loading-form>
        @csrf
        @include('cotizaciones_cliente._form')
    </form>
@endsection
