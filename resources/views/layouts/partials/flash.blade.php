@php
    $toasts = collect([
        ['type' => 'success', 'message' => session('success')],
        ['type' => 'warning', 'message' => session('warning')],
        ['type' => 'danger', 'message' => session('error')],
        ['type' => 'info', 'message' => session('info')],
    ])->filter(fn (array $toast) => filled($toast['message']));

    $toastIcons = [
        'success' => 'check-circle',
        'warning' => 'warning',
        'danger' => 'error',
        'info' => 'info',
    ];
@endphp

@if ($toasts->isNotEmpty())
    <div
        class="toast-stack"
        aria-live="polite"
        aria-atomic="true"
    >
        @foreach ($toasts as $toast)
            <div
                class="toast toast--{{ $toast['type'] }}"
                data-toast
                role="{{ $toast['type'] === 'danger' ? 'alert' : 'status' }}"
            >
                <span class="toast__icon">
                    <x-ui.icon
                        :name="$toastIcons[$toast['type']]"
                        :size="20"
                    />
                </span>

                <div class="toast__content">
                    <strong>
                        @switch($toast['type'])
                            @case('success')
                                Operación completada
                                @break
                            @case('warning')
                                Atención
                                @break
                            @case('danger')
                                Ocurrió un problema
                                @break
                            @default
                                Información
                        @endswitch
                    </strong>

                    <span>{{ $toast['message'] }}</span>
                </div>

                <button
                    type="button"
                    class="toast__close"
                    data-toast-close
                    aria-label="Cerrar notificación"
                >
                    <x-ui.icon name="close" :size="17" />
                </button>
            </div>
        @endforeach
    </div>
@endif

@if ($errors->any())
    <div class="alert alert--danger" role="alert">
        <x-ui.icon name="error" :size="19" />

        <div>
            <strong>Revisa la información ingresada.</strong>

            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
