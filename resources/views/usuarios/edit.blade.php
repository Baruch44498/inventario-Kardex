@extends('layouts.app')

@section('title', 'Editar usuario')
@section('page-kicker', 'Usuarios y permisos')
@section('page-title', 'Editar usuario')

@section('content')
    <a href="{{ route('usuarios.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a usuarios
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Cuenta registrada</p>
            <h1>{{ $usuario->username }}</h1>
            <p>
                @if ($usuario->empleado)
                    Vinculado con {{ $usuario->empleado->nombre_completo }} · DNI {{ $usuario->empleado->dni }}.
                @else
                    Cuenta existente pendiente de vincular con un empleado.
                @endif
                Deja la contraseña vacía para conservar la actual.
            </p>
        </div>

        <div class="table-actions">
            @if ($usuario->esAdministradorPrincipal())
                <span class="badge badge--info">ADMINISTRADOR PRINCIPAL</span>
            @endif
            <span class="badge badge--{{ $usuario->estado ? 'success' : 'danger' }}">
                {{ $usuario->estado ? 'ACTIVO' : 'INACTIVO' }}
            </span>
        </div>
    </section>

    <section class="panel form-panel user-form-panel">
        <form
            method="POST"
            action="{{ route('usuarios.update', $usuario->id) }}"
            data-dirty-form
            data-loading-form
        >
            @csrf
            @method('PUT')
            @include('usuarios._form')
        </form>
    </section>
@endsection
