@extends('layouts.app')

@section('title', $nombreModulo)
@section('page-kicker', 'Módulo en planificación')
@section('page-title', $nombreModulo)

@section('content')
    <section class="placeholder-panel module-placeholder-panel">
        <span class="module-placeholder-panel__icon">
            <x-ui.icon name="clipboard" :size="34" />
        </span>

        <p class="eyebrow">Próximamente</p>

        <h1>{{ $nombreModulo }}</h1>

        <p>
            Este módulo forma parte del alcance aprobado, pero todavía no ha
            sido implementado. La navegación y los permisos ya están
            preparados para integrarlo en los siguientes bloques.
        </p>

        <div class="module-placeholder-panel__meta">
            <span class="badge badge--info">
                Perfil: {{ auth()->user()->role?->nombre ?? 'Sin rol' }}
            </span>

            <span class="badge badge--neutral">
                Sin datos ni operaciones habilitadas
            </span>
        </div>

        <div class="module-placeholder-panel__actions">
            <a href="{{ route('dashboard') }}" class="button button--primary">
                <x-ui.icon name="dashboard" :size="17" />
                Volver al dashboard
            </a>

            <button
                type="button"
                class="button button--ghost"
                onclick="history.back()"
            >
                <x-ui.icon name="arrow-left" :size="17" />
                Regresar
            </button>
        </div>
    </section>
@endsection
