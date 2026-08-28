<?php

namespace App\Http\Requests;

use App\Models\CotizacionPresupuesto;
use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarMaterialesEtapaCotizacionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $materiales = collect($this->input('materiales', []))
            ->filter(fn ($material): bool => is_array($material))
            ->filter(fn (array $material): bool => filled($material['producto_id'] ?? null)
                || filled($material['costo_unitario'] ?? null))
            ->map(fn (array $material): array => [
                'producto_id' => $material['producto_id'] ?? null,
                'cantidad' => $material['cantidad'] ?? null,
                'costo_unitario' => $material['costo_unitario'] ?? null,
            ])
            ->values()
            ->all();

        $this->merge([
            'grupo_costo' => trim((string) $this->input('grupo_costo')),
            'moneda' => strtoupper(trim((string) $this->input('moneda'))),
            'igv_modo' => strtoupper(trim((string) $this->input('igv_modo'))),
            'margen_porcentaje' => $this->input('margen_porcentaje', 0),
            'igv_venta_porcentaje' => $this->input('igv_venta_porcentaje', 18),
            'materiales' => $materiales,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'componente_id' => ['required', 'integer', 'exists:cotizacion_componentes,id'],
            'grupo_costo' => ['required', 'string', 'max:150'],
            'moneda' => ['required', Rule::in(array_keys(CotizacionPresupuesto::MONEDAS))],
            'tipo_cambio' => ['required', 'numeric', 'gte:0.1', 'max:100'],
            'margen_porcentaje' => ['required', 'numeric', 'gte:0', 'max:999.9999'],
            'igv_modo' => ['required', Rule::in(array_keys(CotizacionPresupuesto::MODOS_IGV))],
            'igv_porcentaje' => ['required', 'numeric', 'gte:0', 'max:100'],
            'igv_venta_porcentaje' => ['required', 'numeric', 'gte:0', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'materiales' => ['required', 'array', 'min:1', 'max:100'],
            'materiales.*.producto_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('productos', 'id')->where('estado', true),
            ],
            'materiales.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'materiales.*.costo_unitario' => ['required', 'numeric', 'gt:0', 'max:999999.9999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $materiales = collect($this->input('materiales', []));
            $productos = Producto::query()
                ->where('estado', true)
                ->whereIn('id', $materiales->pluck('producto_id')->filter()->all())
                ->get()
                ->keyBy('id');

            $materiales->each(function (array $material, int $indice) use ($productos, $validator): void {
                $producto = $productos->get((int) ($material['producto_id'] ?? 0));
                $cantidad = $material['cantidad'] ?? null;

                if ($producto && is_numeric($cantidad) && ! $producto->cantidadAdmitida((float) $cantidad)) {
                    $validator->errors()->add(
                        "materiales.$indice.cantidad",
                        'Este producto se controla en cantidades enteras y no admite fracciones.'
                    );
                }
            });
        });
    }

    public function messages(): array
    {
        return [
            'grupo_costo.required' => 'Escribe el nombre de la etapa que agrupará estos materiales.',
            'materiales.required' => 'Agrega al menos un material.',
            'materiales.min' => 'Agrega al menos un material.',
            'materiales.max' => 'Puedes guardar hasta 100 materiales por bloque.',
            'materiales.*.producto_id.required' => 'Selecciona un producto del catálogo.',
            'materiales.*.producto_id.distinct' => 'El mismo producto está repetido en esta etapa.',
            'materiales.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'materiales.*.costo_unitario.gt' => 'El costo unitario debe ser mayor que cero.',
        ];
    }
}
