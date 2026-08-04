@props([
    'name',
    'title' => 'Confirmar acción',
    'message' => 'Revisa la información antes de continuar.',
    'icon' => 'warning',
    'tone' => 'warning',
    'confirmLabel' => 'Confirmar',
    'cancelLabel' => 'Cancelar',
])

@php
    $titleId = 'ui-modal-'.$name.'-title';
    $descriptionId = 'ui-modal-'.$name.'-description';
@endphp

<div
    class="modal-backdrop ui-modal-backdrop"
    data-ui-modal="{{ $name }}"
    data-default-tone="{{ $tone }}"
    hidden
>
    <section
        class="confirmation-modal ui-confirmation-modal ui-confirmation-modal--{{ $tone }}"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $titleId }}"
        aria-describedby="{{ $descriptionId }}"
        tabindex="-1"
    >
        <span class="confirmation-modal__icon" data-ui-modal-icon aria-hidden="true">
            <x-ui.icon :name="$icon" :size="25" />
        </span>

        <div class="confirmation-modal__content">
            <h2 id="{{ $titleId }}" data-ui-modal-title>{{ $title }}</h2>
            <p id="{{ $descriptionId }}" data-ui-modal-message>{{ $message }}</p>
        </div>

        <div class="confirmation-modal__actions">
            <button
                type="button"
                class="button button--ghost"
                data-ui-modal-cancel
            >
                {{ $cancelLabel }}
            </button>

            <button
                type="button"
                class="button {{ $tone === 'danger' ? 'button--danger' : 'button--primary' }}"
                data-ui-modal-confirm
            >
                <span data-ui-modal-confirm-label>{{ $confirmLabel }}</span>
            </button>
        </div>
    </section>
</div>

