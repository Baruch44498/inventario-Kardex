<?php

namespace App\Http\Requests;

use App\Models\CotizacionPresupuesto;
use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarPresupuestoCotizacionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $tipo = strtoupper(trim((string) $this->input('tipo_costo')));
        $unidad = strtoupper(trim((string) $this->input('unidad')));
        $area = filled($this->input('area_nombre'))
            ? trim((string) $this->input('area_nombre'))
            : (filled($this->input('grupo_costo'))
                ? trim((string) $this->input('grupo_costo'))
                : null);
        $ejecucionServicio = $tipo === 'SERVICIO_TERCERO'
            ? strtoupper(trim((string) ($this->input('ejecucion_servicio') ?: 'EXTERNO')))
            : null;

        if ($tipo === 'MATERIAL' && $this->filled('producto_id')) {
            $producto = Producto::query()
                ->with('unidadMedida')
                ->where('estado', true)
                ->find($this->integer('producto_id'));
            $unidad = $producto
                ? (CotizacionPresupuesto::unidadDeProducto($producto) ?? '')
                : '';
        }

        $igvModo = strtoupper(trim((string) $this->input('igv_modo')));

        $this->merge([
            'tipo_costo' => $tipo,
            'unidad' => $unidad,
            'moneda' => strtoupper(trim((string) $this->input('moneda'))),
            'igv_modo' => $igvModo,
            'igv_porcentaje' => $igvModo === 'NO_APLICA'
                ? 0
                : $this->input('igv_porcentaje', 18),
            'descripcion' => trim((string) $this->input('descripcion')),
            'area_nombre' => $area,
            'grupo_costo' => $area,
            'ejecucion_servicio' => $ejecucionServicio,
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
        $tipo = (string) $this->input('tipo_costo');
        $unidadesPermitidas = $tipo === 'MATERIAL'
            ? array_values(array_filter([(string) $this->input('unidad')]))
            : CotizacionPresupuesto::unidadesParaTipo($tipo);

        return [
            'componente_id' => ['nullable', 'integer', 'exists:cotizacion_componentes,id'],
            'tipo_costo' => [
                'required',
                Rule::in(array_keys(CotizacionPresupuesto::TIPOS)),
            ],
            'producto_id' => [
                'required_if:tipo_costo,MATERIAL',
                'nullable',
                'integer',
                Rule::exists('productos', 'id')->where('estado', true),
            ],
            'descripcion' => ['required', 'string', 'max:300'],
            'grupo_costo' => ['nullable', 'string', 'max:150'],
            'area_nombre' => [
                'nullable',
                'string',
                'max:150',
                Rule::requiredIf($tipo === 'MATERIAL'),
            ],
            'ejecucion_servicio' => [
                Rule::requiredIf($tipo === 'SERVICIO_TERCERO'),
                'nullable',
                Rule::in(['EXTERNO', 'INTERNO_HIDROIL']),
            ],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'unidad' => [
                'required',
                'string',
                'max:20',
                Rule::in($unidadesPermitidas),
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->input('tipo_costo') !== 'MATERIAL'
                || ! $this->filled('producto_id')
                || ! is_numeric($this->input('cantidad'))
            ) {
                return;
            }

            $producto = Producto::query()
                ->where('estado', true)
                ->find($this->integer('producto_id'));
            $cantidad = (float) $this->input('cantidad');

            if ($producto && ! $producto->cantidadAdmitida($cantidad)) {
                $validator->errors()->add(
                    'cantidad',
                    'Este producto se controla en cantidades enteras y no admite fracciones.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'tipo_cambio.gte' => 'El tipo de cambio debe ser al menos 0.1 PEN por USD.',
            'costo_unitario.gt' => 'El costo unitario debe ser mayor que cero.',
            'producto_id.required_if' => 'Selecciona el producto que saldrá de almacén.',
            'unidad.in' => 'La unidad no corresponde al tipo de costo seleccionado.',
            'area_nombre.required' => 'Selecciona o escribe el área del material.',
            'ejecucion_servicio.required' => 'Indica si el servicio será externo o ejecutado por HIDROIL.',
        ];
    }
}
