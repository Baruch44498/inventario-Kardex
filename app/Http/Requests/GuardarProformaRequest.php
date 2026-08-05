<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarProformaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $detalles = collect($this->input('detalles', []))
            ->map(function ($detalle): array {
                $detalle = is_array($detalle) ? $detalle : [];

                return [
                    ...$detalle,
                    // El IGV se decide al cotizar en Logistica, no en Almacen.
                    'igv_modo' => 'NO_APLICA',
                ];
            })
            ->all();

        $this->merge([
            'tipo_origen' => 'VENTA_DIRECTA',
            // La proforma de Almacén usa siempre los costos internos en soles.
            // La moneda negociada pertenece a la cotización de Logística.
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            // Las condiciones comerciales se registran en la cotización.
            'condiciones_pago' => null,
            'condiciones_entrega' => null,
            'detalles' => $detalles,
        ]);
    }

    public function rules(): array
    {
        return [
            'tipo_origen' => ['required', Rule::in(['VENTA_DIRECTA'])],
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('clientes', 'id')->where('estado', true),
            ],
            'fecha_emision' => ['required', 'date'],
            'fecha_validez' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'moneda' => ['required', Rule::in(['PEN'])],
            'tipo_cambio' => ['nullable'],
            'condiciones_pago' => ['nullable', 'string', 'max:500'],
            'condiciones_entrega' => ['nullable', 'string', 'max:500'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('productos', 'id')->where('estado', true),
            ],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.igv_modo' => [
                'required',
                Rule::in(['INCLUIDO', 'AGREGAR', 'NO_APLICA']),
            ],
            'detalles.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecciona el cliente que solicita la venta directa.',
            'detalles.required' => 'Agrega al menos un producto a la proforma.',
            'detalles.*.producto_id.distinct' => 'Un producto no puede repetirse en la proforma.',
        ];
    }
}
