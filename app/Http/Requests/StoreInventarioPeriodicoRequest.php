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
            'repisa_id' => ['required_without:repisa_ids', 'integer', 'exists:repisas,id'],
            'repisa_ids' => ['required_without:repisa_id', 'array', 'min:1', 'max:200'],
            'repisa_ids.*' => ['required', 'integer', 'distinct', 'exists:repisas,id'],
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
