<?php

namespace App\Http\Requests;

use App\Models\CostoDirectoOrden;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostoDirectoOrdenRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tipo' => strtoupper(trim((string) $this->input('tipo'))),
            'unidad' => strtoupper(trim((string) $this->input('unidad'))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(CostoDirectoOrden::TIPOS))],
            'fecha_costo' => ['required', 'date', 'before_or_equal:today'],
            'descripcion' => ['required', 'string', 'max:300'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            'unidad' => ['required', Rule::in(array_keys(CostoDirectoOrden::UNIDADES))],
            'costo_unitario_soles' => ['required', 'numeric', 'gt:0', 'max:999999999.9999'],
            'documento_referencia' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_costo.before_or_equal' => 'La fecha del costo no puede ser futura.',
            'cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'costo_unitario_soles.gt' => 'El costo unitario debe ser mayor que cero.',
        ];
    }
}
