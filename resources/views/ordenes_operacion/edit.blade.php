@extends('layouts.app')

@section('title', 'Editar ' . $orden->codigo_orden)
@section('page-kicker', 'Órdenes de operación')
@section('page-title', 'Editar orden')

@section('content')
    <a href="{{ route('ordenes-operacion.show', $orden->id) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a {{ $orden->codigo_orden }}
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Contexto operacional</p>
            <h1>Editar {{ $orden->codigo_orden }}</h1>
            <p>
                Actualiza el contexto de la operación sin alterar su tipo,
                correlativo ni historial.
            </p>
        </div>
    </section>

    <section class="panel form-panel operation-form-panel">
        <form
            method="POST"
            action="{{ route('ordenes-operacion.update', $orden->id) }}"
            data-loading-form
            data-dirty-form
            data-order-form
            data-editing="true"
        >
            @include('ordenes_operacion._form')
        </form>
    </section>
@endsection
