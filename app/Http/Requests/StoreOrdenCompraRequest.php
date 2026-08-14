<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'solicitud_compra_id' => ['required', 'integer', 'exists:solicitudes_compra,id'],
            'fecha_emision' => ['required', 'date'],
            'fecha_entrega_requerida' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'numero_documento_proveedor' => ['nullable', 'string', 'max:60'],
            'condiciones_pago' => ['nullable', 'string', 'max:500'],
            'condiciones_entrega' => ['nullable', 'string', 'max:500'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_entrega_requerida.after_or_equal' => 'La entrega no puede ser anterior a la emisión.',
        ];
    }
}
