<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
                    // El IGV se decide al cotizar en Logística, no en Almacén.
                    'igv_modo' => 'NO_APLICA',
                    'tratamiento' => strtoupper(trim((string) (
                        $detalle['tratamiento'] ?? 'VENTA'
                    ))),
                ];
            })
            ->all();

        $this->merge([
            'tipo_origen' => 'VENTA_DIRECTA',
            // La proforma de Almacén mantiene referencias internas en soles.
            'moneda' => 'PEN',
            'tipo_cambio' => null,
            // La Proforma de Almacén registra una salida inmediata; no maneja vigencia comercial.
            'fecha_validez' => null,
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
                Rule::exists('productos', 'id')->where('estado', true),
            ],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.tratamiento' => [
                'required',
                Rule::in(['VENTA', 'PRESTAMO']),
            ],
            'detalles.*.igv_modo' => [
                'required',
                Rule::in(['INCLUIDO', 'AGREGAR', 'NO_APLICA']),
            ],
            'detalles.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $detalles = collect($this->input('detalles', []));

                // Un mismo producto puede figurar una vez como venta y una vez
                // como préstamo, pero no repetirse dentro del mismo tratamiento.
                $repetidos = $detalles
                    ->groupBy(fn(array $detalle): string =>
                    (string) ($detalle['producto_id'] ?? '') . '|'
                        . (string) ($detalle['tratamiento'] ?? ''))
                    ->filter(fn($grupo): bool => $grupo->count() > 1);

                if ($repetidos->isNotEmpty()) {
                    $validator->errors()->add(
                        'detalles',
                        'Un producto no puede repetirse con el mismo tratamiento dentro de la proforma.'
                    );
                }

                $productoIds = $detalles
                    ->pluck('producto_id')
                    ->filter(fn($id): bool => is_numeric($id))
                    ->map(fn($id): int => (int) $id)
                    ->unique();

                if ($productoIds->isEmpty()) {
                    return;
                }

                $stocks = DB::table('inventarios')
                    ->whereIn('producto_id', $productoIds)
                    ->groupBy('producto_id')
                    ->selectRaw('producto_id, COALESCE(SUM(stock_actual), 0) as stock_total')
                    ->pluck('stock_total', 'producto_id');

                $solicitadoPorProducto = $detalles
                    ->groupBy(fn(array $detalle): int => (int) ($detalle['producto_id'] ?? 0))
                    ->map(fn($lineas): float => (float) $lineas->sum(
                        fn(array $detalle): float => (float) ($detalle['cantidad'] ?? 0)
                    ));

                foreach ($solicitadoPorProducto as $productoId => $cantidad) {
                    if ($productoId <= 0) {
                        continue;
                    }

                    $stock = (float) ($stocks[$productoId] ?? 0);
                    if ($cantidad > $stock + 0.0001) {
                        $validator->errors()->add(
                            'detalles',
                            'La proforma solo puede incluir existencias físicas disponibles. '
                                . 'Solicitado: ' . number_format($cantidad, 2)
                                . ' · Stock: ' . number_format($stock, 2) . '.'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required' => 'Selecciona el cliente de la proforma.',
            'detalles.required' => 'Agrega al menos un producto a la proforma.',
            'detalles.*.tratamiento.in' => 'Indica si el producto es venta o préstamo.',
        ];
    }
}
