<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AprobarCompraYGenerarOrdenRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $origen = strtoupper(trim((string) $this->input('origen_compra_directa')));
        $justificacion = trim((string) $this->input('justificacion_origen'));

        $this->merge([
            'es_compra_directa' => $this->boolean('es_compra_directa'),
            'origen_compra_directa' => $origen !== '' ? $origen : null,
            'justificacion_origen' => $justificacion !== '' ? $justificacion : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'detalle_ids' => ['required', 'array', 'min:1'],
            'detalle_ids.*' => ['required', 'integer', 'distinct', 'exists:cotizacion_detalles,id'],
            'es_compra_directa' => ['required', 'boolean'],
            'origen_compra_directa' => [
                Rule::requiredIf(fn(): bool => $this->boolean('es_compra_directa')),
                'nullable',
                Rule::in(['COMPRA_DIRECTA', 'REGULARIZACION', 'URGENTE', 'REPOSICION']),
            ],
            'justificacion_origen' => [
                Rule::requiredIf(fn(): bool => $this->boolean('es_compra_directa')),
                'nullable',
                'string',
                'min:10',
                'max:500',
            ],
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
            'origen_compra_directa.required' => 'Selecciona el motivo operativo de la compra sin requerimiento.',
            'justificacion_origen.required' => 'Explica por qué la compra se realiza sin requerimiento previo.',
            'justificacion_origen.min' => 'La justificación debe tener al menos 10 caracteres.',
            'fecha_entrega_requerida.after_or_equal' => 'La entrega no puede ser anterior a la emisión.',
        ];
    }
}
