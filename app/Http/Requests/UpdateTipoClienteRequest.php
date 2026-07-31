<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTipoClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $descripcion = trim((string) $this->input('descripcion'));

        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'descripcion' => $descripcion === '' ? null : $descripcion,
            'estado' => $this->boolean('estado'),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'porcentaje_ganancia' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'descripcion' => ['nullable', 'string', 'max:250'],
            'estado' => ['required', 'boolean'],
        ];
    }
}
