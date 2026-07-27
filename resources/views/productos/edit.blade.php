@extends('layouts.app')

@section('title', 'Editar producto')
@section('page-kicker', 'Productos')
@section('page-title', 'Editar producto')

@section('content')
    <a
        href="{{ route('productos.index') }}"
        class="back-link"
    >
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a productos
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Edición de catálogo</p>
            <h1>{{ $producto->codigo }}</h1>
            <p>
                Modifica los datos maestros sin alterar las existencias
                registradas.
            </p>
        </div>
    </section>

    <section class="panel form-panel">
        <form
            method="POST"
            action="{{ route('productos.update', $producto->id_producto) }}"
            data-loading-form
            data-dirty-form
        >
            @include('productos._form')
        </form>
    </section>
@endsection
