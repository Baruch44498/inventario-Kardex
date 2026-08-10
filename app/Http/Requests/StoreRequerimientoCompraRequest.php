<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequerimientoCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('requerimientos.compra.crear') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'orden_operacion_id' => $this->input('orden_operacion_id') ?: null,
            'descripcion' => $this->filled('descripcion') ? trim((string) $this->input('descripcion')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'fecha_solicitud' => ['required', 'date'],
            'origen' => ['required', Rule::in(['REPOSICION', 'ORDEN_OPERACION'])],
            'orden_operacion_id' => [
                Rule::requiredIf(fn (): bool => $this->input('origen') === 'ORDEN_OPERACION'),
                'nullable',
                'integer',
                Rule::exists('ordenes_operacion', 'id'),
            ],
            'prioridad' => ['required', Rule::in(['BAJA', 'NORMAL', 'ALTA', 'URGENTE'])],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'distinct', Rule::exists('productos', 'id')],
            'detalles.*.cantidad_solicitada' => ['required', 'numeric', 'gt:0', 'max:99999999999.999'],
            'detalles.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'orden_operacion_id.required' => 'Selecciona la OM/OS/OP que origina el requerimiento.',
            'detalles.required' => 'Agrega al menos un producto al requerimiento.',
            'detalles.min' => 'Agrega al menos un producto al requerimiento.',
            'detalles.*.producto_id.distinct' => 'El mismo producto no puede repetirse en el requerimiento.',
            'detalles.*.cantidad_solicitada.gt' => 'La cantidad solicitada debe ser mayor que cero.',
        ];
    }
}
