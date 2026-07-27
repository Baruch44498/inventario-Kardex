@extends('layouts.app')

@section('title', 'Nuevo producto')
@section('page-kicker', 'Productos')
@section('page-title', 'Nuevo producto')

@section('content')
    <a href="{{ route('productos.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a productos
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Catálogo maestro</p>
            <h1>Registrar producto</h1>
            <p>
                Crea el registro maestro. El stock se asignará mediante
                operaciones de inventario, no desde este formulario.
            </p>
        </div>
    </section>

    <section class="panel form-panel">
        <form method="POST" action="{{ route('productos.store') }}" data-loading-form data-dirty-form>
            @include('productos._form')
        </form>
    </section>
@endsection
