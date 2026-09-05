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
            <p>Primero define el cliente y la orden principal. Después podrás cargar las áreas, materiales y demás costos manualmente, desde una plantilla o importando el Excel.</p>
        </div>
        <span class="badge badge--info badge--large">ABIERTA</span>
    </section>

    <form method="POST" action="{{ route('cotizaciones-cliente.store') }}" data-dirty-form data-loading-form>
        @csrf
        @include('cotizaciones_cliente._estructura_form')
    </form>
@endsection
