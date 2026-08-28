<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarEmpleadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nombre = preg_replace(
            '/\s+/u',
            ' ',
            trim((string) $this->input('nombre_completo'))
        );

        $this->merge([
            'nombre_completo' => $nombre,
            'dni' => trim((string) $this->input('dni')),
            'estado' => $this->boolean('estado'),
        ]);
    }

    public function rules(): array
    {
        $empleado = $this->route('empleado');
        $empleadoId = is_object($empleado) ? $empleado->id : $empleado;

        return [
            'nombre_completo' => [
                'required',
                'string',
                'min:3',
                'max:150',
            ],
            'dni' => [
                'required',
                'digits:8',
                Rule::unique('empleados', 'dni')->ignore($empleadoId),
            ],
            'estado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_completo.required' => 'Ingresa el nombre completo del empleado.',
            'nombre_completo.min' => 'El nombre completo debe tener al menos 3 caracteres.',
            'dni.required' => 'Ingresa el DNI del empleado.',
            'dni.digits' => 'El DNI debe contener exactamente 8 números.',
            'dni.unique' => 'Ese DNI ya está registrado en otro empleado.',
        ];
    }
}
