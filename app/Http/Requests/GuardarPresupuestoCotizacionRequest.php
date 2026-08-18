<?php

namespace App\Http\Requests;

use App\Models\CotizacionPresupuesto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPresupuestoCotizacionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tipo_costo' => strtoupper(trim((string) $this->input('tipo_costo'))),
            'unidad' => strtoupper(trim((string) $this->input('unidad'))),
            'moneda' => strtoupper(trim((string) $this->input('moneda'))),
            'igv_modo' => strtoupper(trim((string) $this->input('igv_modo'))),
            'descripcion' => trim((string) $this->input('descripcion')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_costo' => [
                'required',
                Rule::in(array_keys(CotizacionPresupuesto::TIPOS)),
            ],
            'producto_id' => [
                'nullable',
                'integer',
                Rule::exists('productos', 'id')->where('estado', true),
            ],
            'descripcion' => ['required', 'string', 'max:300'],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'unidad' => [
                'required',
                Rule::in(array_keys(CotizacionPresupuesto::UNIDADES)),
            ],
            'moneda' => [
                'required',
                Rule::in(array_keys(CotizacionPresupuesto::MONEDAS)),
            ],
            'tipo_cambio' => ['required', 'numeric', 'gte:0.1', 'max:100'],
            'costo_unitario' => ['required', 'numeric', 'gt:0', 'max:999999.9999'],
            'carga_social_porcentaje' => [
                'nullable',
                'numeric',
                'gte:0',
                'max:999.9999',
            ],
            'igv_modo' => [
                'required',
                Rule::in(array_keys(CotizacionPresupuesto::MODOS_IGV)),
            ],
            'igv_porcentaje' => ['required', 'numeric', 'gte:0', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'tipo_cambio.gte' => 'El tipo de cambio debe ser al menos 0.1 PEN por USD.',
            'costo_unitario.gt' => 'El costo unitario debe ser mayor que cero.',
        ];
    }
}
