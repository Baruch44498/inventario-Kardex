<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnviarCotizacionContabilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'detalle_ids' => ['required', 'array', 'min:1'],
            'detalle_ids.*' => ['required', 'integer', 'distinct', 'exists:cotizacion_detalles,id'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'detalle_ids.required' => 'Selecciona al menos un producto para enviar a Contabilidad.',
            'detalle_ids.min' => 'Selecciona al menos un producto para enviar a Contabilidad.',
        ];
    }
}
