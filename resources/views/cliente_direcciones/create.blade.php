@extends('layouts.app')

@section('title', 'Nueva dirección fiscal')
@section('page-kicker', 'Clientes')
@section('page-title', 'Nueva dirección fiscal')

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
            <h1>Registrar dirección fiscal</h1>
            <p>
                Agrega este dato únicamente cuando esté disponible.
                No es obligatorio para registrar ni utilizar al cliente.
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
