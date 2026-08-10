@props([
    'variant' => 'info',
    'icon' => 'info',
    'label' => 'Ver información',
    'title' => null,
])

@php
    $variant = in_array($variant, ['info', 'success'], true) ? $variant : 'info';
@endphp

<details {{ $attributes->class(['ui-collapsible-notice', 'ui-collapsible-notice--'.$variant]) }} data-collapsible-notice>
    <summary class="ui-collapsible-notice__trigger" aria-label="{{ $label }}" title="{{ $label }}">
        <x-ui.icon :name="$icon" :size="18" />
        <span class="sr-only">{{ $label }}</span>
    </summary>

    <div class="ui-collapsible-notice__panel" role="note">
        @if ($title)
            <strong class="ui-collapsible-notice__title">{{ $title }}</strong>
        @endif

        <div class="ui-collapsible-notice__content">
            {{ $slot }}
        </div>
    </div>
</details>
