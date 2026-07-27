<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepisaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->codigo)),
            'descripcion' => $this->filled('descripcion')
                ? trim((string) $this->descripcion)
                : null,
            'estado' => $this->boolean('estado'),
        ]);
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:40',
                Rule::unique('repisas', 'codigo'),
            ],
            'descripcion' => ['nullable', 'string', 'max:180'],
            'estado' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'estado' => 'estado',
        ];
    }
}
