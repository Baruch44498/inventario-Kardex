(() => {
    'use strict';

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
})();
