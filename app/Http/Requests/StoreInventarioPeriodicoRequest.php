<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventarioPeriodicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'repisa_id' => ['required', 'integer', 'exists:repisas,id'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'repisa_id' => 'repisa',
            'observacion' => 'observación',
        ];
    }
}
