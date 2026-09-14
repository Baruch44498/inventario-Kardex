<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFacturaProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'serie' => strtoupper(trim((string) $this->input('serie'))),
            'numero' => strtoupper(trim((string) $this->input('numero'))),
            'observacion' => $this->filled('observacion')
                ? trim((string) $this->input('observacion'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'orden_compra_id' => ['required', 'integer', 'exists:ordenes_compra,id'],
            'tipo_documento' => ['required', Rule::in(['FACTURA', 'BOLETA'])],
            'serie' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/'],
            'numero' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/'],
            'fecha_emision' => ['required', 'date', 'before_or_equal:today'],
            'fecha_vencimiento' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'archivo_original' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:15360'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*' => ['array'],
            'detalles.*.orden_compra_detalle_id' => ['required', 'integer', 'exists:orden_compra_detalles,id'],
            'detalles.*.nota_ingreso_detalle_id' => ['required', 'integer', 'exists:nota_ingreso_detalles,id'],
            'detalles.*.cantidad' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $ordenId = (int) $this->input('orden_compra_id');
            $orden = DB::table('ordenes_compra')->where('id', $ordenId)->first();
            if (! $orden || ! in_array($orden->estado, ['APROBADA', 'PARCIALMENTE_RECIBIDA', 'RECIBIDA'], true)) {
                $validator->errors()->add('orden_compra_id', 'La orden no está disponible para facturación.');

                return;
            }

            $duplicada = DB::table('facturas_proveedor')
                ->where('proveedor_id', $orden->proveedor_id)
                ->where('tipo_documento', $this->input('tipo_documento'))
                ->where('serie', $this->input('serie'))
                ->where('numero', $this->input('numero'))
                ->exists();
            if ($duplicada) {
                $validator->errors()->add('numero', 'Este documento ya fue registrado para el proveedor.');
            }

            $ingresosUsados = [];
            $lineasActivas = 0;

            foreach ((array) $this->input('detalles', []) as $indice => $detalle) {
                if (! is_array($detalle)) {
                    continue;
                }
                $cantidad = round((float) ($detalle['cantidad'] ?? 0), 3);
                if ($cantidad <= 0) {
                    continue;
                }

                $lineasActivas++;
                $ruta = "detalles.{$indice}";
                $detalleId = (int) ($detalle['orden_compra_detalle_id'] ?? 0);
                $ingresoDetalleId = (int) ($detalle['nota_ingreso_detalle_id'] ?? 0);

                if (in_array($ingresoDetalleId, $ingresosUsados, true)) {
                    $validator->errors()->add("{$ruta}.nota_ingreso_detalle_id", 'La línea de recepción está repetida.');

                    continue;
                }
                $ingresosUsados[] = $ingresoDetalleId;

                $ingresoDetalle = DB::table('nota_ingreso_detalles as detalle_ingreso')
                    ->join('notas_ingreso as ingreso', 'ingreso.id', '=', 'detalle_ingreso.nota_ingreso_id')
                    ->join('orden_compra_detalles as detalle_oc', 'detalle_oc.id', '=', 'detalle_ingreso.orden_compra_detalle_id')
                    ->where('detalle_ingreso.id', $ingresoDetalleId)
                    ->where('detalle_ingreso.orden_compra_detalle_id', $detalleId)
                    ->where('detalle_oc.orden_compra_id', $ordenId)
                    ->where('ingreso.orden_compra_id', $ordenId)
                    ->where('ingreso.motivo_ingreso', 'COMPRA')
                    ->where('ingreso.estado', 'CONFIRMADA')
                    ->first([
                        'detalle_ingreso.cantidad',
                        'detalle_oc.cantidad_ordenada',
                    ]);
                if (! $ingresoDetalle) {
                    $validator->errors()->add(
                        "{$ruta}.nota_ingreso_detalle_id",
                        'Selecciona una recepción confirmada de esta orden.'
                    );

                    continue;
                }

                $yaFacturadoOrden = (float) DB::table('factura_proveedor_detalles as d')
                    ->join('facturas_proveedor as f', 'f.id', '=', 'd.factura_proveedor_id')
                    ->where('d.orden_compra_detalle_id', $detalleId)
                    ->where('f.estado', '!=', 'ANULADA')
                    ->sum('d.cantidad');
                $yaFacturadoIngreso = (float) DB::table('factura_proveedor_detalles as d')
                    ->join('facturas_proveedor as f', 'f.id', '=', 'd.factura_proveedor_id')
                    ->where('d.nota_ingreso_detalle_id', $ingresoDetalleId)
                    ->where('f.estado', '!=', 'ANULADA')
                    ->sum('d.cantidad');
                $pendienteOrden = max(0, round(
                    (float) $ingresoDetalle->cantidad_ordenada - $yaFacturadoOrden,
                    3
                ));
                $pendienteIngreso = max(0, round(
                    (float) $ingresoDetalle->cantidad - $yaFacturadoIngreso,
                    3
                ));
                $pendiente = min($pendienteOrden, $pendienteIngreso);

                if ($cantidad > $pendiente + 0.0001) {
                    $validator->errors()->add(
                        "{$ruta}.cantidad",
                        "La cantidad supera el saldo recibido pendiente de facturar de {$pendiente}."
                    );
                }
            }

            if ($lineasActivas === 0) {
                $validator->errors()->add('detalles', 'Ingresa al menos una cantidad facturada.');
            }
        }];
    }
}
