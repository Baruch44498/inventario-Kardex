@props([
    'name',
    'searchUrl',
    'selectedId' => null,
    'selectedLabel' => '',
    'searchId' => null,
    'valueId' => null,
    'placeholder' => 'Escribe para buscar',
    'emptyText' => 'No se encontraron coincidencias.',
    'required' => false,
    'disabled' => false,
    'valueAttributes' => [],
    'ariaLabel' => null,
])

@php
    $searchId = $searchId ?: $name.'_busqueda';
    $valueId = $valueId ?: $name;
@endphp

<div
    class="remote-combobox"
    data-remote-combobox
    data-search-url="{{ $searchUrl }}"
    data-empty-text="{{ $emptyText }}"
    data-required="{{ $required ? 'true' : 'false' }}"
>
    <div class="remote-combobox__control">
        <input
            id="{{ $searchId }}"
            type="search"
            value="{{ $selectedLabel }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            aria-autocomplete="list"
            aria-expanded="false"
            @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
            data-remote-combobox-search
            @required($required)
            @disabled($disabled)
        >
        <button
            type="button"
            class="remote-combobox__clear"
            aria-label="Limpiar selección"
            title="Limpiar selección"
            data-remote-combobox-clear
            @if (! $selectedId) hidden @endif
            @disabled($disabled)
        >&times;</button>
    </div>

    <input
        id="{{ $valueId }}"
        type="hidden"
        name="{{ $name }}"
        value="{{ $selectedId }}"
        data-remote-combobox-value
        {{ new \Illuminate\View\ComponentAttributeBag($valueAttributes) }}
    >

    <div
        class="remote-combobox__results"
        role="listbox"
        data-remote-combobox-results
        hidden
    ></div>
</div>
