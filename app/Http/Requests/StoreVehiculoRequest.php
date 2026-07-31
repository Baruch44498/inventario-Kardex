<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $placa = mb_strtoupper(
            preg_replace(
                '/\s+/',
                '',
                trim((string) $this->input('placa'))
            )
        );

        $this->merge([
            'placa' => $placa,
            'marca' => $this->nullableTexto('marca'),
            'modelo' => $this->nullableTexto('modelo'),
            'color' => $this->nullableTexto('color'),
            'descripcion' => $this->nullableTexto('descripcion'),
            'procedencia' => $this->nullableTexto('procedencia'),
            'estado' => $this->boolean('estado'),
        ]);
    }

    public function rules(): array
    {
        return [
            'placa' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('vehiculos', 'placa'),
            ],
            'marca' => ['nullable', 'string', 'max:100'],
            'modelo' => ['nullable', 'string', 'max:100'],
            'anio' => [
                'nullable',
                'integer',
                'min:1900',
                'max:' . (now()->year + 1),
            ],
            'color' => ['nullable', 'string', 'max:60'],
            'descripcion' => ['nullable', 'string', 'max:250'],
            'procedencia' => ['nullable', 'string', 'max:150'],
            'estado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'placa.required' =>
            'Ingresa el número de placa del vehículo.',
            'placa.regex' =>
            'La placa solo puede contener letras, números y guiones.',
            'placa.unique' =>
            'La placa ya está vinculada a otro vehículo.',
        ];
    }

    private function nullableTexto(string $campo): ?string
    {
        $valor = trim((string) $this->input($campo));

        return $valor === '' ? null : $valor;
    }
}
