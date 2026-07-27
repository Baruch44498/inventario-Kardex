<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'stock_maximo' => [
                'nullable',
                'numeric',
                'gte:stock_minimo',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'stock_minimo' => 'stock mínimo',
            'stock_maximo' => 'stock máximo',
        ];
    }

    public function messages(): array
    {
        return [
            'stock_maximo.gte' => 'El stock máximo debe ser mayor o igual al stock mínimo.',
        ];
    }
}
