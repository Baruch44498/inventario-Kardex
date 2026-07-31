@extends('layouts.app')

@section('title', 'Editar vehículo')
@section('page-kicker', 'Clientes')
@section('page-title', 'Editar vehículo')

@section('content')
    <a
        href="{{ route(
            'clientes.vehiculos.show',
            [$cliente->id, $vehiculo->id]
        ) }}"
        class="back-link"
    >
        <x-ui.icon name="arrow-left" :size="17" />
        Volver al historial
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">{{ $cliente->nombreVisible() }}</p>
            <h1>{{ $vehiculo->identificadorVisible() }}</h1>
            <p>
                Actualiza los datos descriptivos de la unidad.
                La placa permanece como su identificador principal.
            </p>
        </div>
    </section>

    <section class="panel form-panel nested-client-form-panel">
        <form
            method="POST"
            action="{{ route(
                'clientes.vehiculos.update',
                [$cliente->id, $vehiculo->id]
            ) }}"
            data-dirty-form
        >
            @csrf
            @method('PUT')
            @include('vehiculos._form')
        </form>
    </section>
@endsection
