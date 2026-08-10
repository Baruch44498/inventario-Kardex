@extends('layouts.app')

@section('title', 'Nueva cotización')
@section('page-kicker', 'Comercial y logística')
@section('page-title', 'Cotización directa')

@section('content')
    <a href="{{ route('cotizaciones-cliente.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a cotizaciones
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Creación directa por Logística</p>
            <h1>Nueva cotización al cliente</h1>
            <p>Define el trabajo y sus materiales según el tipo de orden. En Producción la composición queda interna; en Mantenimiento y Servicio los materiales/repuestos forman parte del detalle comercial.</p>
        </div>
        <span class="badge badge--info badge--large">ABIERTA</span>
    </section>

    <form method="POST" action="{{ route('cotizaciones-cliente.store') }}" data-dirty-form data-loading-form>
        @csrf
        @include('cotizaciones_cliente._form')
    </form>
@endsection
