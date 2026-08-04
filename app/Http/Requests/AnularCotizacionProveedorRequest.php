<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularCotizacionProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_anulacion' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_anulacion.required' => 'Explica por qué se anula la cotización.',
            'motivo_anulacion.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
