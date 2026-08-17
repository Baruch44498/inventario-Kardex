<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularCostoDirectoOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_anulacion' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
