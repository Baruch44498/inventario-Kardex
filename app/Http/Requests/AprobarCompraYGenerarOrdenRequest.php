<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AprobarCompraYGenerarOrdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'detalle_ids' => ['required', 'array', 'min:1'],
            'detalle_ids.*' => ['required', 'integer', 'distinct', 'exists:cotizacion_detalles,id'],
            'descripcion' => ['nullable', 'string', 'max:500'],
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
            'detalle_ids.required' => 'Selecciona al menos un producto para generar la orden de compra.',
            'detalle_ids.min' => 'Selecciona al menos un producto para generar la orden de compra.',
            'fecha_entrega_requerida.after_or_equal' => 'La entrega no puede ser anterior a la emisión.',
        ];
    }
}
