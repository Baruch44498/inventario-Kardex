<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComprarSeleccionCotizacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selecciones' => ['required', 'array', 'min:1'],
            'selecciones.*' => ['required', 'integer', 'distinct', 'exists:cotizacion_detalles,id'],
            'fecha_emision' => ['required', 'date'],
            'fecha_entrega_requerida' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'selecciones.required' => 'Selecciona al menos una oferta ganadora.',
            'selecciones.min' => 'Selecciona al menos una oferta ganadora.',
            'selecciones.*.distinct' => 'Una misma oferta no puede cubrir dos líneas diferentes.',
            'fecha_entrega_requerida.after_or_equal' => 'La entrega no puede ser anterior a la emisión.',
        ];
    }
}
