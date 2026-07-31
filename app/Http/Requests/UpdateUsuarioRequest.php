<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => trim((string) $this->input('username')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'estado' => $this->boolean('estado'),
        ]);
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $usuarioId = is_object($usuario) ? $usuario->id : $usuario;

        return [
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')
                    ->where(fn($query) => $query->where('estado', true)),
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($usuarioId),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($usuarioId),
            ],
            'password' => [
                'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'estado' => ['required', 'boolean'],
        ];
    }
}
