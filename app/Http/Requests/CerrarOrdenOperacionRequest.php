<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerrarOrdenOperacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $observacion = trim((string) $this->input('observacion_cierre'));

        $this->merge([
            'observacion_cierre' => $observacion !== '' ? $observacion : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'observacion_cierre' => ['nullable', 'string', 'max:500'],
        ];
    }
}
