<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarAvanceOrdenOperacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'detalle' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'porcentaje.required' => 'Indica el porcentaje actual de avance.',
            'porcentaje.max' => 'El avance no puede superar el 100%.',
            'porcentaje.decimal' => 'Usa como máximo dos decimales para el avance.',
            'detalle.required' => 'Describe brevemente qué trabajo se completó.',
        ];
    }
}
