@php
    $loginLockoutUntil = (int) session('login_lockout_until', 0);
    $loginLocked = $loginLockoutUntil > now()->timestamp;
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Iniciar sesión | Hidroil</title>

    <link rel="stylesheet" href="{{ asset('css/hidroil-admin.css') }}">
</head>
<body class="login-page">
    <main class="login-shell">
        <section
            class="login-brand-panel"
            aria-label="Presentación del sistema"
        >
            <div class="brand-logo-card brand-logo-card--login">
                <img
                    src="{{ asset('images/logo-hidroil.png') }}"
                    alt="Hidroil S.A.C."
                    class="brand-logo brand-logo--login"
                >
            </div>

            <div class="login-brand-copy">
                <p class="eyebrow">Sistema administrativo</p>

                <h1>Gestión de almacén, compras y operaciones</h1>

                <p>
                    Control centralizado de inventario, movimientos,
                    requerimientos de compra, órdenes y alertas de stock.
                </p>

                <ul
                    class="login-benefit-list"
                    aria-label="Funciones principales del sistema"
                >
                    <li class="login-benefit">
                        <span class="login-benefit__icon">
                            <x-ui.icon name="inventory" :size="20" />
                        </span>

                        <span>
                            <strong>Inventario centralizado</strong>
                            <small>Stock, repisas y ubicaciones.</small>
                        </span>
                    </li>

                    <li class="login-benefit">
                        <span class="login-benefit__icon">
                            <x-ui.icon name="purchase-order" :size="20" />
                        </span>

                        <span>
                            <strong>Control de compras</strong>
                            <small>Solicitudes, cotizaciones y órdenes.</small>
                        </span>
                    </li>

                    <li class="login-benefit">
                        <span class="login-benefit__icon">
                            <x-ui.icon name="movements" :size="20" />
                        </span>

                        <span>
                            <strong>Seguimiento de operaciones</strong>
                            <small>Entradas, salidas y movimientos.</small>
                        </span>
                    </li>
                </ul>
            </div>

            <div class="login-brand-footer">
                Acceso exclusivo para personal autorizado.
            </div>
        </section>

        <section class="login-form-panel">
            <div class="login-card">
                <div class="login-card__mobile-logo">
                    <img
                        src="{{ asset('images/logo-hidroil.png') }}"
                        alt="Hidroil S.A.C."
                    >
                </div>

                <div class="login-card__header">
                    <p class="eyebrow">Bienvenido</p>
                    <h2>Iniciar sesión</h2>

                    <p>
                        Ingresa tus credenciales para acceder al sistema.
                    </p>
                </div>

                @if (session('success'))
                    <div class="alert alert--success">
                        <x-ui.icon name="check-circle" :size="18" />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div
                        class="alert alert--danger"
                        role="alert"
                        data-login-errors
                    >
                        <x-ui.icon name="error" :size="18" />

                        <div>
                            <strong>No se pudo iniciar sesión.</strong>

                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @if ($loginLocked)
                    <div
                        class="login-lockout"
                        data-login-lockout
                        data-lockout-until="{{ $loginLockoutUntil }}"
                        role="status"
                        aria-live="polite"
                    >
                        <x-ui.icon name="lock" :size="19" />

                        <div>
                            <strong>Acceso bloqueado temporalmente</strong>

                            <span>
                                Podrás volver a intentarlo en
                                <strong data-login-countdown>--:--</strong>
                            </span>
                        </div>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('login.store') }}"
                    class="form-stack"
                    data-login-form
                >
                    @csrf

                    <div class="form-field">
                        <label for="email">Correo electrónico</label>

                        <div class="input-with-icon">
                            <span class="input-with-icon__symbol">
                                <x-ui.icon name="mail" :size="19" />
                            </span>

                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                @class([
                                    'is-invalid' => $errors->has('email'),
                                ])
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                autocomplete="username"
                                required
                                placeholder="usuario@hidroil.test"
                                data-login-lockable
                                @if (!$loginLocked) autofocus @endif
                                @disabled($loginLocked)
                            >
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="password">Contraseña</label>

                        <div class="password-field input-with-icon">
                            <span class="input-with-icon__symbol">
                                <x-ui.icon name="lock" :size="19" />
                            </span>

                            <input
                                id="password"
                                name="password"
                                type="password"
                                @class([
                                    'is-invalid' => $errors->has('password'),
                                ])
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                autocomplete="current-password"
                                required
                                placeholder="Ingresa tu contraseña"
                                data-login-lockable
                                @disabled($loginLocked)
                            >

                            <button
                                type="button"
                                class="password-toggle password-toggle--icon"
                                data-password-toggle
                                data-login-lockable
                                aria-label="Mostrar contraseña"
                                @disabled($loginLocked)
                            >
                                <span data-eye-open>
                                    <x-ui.icon name="eye" :size="20" />
                                </span>

                                <span data-eye-closed hidden>
                                    <x-ui.icon name="eye-off" :size="20" />
                                </span>
                            </button>
                        </div>
                    </div>

                    <label class="check-field">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            data-login-lockable
                            @checked(old('remember'))
                            @disabled($loginLocked)
                        >

                        <span>Mantener sesión iniciada</span>
                    </label>

                    <button
                        type="submit"
                        class="button button--primary button--block"
                        data-login-submit
                        data-login-lockable
                        @disabled($loginLocked)
                    >
                        <span
                            class="button-spinner"
                            data-login-spinner
                            hidden
                            aria-hidden="true"
                        ></span>

                        <span data-login-submit-text>
                            {{ $loginLocked
                                ? 'Acceso bloqueado'
                                : 'Ingresar al sistema' }}
                        </span>
                    </button>
                </form>

                <p class="login-help">
                    Ante problemas de acceso, contacta al administrador.
                </p>
            </div>
        </section>
    </main>

    <script>
        const passwordToggle = document.querySelector(
            '[data-password-toggle]'
        );

        const eyeOpen = document.querySelector('[data-eye-open]');
        const eyeClosed = document.querySelector('[data-eye-closed]');

        passwordToggle?.addEventListener('click', function () {
            const input = document.getElementById('password');
            const visible = input.type === 'text';

            input.type = visible ? 'password' : 'text';
            this.setAttribute(
                'aria-label',
                visible ? 'Mostrar contraseña' : 'Ocultar contraseña'
            );

            if (eyeOpen && eyeClosed) {
                eyeOpen.hidden = !visible;
                eyeClosed.hidden = visible;
            }
        });

        const loginForm = document.querySelector('[data-login-form]');
        const loginSubmit = document.querySelector('[data-login-submit]');
        const loginSubmitText = document.querySelector(
            '[data-login-submit-text]'
        );
        const loginSpinner = document.querySelector('[data-login-spinner]');

        loginForm?.addEventListener('submit', function () {
            this.classList.add('form-stack--loading');

            if (loginSubmit) {
                loginSubmit.disabled = true;
            }

            if (loginSubmitText) {
                loginSubmitText.textContent = 'Ingresando...';
            }

            if (loginSpinner) {
                loginSpinner.hidden = false;
            }
        });

        const lockoutPanel = document.querySelector(
            '[data-login-lockout]'
        );

        if (lockoutPanel) {
            const lockoutUntil =
                Number(lockoutPanel.dataset.lockoutUntil) * 1000;

            const countdown = document.querySelector(
                '[data-login-countdown]'
            );

            const lockableElements = document.querySelectorAll(
                '[data-login-lockable]'
            );

            const passwordInput = document.getElementById('password');
            const emailInput = document.getElementById('email');

            const formatTime = (totalSeconds) => {
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;

                return `${String(minutes).padStart(2, '0')}:`
                    + `${String(seconds).padStart(2, '0')}`;
            };

            const unlockForm = () => {
                lockableElements.forEach((element) => {
                    element.disabled = false;
                });

                document.querySelector(
                    '[data-login-errors]'
                )?.remove();

                if (loginSubmitText) {
                    loginSubmitText.textContent = 'Ingresar al sistema';
                }

                lockoutPanel.innerHTML = `
                    <svg
                        class="ui-icon"
                        width="19"
                        height="19"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.9"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="m8 12 2.7 2.7L16 9.5"></path>
                    </svg>
                    <div>
                        <strong>Ya puedes volver a intentarlo</strong>
                        <span>El bloqueo temporal ha finalizado.</span>
                    </div>
                `;

                lockoutPanel.classList.add(
                    'login-lockout--finished'
                );

                if (passwordInput) {
                    passwordInput.value = '';
                    passwordInput.focus();
                } else {
                    emailInput?.focus();
                }
            };

            let timer = null;

            const updateCountdown = () => {
                const remainingSeconds = Math.max(
                    0,
                    Math.ceil(
                        (lockoutUntil - Date.now()) / 1000
                    )
                );

                if (countdown) {
                    countdown.textContent =
                        formatTime(remainingSeconds);
                }

                if (remainingSeconds <= 0) {
                    if (timer) {
                        clearInterval(timer);
                    }

                    unlockForm();
                }
            };

            updateCountdown();

            if (lockoutUntil > Date.now()) {
                timer = setInterval(updateCountdown, 1000);
            }
        }
    </script>
</body>
</html>
