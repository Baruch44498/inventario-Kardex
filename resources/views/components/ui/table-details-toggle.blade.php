@props([
    'target',
    'label' => 'Ver más detalles',
])

<button
    type="button"
    class="icon-button table-details-toggle"
    aria-expanded="false"
    aria-controls="{{ $target }}"
    aria-label="{{ $label }}"
    title="{{ $label }}"
    data-table-details-toggle
>
    <x-ui.icon name="chevron-down" :size="17" />
</button>
