@extends('layouts.app')

@section('title', $nombreModulo)
@section('page-kicker', 'Construcción progresiva')
@section('page-title', $nombreModulo)

@section('content')
    <section class="placeholder-panel">
        <div class="placeholder-panel__icon">
            <x-ui.icon :name="$iconoModulo" :size="35" />
        </div>

        <p class="eyebrow">Módulo preparado</p>
        <h1>{{ $nombreModulo }}</h1>

        <p>
            La navegación y los permisos de este módulo ya están integrados.
            Su funcionalidad visual se incorporará en el siguiente corte
            vertical sin modificar la lógica transaccional existente.
        </p>

        <a href="{{ route('dashboard') }}" class="button button--primary">
            Volver al dashboard
        </a>
    </section>
@endsection
