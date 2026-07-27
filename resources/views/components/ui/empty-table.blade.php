@props([
    'icon',
    'title',
    'description',
    'actionUrl' => null,
    'actionLabel' => null,
    'actionIcon' => 'plus',
    'secondaryUrl' => null,
    'secondaryLabel' => null,
])

<div class="empty-table-state">
    <span class="empty-state__icon">
        <x-ui.icon :name="$icon" :size="30" />
    </span>

    <strong>{{ $title }}</strong>
    <span>{{ $description }}</span>

    @if ($actionUrl && $actionLabel)
        <div class="empty-table-state__actions">
            <a href="{{ $actionUrl }}" class="button button--primary button--small">
                <x-ui.icon :name="$actionIcon" :size="16" />
                {{ $actionLabel }}
            </a>

            @if ($secondaryUrl && $secondaryLabel)
                <a href="{{ $secondaryUrl }}" class="button button--ghost button--small">
                    {{ $secondaryLabel }}
                </a>
            @endif
        </div>
    @endif
</div>
