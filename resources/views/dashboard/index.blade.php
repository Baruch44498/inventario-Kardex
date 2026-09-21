@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-kicker', 'Panel administrativo')
@section('page-title', 'Dashboard')

@section('content')
    <section class="welcome-panel role-welcome-panel">
        <div>
            <p class="eyebrow">{{ $perfil['perfil'] }}</p>
            <h1>Bienvenido, {{ auth()->user()->nombreVisible() }}</h1>
            <p>{{ $perfil['descripcion'] }}</p>
        </div>

        <div class="role-welcome-panel__meta">
            <span class="role-profile-chip">
                {{ $perfil['nombre'] }}
            </span>
            <div class="welcome-panel__date">
                <span>Fecha</span>
                <strong>{{ now()->translatedFormat('d M Y') }}</strong>
            </div>
        </div>
    </section>

    @if ($modo === 'administrador')
        @include('dashboard.partials._modo_administrador')
    @elseif ($modo === 'almacen')
        @include('dashboard.partials._modo_almacen')
    @elseif ($modo === 'ordenes')
        @include('dashboard.partials._modo_ordenes')
    @elseif ($modo === 'contabilidad')
        @include('dashboard.partials._modo_contabilidad')
    @else
        @include('dashboard.partials._modo_sin_configuracion')
    @endif
@endsection
