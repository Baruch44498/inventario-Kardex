<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUsuarioRequest extends FormRequest
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
        return [
            'empleado_id' => [
                'required',
                'integer',
                Rule::exists('empleados', 'id')
                    ->where(fn($query) => $query->where('estado', true)),
                Rule::unique('users', 'empleado_id'),
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
                'unique:users,username',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'estado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'empleado_id.required' => 'Selecciona el empleado que utilizará esta cuenta.',
            'empleado_id.exists' => 'El empleado seleccionado no existe o está inactivo.',
            'empleado_id.unique' => 'Ese empleado ya tiene una cuenta de usuario vinculada.',
            'username.regex' => 'El usuario solo puede contener letras, números, punto, guion y guion bajo.',
            'username.unique' => 'Ese nombre de usuario ya está registrado.',
            'email.unique' => 'Ese correo ya está registrado.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}
