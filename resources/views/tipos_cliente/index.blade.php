@extends('layouts.app')

@section('title', 'Tipos de cliente')
@section('page-kicker', 'Clientes')
@section('page-title', 'Tipos de cliente')

@section('content')
    <a href="{{ route('clientes.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a clientes
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Segmentación comercial</p>
            <h1>Tipos de cliente</h1>
            <p>
                Define el margen sugerido para cada segmento. El porcentaje se
                utilizará en el módulo de proformas y podrá modificarse por negociación.
            </p>
        </div>
    </section>

    <div class="client-type-settings-grid">
        @foreach ($tipos as $tipo)
            <article class="panel client-type-settings-card">
                <header class="client-type-settings-card__header">
                    <span class="client-type-settings-card__icon"><x-ui.icon name="tag" :size="22" /></span>
                    <div>
                        <code>{{ $tipo->codigo }}</code>
                        <strong>{{ $tipo->nombre }}</strong>
                    </div>
                    <span class="badge badge--{{ $tipo->estado ? 'success' : 'danger' }}">
                        {{ $tipo->estado ? 'ACTIVO' : 'INACTIVO' }}
                    </span>
                </header>

                <div class="client-type-settings-card__stats">
                    <div><span>Clientes</span><strong>{{ $tipo->clientes_count }}</strong></div>
                    <div><span>Activos</span><strong>{{ $tipo->clientes_activos_count }}</strong></div>
                </div>

                <form method="POST" action="{{ route('tipos-cliente.update', $tipo->id) }}" class="client-type-settings-form">
                    @csrf
                    @method('PUT')

                    <label class="form-field">
                        <span>Nombre</span>
                        <input name="nombre" type="text" value="{{ old('nombre', $tipo->nombre) }}" maxlength="100" required>
                    </label>

                    <label class="form-field">
                        <span>Margen sugerido (%)</span>
                        <div class="input-with-icon">
                            <span class="input-with-icon__symbol"><x-ui.icon name="percent" :size="17" /></span>
                            <input name="porcentaje_ganancia" type="number" min="0" max="999.99" step="0.01" value="{{ old('porcentaje_ganancia', $tipo->porcentaje_ganancia) }}" required>
                        </div>
                        <small>Se inicia en 0 % para no asumir márgenes no aprobados.</small>
                    </label>

                    <label class="form-field">
                        <span>Descripción</span>
                        <textarea name="descripcion" rows="3" maxlength="250">{{ old('descripcion', $tipo->descripcion) }}</textarea>
                    </label>

                    <label class="switch-field">
                        <input type="hidden" name="estado" value="0">
                        <input type="checkbox" name="estado" value="1" @checked($tipo->estado)>
                        <span class="switch-control"></span>
                        <span>Tipo activo</span>
                    </label>

                    <button type="submit" class="button button--primary button--block">
                        <x-ui.icon name="save" :size="17" />
                        Guardar configuración
                    </button>
                </form>
            </article>
        @endforeach
    </div>
@endsection
