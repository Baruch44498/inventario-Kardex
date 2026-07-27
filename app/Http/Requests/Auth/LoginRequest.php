<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $authenticated = Auth::attempt(
            [
                'email' => $this->string('email')->lower()->toString(),
                'password' => $this->string('password')->toString(),
                'estado' => true,
            ],
            $this->boolean('remember')
        );

        if (!$authenticated) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales son incorrectas o el usuario está inactivo.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $this->session()->regenerate();
    }

    private function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $this->session()->flash(
            'login_lockout_until',
            now()->addSeconds($seconds)->timestamp
        );

        throw ValidationException::withMessages([
            'email' => 'Demasiados intentos. Espera a que termine el contador para volver a intentarlo.',
        ]);
    }
    private function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('email')->toString())
            . '|' . $this->ip()
        );
    }
}
