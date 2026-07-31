@extends('layouts.app')

@section('title', 'Nuevo cliente')
@section('page-kicker', 'Clientes')
@section('page-title', 'Nuevo cliente')

@section('content')
    <a href="{{ route('clientes.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a clientes
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Registro comercial</p>
            <h1>Registrar cliente</h1>
            <p>
                Registra la identificación y los datos operativos.
                Este módulo no realiza consultas tributarias ni facturación
                automática.
            </p>
        </div>
    </section>

    <section class="panel form-panel client-form-panel">
        <form
            method="POST"
            action="{{ route('clientes.store') }}"
            data-dirty-form
        >
            @csrf
            @include('clientes._form')
        </form>
    </section>
@endsection
