@extends('layouts.app')

@section('title', 'Editar dirección fiscal')
@section('page-kicker', 'Clientes')
@section('page-title', 'Editar dirección fiscal')

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
            <h1>{{ $direccion->destino ?: 'Dirección fiscal' }}</h1>
            <p>
                Actualiza la dirección de referencia del cliente.
                Este registro no consulta SUNAT ni genera comprobantes.
            </p>
        </div>
    </section>

    <section class="panel form-panel nested-client-form-panel">
        <form
            method="POST"
            action="{{ route(
                'clientes.direcciones.update',
                [$cliente->id, $direccion->id]
            ) }}"
            data-dirty-form
        >
            @csrf
            @method('PUT')
            @include('cliente_direcciones._form')
        </form>
    </section>
@endsection
