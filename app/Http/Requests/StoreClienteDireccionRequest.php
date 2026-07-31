<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteDireccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['ciudad', 'departamento', 'provincia', 'distrito', 'direccion', 'destino', 'referencia'] as $campo) {
            $valor = trim((string) $this->input($campo));
            $this->merge([$campo => $valor === '' ? null : $valor]);
        }

        $this->merge([
            'es_principal' => $this->boolean('es_principal'),
            'estado' => $this->boolean('estado'),
        ]);
    }

    public function rules(): array
    {
        return [
            'departamento' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'direccion' => ['required', 'string', 'max:350'],
            'destino' => ['nullable', 'string', 'max:150'],
            'referencia' => ['nullable', 'string', 'max:250'],
            'es_principal' => ['required', 'boolean'],
            'estado' => ['required', 'boolean'],
        ];
    }
}
