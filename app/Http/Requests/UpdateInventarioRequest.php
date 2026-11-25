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
                'required',
                'numeric',
                'gt:stock_minimo',
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
            'stock_maximo.gt' => 'El stock máximo debe ser mayor que el stock mínimo para definir un nivel real de reposición.',
        ];
    }
}
