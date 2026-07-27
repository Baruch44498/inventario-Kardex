<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class StoreNotaSalidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'entregado_a' => trim((string) $this->input('entregado_a')),
            'observacion' => $this->filled('observacion')
                ? trim((string) $this->input('observacion'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'orden_operacion_id' => [
                'required',
                'integer',
                'exists:ordenes_operacion,id',
            ],
            'fecha_salida' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'entregado_a' => [
                'required',
                'string',
                'max:150',
            ],
            'observacion' => [
                'nullable',
                'string',
                'max:500',
            ],
            'detalles' => [
                'required',
                'array',
                'min:1',
            ],
            'detalles.*.inventario_id' => [
                'required',
                'integer',
                'exists:inventarios,id',
            ],
            'detalles.*.producto_id' => [
                'required',
                'integer',
                'exists:productos,id',
            ],
            'detalles.*.repisa_id' => [
                'required',
                'integer',
                'exists:repisas,id',
            ],
            'detalles.*.cantidad' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999999.999',
            ],
            'detalles.*.observacion' => [
                'nullable',
                'string',
                'max:300',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $orden = DB::table('ordenes_operacion')
                    ->where('id', $this->integer('orden_operacion_id'))
                    ->first(['id', 'estado']);

                if (! $orden
                    || in_array($orden->estado, ['ANULADA', 'CERRADA'], true)) {
                    $validator->errors()->add(
                        'orden_operacion_id',
                        'La orden seleccionada no está disponible para registrar salidas.'
                    );

                    return;
                }

                $filasSeleccionadas = 0;
                $combinaciones = [];

                foreach ($this->input('detalles', []) as $indice => $detalle) {
                    $cantidad = round(
                        (float) ($detalle['cantidad'] ?? 0),
                        3
                    );

                    if ($cantidad <= 0) {
                        continue;
                    }

                    $filasSeleccionadas++;
                    $ruta = "detalles.{$indice}";
                    $inventarioId = (int) ($detalle['inventario_id'] ?? 0);
                    $productoId = (int) ($detalle['producto_id'] ?? 0);
                    $repisaId = (int) ($detalle['repisa_id'] ?? 0);
                    $clave = "{$productoId}:{$repisaId}";

                    if (isset($combinaciones[$clave])) {
                        $validator->errors()->add(
                            "{$ruta}.cantidad",
                            'El producto y la repisa están repetidos en la salida.'
                        );
                    }

                    $combinaciones[$clave] = true;

                    $inventario = DB::table('inventarios as i')
                        ->join('productos as p', 'p.id', '=', 'i.producto_id')
                        ->join('repisas as r', 'r.id', '=', 'i.repisa_id')
                        ->where('i.id', $inventarioId)
                        ->where('i.producto_id', $productoId)
                        ->where('i.repisa_id', $repisaId)
                        ->first([
                            'i.stock_actual',
                            'p.estado as producto_activo',
                            'r.estado as repisa_activa',
                        ]);

                    if (! $inventario) {
                        $validator->errors()->add(
                            "{$ruta}.inventario_id",
                            'La existencia seleccionada ya no está disponible.'
                        );
                        continue;
                    }

                    if (! $inventario->producto_activo) {
                        $validator->errors()->add(
                            "{$ruta}.producto_id",
                            'El producto seleccionado está inactivo.'
                        );
                    }

                    if (! $inventario->repisa_activa) {
                        $validator->errors()->add(
                            "{$ruta}.repisa_id",
                            'La repisa seleccionada está inactiva.'
                        );
                    }

                    $disponible = round(
                        (float) $inventario->stock_actual,
                        3
                    );

                    if ($cantidad > $disponible) {
                        $validator->errors()->add(
                            "{$ruta}.cantidad",
                            "La cantidad supera el stock disponible de {$disponible}."
                        );
                    }
                }

                if ($filasSeleccionadas === 0) {
                    $validator->errors()->add(
                        'detalles',
                        'Ingresa una cantidad mayor que cero en al menos un producto.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_salida.required' =>
                'Selecciona la fecha de salida.',
            'fecha_salida.before_or_equal' =>
                'La fecha de salida no puede ser futura.',
            'entregado_a.required' =>
                'Indica quién recibe los productos.',
            'detalles.required' =>
                'Selecciona al menos una existencia para la salida.',
        ];
    }
}
