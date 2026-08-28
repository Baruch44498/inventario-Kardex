@extends('layouts.app')

@section('title', 'Nuevo usuario')
@section('page-kicker', 'Usuarios y permisos')
@section('page-title', 'Nuevo usuario')

@section('content')
    <a href="{{ route('usuarios.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a usuarios
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Nueva cuenta</p>
            <h1>Registrar usuario</h1>
            <p>Vincula primero un empleado activo y luego asigna el perfil de acceso.</p>
        </div>
    </section>

    <section class="panel form-panel user-form-panel">
        <form
            method="POST"
            action="{{ route('usuarios.store') }}"
            data-dirty-form
            data-loading-form
        >
            @csrf
            @include('usuarios._form')
        </form>
    </section>
@endsection
