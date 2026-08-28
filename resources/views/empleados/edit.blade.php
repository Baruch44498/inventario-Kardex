@extends('layouts.app')

@section('title', 'Editar empleado')
@section('page-kicker', 'Catálogo de empleados')
@section('page-title', 'Editar empleado')

@section('content')
    <a href="{{ route('empleados.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a empleados
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Empleado registrado</p>
            <h1>{{ $empleado->nombre_completo }}</h1>
            <p>
                DNI {{ $empleado->dni }}
                @if ($empleado->usuario)
                    · Usuario {{ $empleado->usuario->username }}
                @endif
                · Los cambios quedarán asociados al administrador actual.
            </p>
        </div>

        <div class="table-actions">
            @if ($empleadoProtegido)
                <span class="badge badge--info">EMPLEADO PROTEGIDO</span>
            @endif
            <span class="badge badge--{{ $empleado->estado ? 'success' : 'neutral' }}">
                {{ $empleado->estado ? 'ACTIVO' : 'INACTIVO' }}
            </span>
        </div>
    </section>

    <section class="panel form-panel user-form-panel">
        <form
            method="POST"
            action="{{ route('empleados.update', $empleado) }}"
            data-dirty-form
            data-loading-form
        >
            @csrf
            @method('PUT')
            @include('empleados._form')
        </form>
    </section>
@endsection
