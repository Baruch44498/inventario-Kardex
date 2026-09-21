@props([
    'title',
    'description',
    'tone' => 'info',
    'href' => null,
    'label' => null,
    'iconClass' => 'ui-next-action__icon',
    'linkClass' => 'ui-next-action__link',
])

<aside {{ $attributes->class(['ui-next-action']) }} role="status" data-tone="{{ $tone }}">
    <span class="{{ $iconClass }}">
        <x-ui.icon name="arrow-right" :size="16" />
    </span>
    <div>
        <strong>Siguiente acción: {{ $title }}</strong>
        <span>{{ $description }}</span>
    </div>
    @if ($href && $label)
        <a href="{{ $href }}" class="{{ $linkClass }}">
            {{ $label }} <x-ui.icon name="arrow-right" :size="14" />
        </a>
    @endif
</aside>
