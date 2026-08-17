<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarConteoInventarioPeriodicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.stock_contado' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999999.999',
            ],
            'detalles.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function attributes(): array
    {
        return [
            'detalles' => 'conteo',
            'detalles.*.stock_contado' => 'stock contado',
            'detalles.*.observacion' => 'observación de la línea',
        ];
    }
}
