@props([
    'href',
    'icon' => 'clipboard',
    'title',
    'description' => null,
    'status' => 'implemented',
    'statusLabel' => null,
])

@php
    $statusConfig = [
        'implemented' => ['success', 'Disponible'],
        'planning' => ['neutral', 'En planificación'],
        'development' => ['warning', 'En desarrollo'],
        'soon' => ['info', 'Próximamente'],
    ];
    [$statusTone, $defaultStatusLabel] = $statusConfig[$status]
        ?? $statusConfig['planning'];
@endphp

<a href="{{ $href }}" {{ $attributes->class(['ui-module-card']) }}>
    <span class="ui-module-card__icon" aria-hidden="true">
        <x-ui.icon :name="$icon" :size="22" />
    </span>

    <span class="ui-module-card__content">
        <span class="ui-module-card__heading">
            <strong>{{ $title }}</strong>
            <x-ui.status-badge :tone="$statusTone">
                {{ $statusLabel ?: $defaultStatusLabel }}
            </x-ui.status-badge>
        </span>

        @if ($description)
            <small>{{ $description }}</small>
        @endif
    </span>

    <span class="ui-module-card__arrow" aria-hidden="true">
        <x-ui.icon name="arrow-right" :size="18" />
    </span>
</a>
