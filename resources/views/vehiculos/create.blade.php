@extends('layouts.app')

@section('title', 'Nuevo vehículo')
@section('page-kicker', 'Clientes')
@section('page-title', 'Nuevo vehículo')

@section('content')
    <a
        href="{{ route('clientes.show', $cliente->id) }}"
        class="back-link"
    >
        <x-ui.icon name="arrow-left" :size="17" />
        Volver al cliente
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">{{ $cliente->nombreVisible() }}</p>
            <h1>Registrar vehículo</h1>
            <p>
                La placa identifica la unidad y permitirá consultar su
                historial completo de órdenes.
            </p>
        </div>
    </section>

    <section class="panel form-panel nested-client-form-panel">
        <form
            method="POST"
            action="{{ route(
                'clientes.vehiculos.store',
                $cliente->id
            ) }}"
            data-dirty-form
        >
            @csrf
            @include('vehiculos._form')
        </form>
    </section>
@endsection
