@props([
    'kicker' => null,
    'title',
    'description' => null,
    'status' => null,
    'statusTone' => 'neutral',
    'backHref' => null,
    'backLabel' => 'Regresar',
])

<section {{ $attributes->class(['ui-page-header']) }}>
    <div class="ui-page-header__content">
        @if ($backHref)
            <a href="{{ $backHref }}" class="ui-page-header__back">
                <x-ui.icon name="arrow-left" :size="16" />
                {{ $backLabel }}
            </a>
        @endif

        @if ($kicker)
            <p class="eyebrow">{{ $kicker }}</p>
        @endif

        <div class="ui-page-header__title-row">
            <h1>{{ $title }}</h1>

            @if ($status)
                <x-ui.status-badge :tone="$statusTone">
                    {{ $status }}
                </x-ui.status-badge>
            @endif
        </div>

        @if ($description)
            <p class="ui-page-header__description">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="ui-page-header__actions">
            {{ $actions }}
        </div>
    @endisset
</section>

