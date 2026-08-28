@extends('layouts.app')

@section('title', 'Nuevo empleado')
@section('page-kicker', 'Catálogo de empleados')
@section('page-title', 'Nuevo empleado')

@section('content')
    <a href="{{ route('empleados.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a empleados
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Nuevo registro</p>
            <h1>Registrar empleado</h1>
            <p>Estos datos permitirán identificar quién recibe materiales de Almacén.</p>
        </div>
    </section>

    <section class="panel form-panel user-form-panel">
        <form
            method="POST"
            action="{{ route('empleados.store') }}"
            data-dirty-form
            data-loading-form
        >
            @csrf
            @include('empleados._form')
        </form>
    </section>
@endsection
