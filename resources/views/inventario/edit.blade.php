@extends('layouts.app')

@section('title', 'Configurar inventario')
@section('page-kicker', 'Inventario')
@section('page-title', 'Configurar límites')

@section('content')
    <a href="{{ route('inventario.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver al inventario
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Parámetros de reposición</p>
            <h1>{{ $inventario->producto_codigo }}</h1>
            <p>
                {{ $inventario->producto_descripcion }}
                · Repisa {{ $inventario->repisa_codigo }}
            </p>
        </div>
    </section>

    <section class="detail-grid detail-grid--inventory">
        <article class="panel detail-card">
            <header class="detail-card__header">
                <span class="detail-card__icon">
                    <x-ui.icon name="inventory" :size="22" />
                </span>
                <div>
                    <p class="eyebrow">Solo lectura</p>
                    <h2>Existencia actual</h2>
                </div>
            </header>

            <div class="mini-metric-grid mini-metric-grid--two">
                <div class="mini-metric">
                    <span>Stock actual</span>
                    <strong>
                        <x-ui.quantity :value="$inventario->stock_actual" />
                        {{ $inventario->unidad_codigo }}
                    </strong>
                </div>

                <div class="mini-metric">
                    <span>Costo promedio</span>
                    <strong>
                        S/ {{ number_format((float) $inventario->costo_promedio_soles, 2, '.', ',') }}
                    </strong>
                </div>
            </div>

            <div class="notice notice--info">
                <x-ui.icon name="info" :size="19" />
                <span>
                    El stock actual y el costo promedio solo se modifican
                    mediante notas de entrada, salida o sus reversas.
                </span>
            </div>
        </article>

        <article class="panel form-panel">
            <form
                method="POST"
                action="{{ route('inventario.update', $inventario->id_inventario) }}"
            >
                @csrf
                @method('PUT')

                <div class="form-grid form-grid--single">
                    <div class="form-field">
                        <label for="stock_minimo">
                            Stock mínimo <span class="required-mark">*</span>
                        </label>
                        <input
                            id="stock_minimo"
                            name="stock_minimo"
                            type="number"
                            min="0"
                            step="0.001"
                            value="{{ old('stock_minimo', $inventario->stock_minimo) }}"
                            required
                            @class(['is-invalid' => $errors->has('stock_minimo')])
                        >
                        <small>
                            Al alcanzar este valor, el inventario requerirá revisión.
                        </small>
                        @error('stock_minimo')
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label for="stock_maximo">Stock máximo</label>
                        <input
                            id="stock_maximo"
                            name="stock_maximo"
                            type="number"
                            min="0"
                            step="0.001"
                            value="{{ old('stock_maximo', $inventario->stock_maximo) }}"
                            @class(['is-invalid' => $errors->has('stock_maximo')])
                        >
                        <small>
                            Déjalo vacío cuando no exista un límite superior.
                        </small>
                        @error('stock_maximo')
                            <small class="field-error">{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('inventario.index') }}" class="button button--ghost">
                        Cancelar
                    </a>

                    <button type="submit" class="button button--primary">
                        <x-ui.icon name="save" :size="18" />
                        Guardar límites
                    </button>
                </div>
            </form>
        </article>
    </section>
@endsection
