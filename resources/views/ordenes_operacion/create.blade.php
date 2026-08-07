@extends('layouts.app')

@section('title', 'Nueva orden de operación')
@section('page-kicker', 'Órdenes de operación')
@section('page-title', 'Nueva orden')

@section('content')
    <a href="{{ route('ordenes-operacion.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a órdenes de operación
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Contexto operacional</p>
            <h1>Registrar orden de operación</h1>
            <p>
                Crea una orden OP, OS u OM para relacionar el trabajo con
                requisiciones y entregas de almacén.
            </p>
        </div>
    </section>

    <div class="notice notice--info notice--block">
        <x-ui.icon name="info" :size="18" />
        <span>
            El código se genera automáticamente con el tipo, correlativo anual y año.
            Cliente, dirección y vehículo son opcionales.
        </span>
    </div>

    <section class="panel form-panel operation-form-panel">
        <form
            method="POST"
            action="{{ route('ordenes-operacion.store') }}"
            data-loading-form
            data-dirty-form
            data-order-form
            data-editing="false"
        >
            @include('ordenes_operacion._form')
        </form>
    </section>
@endsection
