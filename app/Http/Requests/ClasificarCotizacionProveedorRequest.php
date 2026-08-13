<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClasificarCotizacionProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'in:NO_REQUERIDA,NO_UTILIZADA'],
            'motivo_evaluacion' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.in' => 'Selecciona una clasificación válida.',
            'motivo_evaluacion.required' => 'Explica por qué la cotización no continuará a compra.',
            'motivo_evaluacion.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
