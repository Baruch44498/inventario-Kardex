<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularNotaSalidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo_anulacion' => trim(
                (string) $this->input('motivo_anulacion')
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'motivo_anulacion' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_anulacion.required' =>
                'Escribe el motivo de la anulación.',
            'motivo_anulacion.min' =>
                'El motivo debe tener al menos 5 caracteres.',
            'motivo_anulacion.max' =>
                'El motivo no puede superar los 500 caracteres.',
        ];
    }
}
