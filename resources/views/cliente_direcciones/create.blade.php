@extends('layouts.app')

@section('title', 'Nueva dirección')
@section('page-kicker', 'Clientes')
@section('page-title', 'Nueva dirección')

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
            <h1>Registrar dirección</h1>
            <p>
                Registra la dirección fiscal o una ubicación adicional del cliente.
            </p>
        </div>
    </section>

    <section class="panel form-panel nested-client-form-panel">
        <form
            method="POST"
            action="{{ route(
                'clientes.direcciones.store',
                $cliente->id
            ) }}"
            data-dirty-form
        >
            @csrf
            @include('cliente_direcciones._form')
        </form>
    </section>
@endsection
