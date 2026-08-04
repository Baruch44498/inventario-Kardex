@extends('layouts.app')

@section('title', 'Acceso restringido')
@section('page-kicker', 'Seguridad')
@section('page-title', 'Acceso restringido')

@section('content')
    <section class="placeholder-panel permission-denied-panel">
        <span class="permission-denied-panel__icon">
            <x-ui.icon name="lock" :size="32" />
        </span>
        <p class="eyebrow">Permiso insuficiente</p>
        <h1>Esta acción no pertenece a tu perfil</h1>
        <p>
            {{ $exception->getMessage() ?: 'Solicita al administrador que revise el rol asignado.' }}
        </p>
        <div class="permission-denied-panel__actions">
            <a href="{{ route('dashboard') }}" class="button button--primary">
                Volver al dashboard
            </a>
            <button type="button" class="button button--ghost" data-history-back>
                Regresar
            </button>
        </div>
    </section>
@endsection
