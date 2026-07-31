@extends('layouts.app')

@section('title', 'Editar cliente')
@section('page-kicker', 'Clientes')
@section('page-title', 'Editar cliente')

@section('content')
    <a href="{{ route('clientes.show', $cliente->id) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver al cliente
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Datos comerciales</p>
            <h1>{{ $cliente->nombreVisible() }}</h1>
            <p>Actualiza los datos generales sin alterar la trazabilidad de sus órdenes.</p>
        </div>

        @if ($cliente->es_mostrador)
            <span class="badge badge--info">REGISTRO DEL SISTEMA</span>
        @endif
    </section>

    <section class="panel form-panel client-form-panel">
        <form method="POST" action="{{ route('clientes.update', $cliente->id) }}" data-dirty-form>
            @csrf
            @method('PUT')
            @include('clientes._form')
        </form>
    </section>
@endsection
