@extends('layouts.app')

@section('title', 'Abrir inventario periódico')
@section('page-kicker', 'Almacén')
@section('page-title', 'Abrir inventario periódico')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Nuevo conteo físico</p>
            <h1>Abrir inventario por repisa</h1>
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
