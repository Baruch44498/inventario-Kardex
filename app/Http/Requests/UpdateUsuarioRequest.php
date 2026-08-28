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
            'empleado_id' => [
                'required',
                'integer',
                Rule::exists('empleados', 'id')
                    ->where(fn($query) => $query->where('estado', true)),
                Rule::unique('users', 'empleado_id')->ignore($usuarioId),
            ],
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

    public function messages(): array
    {
        return [
            'empleado_id.required' => 'Selecciona el empleado vinculado con esta cuenta.',
            'empleado_id.exists' => 'El empleado seleccionado no existe o está inactivo.',
            'empleado_id.unique' => 'Ese empleado ya tiene una cuenta de usuario vinculada.',
            'username.regex' => 'El usuario solo puede contener letras, números, punto, guion y guion bajo.',
            'username.unique' => 'Ese nombre de usuario ya está registrado.',
            'email.unique' => 'Ese correo ya está registrado.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}
