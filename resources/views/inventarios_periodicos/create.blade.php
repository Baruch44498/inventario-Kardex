@extends('layouts.app')

@section('title', 'Abrir inventario periódico')
@section('page-kicker', 'Almacén')
@section('page-title', 'Abrir inventario periódico')

@section('content')
    @php
        $seleccionPorId = $repisasSeleccionadas->keyBy('id');
        $fueraDeResultados = $repisasSeleccionadas->whereNotIn('id', $repisasGrupo->pluck('id'));
        $totalSeleccionadas = $repisasSeleccionadas->count();
        $erroresSeleccion = collect($errors->get('repisa_ids.*'))->flatten()
            ->merge($errors->get('repisa_id'))->merge($errors->get('repisa_ids'))->unique();
    @endphp

    <div class="periodic-opening">
        <x-ui.page-header kicker="Almacén" title="Abrir inventario periódico"
            description="El sistema guardará una foto del stock y del costo promedio al abrir el conteo." />

        <div class="notice notice--info periodic-opening__notice">
            <x-ui.icon name="info" :size="18" />
            <p>Las existencias solo se ajustan después de contar todos los productos y confirmar el cierre.</p>
        </div>

        <form id="periodic-shelf-search-form" method="GET" action="{{ route('inventarios-periodicos.create') }}">
            <noscript>
                @foreach ($repisasSeleccionadas as $repisa)
                    <input type="hidden" name="repisa_ids[]" value="{{ $repisa->id }}">
                @endforeach
            </noscript>
        </form>

        <form method="POST" action="{{ route('inventarios-periodicos.store') }}"
            class="panel form-panel periodic-shelf-picker-form" data-dirty-form data-periodic-shelf-picker
            data-shelf-limit="{{ $limiteRepisas }}" aria-labelledby="periodic-shelf-title">
            @csrf
            <h2 id="periodic-shelf-title">Seleccionar repisas</h2>

            <div class="periodic-shelf-picker__search">
                <div class="form-field">
                    <label for="periodic-shelf-prefix">Código de repisa</label>
                    <div class="input-with-icon">
                        <span class="input-with-icon__symbol" aria-hidden="true"><x-ui.icon name="search" :size="18" /></span>
                        <input id="periodic-shelf-prefix" type="search" form="periodic-shelf-search-form"
                            name="prefijo" value="{{ $prefijo }}" maxlength="40" placeholder="Ej. AA o AA011"
                            autocomplete="off" aria-describedby="periodic-shelf-help">
                    </div>
                    <small id="periodic-shelf-help">Escribe el inicio del código o el código completo.</small>
                    @error('prefijo')<span class="form-error">{{ $message }}</span>@enderror
                </div>
                <button type="button" class="button button--ghost" data-clear-shelf-search hidden>Limpiar búsqueda</button>
                <noscript>
                    <button type="submit" form="periodic-shelf-search-form" class="button button--secondary">Buscar repisas</button>
                </noscript>
            </div>
            <noscript>
                <p class="periodic-shelf-picker__help">Sin JavaScript, realiza la búsqueda antes de marcar las repisas y escribir la observación.</p>
            </noscript>

            <div class="periodic-shelf-picker__selection">
                <div class="periodic-shelf-picker__toolbar">
                    <strong data-shelf-count role="status" aria-live="polite" aria-atomic="true">
                        {{ $totalSeleccionadas === 1 ? '1 repisa seleccionada' : $totalSeleccionadas.' repisas seleccionadas' }}
                    </strong>
                    <button type="button" class="button button--ghost" data-clear-shelf-selection hidden>Quitar todas</button>
                </div>
                <a href="#periodic-shelf-observation" class="button button--ghost periodic-shelf-picker__skip"
                    data-shelf-skip hidden>Ir a la observación y apertura</a>
                <details data-shelf-summary hidden>
                    <summary><span data-shelf-summary-label>Ver selección</span></summary>
                    <ul class="periodic-shelf-picker__selected-list" data-shelf-summary-items></ul>
                </details>
                <p class="form-error" data-shelf-selection-status role="status" hidden></p>
                <div class="form-error" data-shelf-errors @if ($erroresSeleccion->isEmpty()) hidden @endif>
                    @foreach ($erroresSeleccion as $mensaje)
                        <p>{{ $mensaje }}</p>
                    @endforeach
                </div>
            </div>

            <div class="periodic-shelf-picker__toolbar">
                <p data-shelf-search-status role="status" aria-live="polite" aria-atomic="true">
                    @if ($prefijo === '')
                        Busca un código para ver las repisas activas.
                    @elseif ($totalCoincidencias === 0)
                        No hay repisas activas con ese código.
                    @elseif ($totalCoincidencias > $limiteRepisas)
                        {{ $totalCoincidencias }} coincidencias. Se muestran las primeras {{ $limiteRepisas }}.
                        Seleccionar resultados solo incluye las repisas visibles.
                    @else
                        {{ $totalCoincidencias === 1 ? '1 coincidencia' : $totalCoincidencias.' coincidencias' }}
                    @endif
                </p>
                <button type="button" class="button button--ghost" data-select-periodic-shelves
                    aria-controls="periodic-shelf-results" hidden>Seleccionar resultados</button>
                <button type="button" class="button button--ghost" data-retry-shelf-search hidden>Reintentar</button>
            </div>

            <div id="periodic-shelf-results" class="periodic-shelf-picker" data-shelf-results
                role="group" aria-label="Resultados de búsqueda" @if ($repisasGrupo->isEmpty()) hidden @endif>
                @foreach ($repisasGrupo as $repisa)
                    <label class="periodic-shelf-picker__item">
                        <input type="checkbox" name="repisa_ids[]" value="{{ $repisa->id }}" data-shelf-option
                            data-code="{{ $repisa->codigo }}" @checked($seleccionPorId->has($repisa->id))>
                        <span>
                            <strong>{{ $repisa->codigo }}</strong>
                            @if ($repisa->descripcion)<small>{{ $repisa->descripcion }}</small>@endif
                        </span>
                    </label>
                @endforeach
            </div>

            @if ($fueraDeResultados->isNotEmpty())
                <div data-restored-shelves class="periodic-shelf-picker" role="group" aria-label="Repisas recuperadas">
                    @foreach ($fueraDeResultados as $repisa)
                        <label class="periodic-shelf-picker__item">
                            <input type="checkbox" name="repisa_ids[]" value="{{ $repisa->id }}" data-shelf-option
                                data-code="{{ $repisa->codigo }}" @if (! $repisa->estado) data-unavailable @endif checked>
                            <span>
                                <strong>{{ $repisa->codigo }}</strong>
                                @if (! $repisa->estado)<small>No disponible</small>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
            @endif

            <div data-shelf-fields></div>

            <label class="form-field" for="periodic-shelf-observation">
                <span>Observación <small>(opcional)</small></span>
                <textarea id="periodic-shelf-observation" name="observacion" rows="2" maxlength="500"
                    placeholder="Ej. Conteo del sector AB"
                    @error('observacion') class="is-invalid" aria-invalid="true" aria-describedby="periodic-shelf-observation-error" @enderror>{{ old('observacion') }}</textarea>
                @error('observacion')<span class="form-error" id="periodic-shelf-observation-error">{{ $message }}</span>@enderror
            </label>

            <div class="form-actions">
                <a href="{{ route('inventarios-periodicos.index') }}" class="button button--ghost" data-shelf-cancel-fallback>Cancelar</a>
                <button type="button" class="button button--ghost" data-cancel-form
                    data-cancel-url="{{ route('inventarios-periodicos.index') }}" hidden>Cancelar</button>
                <button type="submit" class="button button--primary" data-open-shelf-count>
                    <x-ui.icon name="clipboard" :size="17" />
                    <span data-open-shelf-label>Abrir conteo</span>
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/inventario-periodico-grupo.js') }}" defer></script>
@endpush
