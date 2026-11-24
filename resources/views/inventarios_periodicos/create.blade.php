@extends('layouts.app')

@section('title', 'Abrir inventario periódico')
@section('page-kicker', 'Almacén')
@section('page-title', 'Abrir inventario periódico')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Nuevo conteo físico</p>
            <h1>Abrir inventario periódico</h1>
            <p>El sistema guardará una foto del stock y del costo promedio antes de comenzar el conteo.</p>
        </div>
        <a href="{{ route('inventarios-periodicos.index') }}" class="button button--ghost">Volver</a>
    </section>

    <div class="notice notice--info notice--block">
        <x-ui.icon name="info" :size="19" />
        <div>
            <strong>Abrir no modifica existencias</strong>
            <p>Los ajustes se generan únicamente después de contar todos los productos y confirmar el cierre.</p>
        </div>
    </div>

    <section class="panel form-panel">
        <div class="panel-heading">
            <p class="eyebrow">Recorrido por zona o tramo</p>
            <h2>Contar varias repisas en una sesión</h2>
            <p>Busca por el inicio del código (por ejemplo AA o AB) y selecciona los casilleros que vas a recorrer.</p>
        </div>
        <form method="GET" action="{{ route('inventarios-periodicos.create') }}" class="filter-grid">
            <label class="form-field">
                <span>Inicio del código</span>
                <input type="text" name="prefijo" value="{{ $prefijo }}" maxlength="40" placeholder="Ej. AB" required>
            </label>
            <div class="filter-actions"><button type="submit" class="button button--ghost">Buscar repisas</button></div>
        </form>

        @if ($prefijo !== '')
            @if ($totalCoincidencias > 200)
                <div class="notice notice--warning notice--block">Hay {{ $totalCoincidencias }} coincidencias; se muestran las primeras 200. Usa un prefijo más específico para incluir las demás.</div>
            @endif
            @if ($repisasGrupo->isEmpty())
                <div class="notice notice--info notice--block">No hay repisas activas con ese inicio de código.</div>
            @else
                <form method="POST" action="{{ route('inventarios-periodicos.store') }}" data-dirty-form>
                    @csrf
                    <div class="periodic-shelf-picker__toolbar">
                        <p>{{ $repisasGrupo->count() }} repisas encontradas. Marca solo las que vas a contar.</p>
                        <button type="button" class="button button--ghost" data-select-periodic-shelves>Seleccionar todas</button>
                    </div>
                    <div class="periodic-shelf-picker">
                        @foreach ($repisasGrupo as $repisa)
                            <label class="periodic-shelf-picker__item">
                                <input type="checkbox" name="repisa_ids[]" value="{{ $repisa->id }}" @checked(in_array($repisa->id, old('repisa_ids', [])))>
                                <span><strong>{{ $repisa->codigo }}</strong><small>{{ $repisa->descripcion ?: 'Sin descripción' }}</small></span>
                            </label>
                        @endforeach
                    </div>
                    @error('repisa_ids')<span class="form-error">{{ $message }}</span>@enderror
                    @error('repisa_ids.*')<span class="form-error">{{ $message }}</span>@enderror
                    <label class="form-field">
                        <span>Observación</span>
                        <textarea name="observacion" rows="2" maxlength="500" placeholder="Ej. Conteo del sector AB">{{ old('observacion') }}</textarea>
                    </label>
                    <div class="form-actions"><button type="submit" class="button button--primary">Abrir conteo de las repisas marcadas</button></div>
                </form>
            @endif
        @endif
    </section>

    <section class="panel form-panel">
        <div class="panel-heading"><h2>Contar una sola repisa</h2></div>
        <form method="POST" action="{{ route('inventarios-periodicos.store') }}" class="stack-form" data-dirty-form>
            @csrf

            <div class="form-grid form-grid--two">
                <div class="form-field">
                    <label for="repisa_busqueda">Repisa a contar <span class="required-mark">*</span></label>
                    <x-ui.remote-combobox
                        name="repisa_id"
                        search-id="repisa_busqueda"
                        value-id="repisa_id"
                        :search-url="route('catalogos.repisas.buscar', ['todos' => 1])"
                        :selected-id="$repisaSeleccionada?->id"
                        :selected-label="$repisaSeleccionada
                            ? $repisaSeleccionada->codigo.($repisaSeleccionada->descripcion ? ' — '.$repisaSeleccionada->descripcion : '')
                            : ''"
                        placeholder="Código o descripción"
                        empty-text="No se encontró la repisa."
                        required
                    />
                    @error('repisa_id')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <label class="form-field">
                    <span>Observación</span>
                    <textarea name="observacion" rows="3" maxlength="500" placeholder="Ej. Conteo mensual de almacén">{{ old('observacion') }}</textarea>
                    @error('observacion')<span class="form-error">{{ $message }}</span>@enderror
                </label>
            </div>

            <div class="form-actions">
                <a href="{{ route('inventarios-periodicos.index') }}" class="button button--ghost" data-discard-link>Cancelar</a>
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="clipboard" :size="17" /> Abrir conteo
                </button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
<script src="{{ asset('js/inventario-periodico-grupo.js') }}" defer></script>
@endpush
