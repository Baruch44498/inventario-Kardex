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
            'grupo_costo' => filled($this->input('grupo_costo'))
                ? trim((string) $this->input('grupo_costo'))
                : null,
            'margen_porcentaje' => $this->input('margen_porcentaje', 0),
            'igv_venta_porcentaje' => $this->input('igv_venta_porcentaje', 18),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'componente_id' => ['nullable', 'integer', 'exists:cotizacion_componentes,id'],
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
            'grupo_costo' => ['nullable', 'string', 'max:150'],
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
            'margen_porcentaje' => ['required', 'numeric', 'gte:0', 'max:999.9999'],
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
            'igv_venta_porcentaje' => ['required', 'numeric', 'gte:0', 'max:100'],
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
