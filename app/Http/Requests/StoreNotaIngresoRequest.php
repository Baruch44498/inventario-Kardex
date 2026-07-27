<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNotaIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_guia_remision' => $this->filled('numero_guia_remision')
                ? trim((string) $this->input('numero_guia_remision'))
                : null,
            'observacion' => $this->filled('observacion')
                ? trim((string) $this->input('observacion'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'orden_compra_id' => ['required', 'integer', 'exists:ordenes_compra,id'],
            'factura_proveedor_id' => ['nullable', 'integer', 'exists:facturas_proveedor,id'],
            'fecha_ingreso' => ['required', 'date', 'before_or_equal:today'],
            'numero_guia_remision' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.orden_compra_detalle_id' => [
                'required',
                'integer',
                'exists:orden_compra_detalles,id',
            ],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.repisa_id' => ['nullable', 'integer', 'exists:repisas,id'],
            'detalles.*.cantidad' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'detalles.*.costo_unitario' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'detalles.*.lote' => ['nullable', 'string', 'max:80'],
            'detalles.*.fecha_vencimiento' => ['nullable', 'date'],
            'detalles.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ordenId = (int) $this->input('orden_compra_id');
                $orden = DB::table('ordenes_compra')
                    ->where('id', $ordenId)
                    ->first(['id', 'estado']);

                if (! $orden || ! in_array(
                    $orden->estado,
                    ['APROBADA', 'PARCIALMENTE_RECIBIDA'],
                    true
                )) {
                    $validator->errors()->add(
                        'orden_compra_id',
                        'La orden seleccionada no está disponible para recepción.'
                    );

                    return;
                }

                $facturaId = $this->input('factura_proveedor_id');
                if ($facturaId) {
                    $facturaValida = DB::table('facturas_proveedor')
                        ->where('id', $facturaId)
                        ->where('orden_compra_id', $ordenId)
                        ->where('estado', '!=', 'ANULADA')
                        ->exists();

                    if (! $facturaValida) {
                        $validator->errors()->add(
                            'factura_proveedor_id',
                            'La factura no pertenece a la orden de compra seleccionada.'
                        );
                    }
                }

                $detalles = $this->input('detalles', []);
                $filasRecibidas = 0;

                foreach ($detalles as $indice => $detalle) {
                    $cantidad = round((float) ($detalle['cantidad'] ?? 0), 3);

                    if ($cantidad <= 0) {
                        continue;
                    }

                    $filasRecibidas++;
                    $ruta = "detalles.{$indice}";
                    $detalleId = (int) ($detalle['orden_compra_detalle_id'] ?? 0);
                    $productoId = (int) ($detalle['producto_id'] ?? 0);

                    $ordenDetalle = DB::table('orden_compra_detalles')
                        ->where('id', $detalleId)
                        ->where('orden_compra_id', $ordenId)
                        ->where('producto_id', $productoId)
                        ->first([
                            'cantidad_ordenada',
                            'cantidad_recibida',
                        ]);

                    if (! $ordenDetalle) {
                        $validator->errors()->add(
                            "{$ruta}.producto_id",
                            'El producto no pertenece a la orden seleccionada.'
                        );
                        continue;
                    }

                    $pendiente = round(
                        (float) $ordenDetalle->cantidad_ordenada
                        - (float) $ordenDetalle->cantidad_recibida,
                        3
                    );

                    if ($cantidad > $pendiente) {
                        $validator->errors()->add(
                            "{$ruta}.cantidad",
                            "La cantidad supera el pendiente de {$pendiente}."
                        );
                    }

                    if (empty($detalle['repisa_id'])) {
                        $validator->errors()->add(
                            "{$ruta}.repisa_id",
                            'Selecciona la repisa donde se almacenará el producto.'
                        );
                    } elseif (! DB::table('repisas')
                        ->where('id', $detalle['repisa_id'])
                        ->where('estado', true)
                        ->exists()) {
                        $validator->errors()->add(
                            "{$ruta}.repisa_id",
                            'La repisa seleccionada no está activa.'
                        );
                    }

                    if (! isset($detalle['costo_unitario'])
                        || (float) $detalle['costo_unitario'] <= 0) {
                        $validator->errors()->add(
                            "{$ruta}.costo_unitario",
                            'El costo unitario debe ser mayor que cero.'
                        );
                    }

                    if (! empty($detalle['fecha_vencimiento'])
                        && $detalle['fecha_vencimiento'] < $this->input('fecha_ingreso')) {
                        $validator->errors()->add(
                            "{$ruta}.fecha_vencimiento",
                            'La fecha de vencimiento no puede ser anterior al ingreso.'
                        );
                    }
                }

                if ($filasRecibidas === 0) {
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
            'fecha_ingreso.required' => 'Selecciona la fecha de ingreso.',
            'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'detalles.required' => 'La orden debe contener productos pendientes.',
        ];
    }
}
