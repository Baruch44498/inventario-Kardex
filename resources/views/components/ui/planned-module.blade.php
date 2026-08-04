@props([
    'name',
    'area' => 'Área correspondiente',
    'icon' => 'clipboard',
    'status' => 'planning',
    'description' => null,
    'profile' => null,
    'message' => null,
    'dashboardHref',
    'backHref' => null,
])

@php
    $statusLabels = [
        'planning' => 'En planificación',
        'development' => 'En desarrollo',
        'soon' => 'Próximamente',
    ];
    $statusTones = [
        'planning' => 'neutral',
        'development' => 'warning',
        'soon' => 'info',
    ];
@endphp

<section {{ $attributes->class(['placeholder-panel', 'module-placeholder-panel', 'ui-planned-module']) }}>
    <span class="module-placeholder-panel__icon" aria-hidden="true">
        <x-ui.icon :name="$icon" :size="34" />
    </span>

    <x-ui.status-badge :tone="$statusTones[$status] ?? 'neutral'">
        {{ $statusLabels[$status] ?? 'En planificación' }}
    </x-ui.status-badge>

    <p class="eyebrow">{{ $area }}</p>
    <h1>{{ $name }}</h1>

    <p>
        {{ $description ?: 'Este módulo forma parte del alcance aprobado del sistema.' }}
    </p>

    <p class="ui-planned-module__message">
        {{ $message ?: 'La navegación y los permisos están preparados, pero todavía no existen datos ni operaciones habilitadas.' }}
    </p>

    <div class="module-placeholder-panel__meta">
        <x-ui.status-badge tone="info">
            Perfil: {{ $profile ?: (auth()->user()->role?->nombre ?? 'Sin rol') }}
        </x-ui.status-badge>

        <x-ui.status-badge tone="neutral">
            Sin operaciones habilitadas
        </x-ui.status-badge>
    </div>

    <div class="module-placeholder-panel__actions">
        <a href="{{ $dashboardHref }}" class="button button--primary">
            <x-ui.icon name="dashboard" :size="17" />
            Volver al dashboard
        </a>

        @if ($backHref && $backHref !== $dashboardHref)
            <a href="{{ $backHref }}" class="button button--ghost">
                <x-ui.icon name="arrow-left" :size="17" />
                Regresar
            </a>
        @endif
    </div>
</section>

