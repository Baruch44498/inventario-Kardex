@props([
    'tone' => 'neutral',
    'icon' => null,
])

<span {{ $attributes->class(['badge', 'badge--'.$tone, 'ui-status-badge']) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" :size="14" />
    @else
        <span class="ui-status-badge__dot" aria-hidden="true"></span>
    @endif

    <span>{{ $slot }}</span>
</span>

