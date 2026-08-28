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
            <p>Primero define el cliente y el trabajo. Los materiales, la mano de obra y los servicios se cargarán después dentro del componente correspondiente.</p>
        </div>
        <span class="badge badge--info badge--large">ABIERTA</span>
    </section>

    <form method="POST" action="{{ route('cotizaciones-cliente.store') }}" data-dirty-form data-loading-form>
        @csrf
        @include('cotizaciones_cliente._estructura_form')
    </form>
@endsection
