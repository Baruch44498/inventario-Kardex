<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFacturaProveedorRequest extends FormRequest
{
    private const TOLERANCIA = 0.05;

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
            'moneda' => ['required', Rule::in(['PEN', 'USD'])],
            'tipo_cambio' => ['nullable', 'numeric', 'min:0.000001', 'max:999.999999'],
            'subtotal_documento' => ['required', 'numeric', 'min:0', 'max:9999999999.9999'],
            'impuesto_documento' => ['required', 'numeric', 'min:0', 'max:9999999999.9999'],
            'total_documento' => ['required', 'numeric', 'min:0.01', 'max:9999999999.9999'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'archivo_original' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:15360'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.orden_compra_detalle_id' => ['required', 'integer', 'exists:orden_compra_detalles,id'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'detalles.*.costo_unitario_total' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'detalles.*.afecto_igv' => ['nullable', 'boolean'],
            'detalles.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $ordenId = (int) $this->input('orden_compra_id');
            $orden = DB::table('ordenes_compra')->where('id', $ordenId)->first();
            if (! $orden || $orden->estado === 'ANULADA') {
                $validator->errors()->add('orden_compra_id', 'La orden no está disponible para facturación.');
                return;
            }

            if ((string) $orden->moneda !== (string) $this->input('moneda')) {
                $validator->errors()->add('moneda', 'La moneda debe coincidir con la Orden de Compra.');
            }
            if ($this->input('moneda') === 'USD' && (float) $this->input('tipo_cambio') <= 0) {
                $validator->errors()->add('tipo_cambio', 'Ingresa el tipo de cambio aplicado a la factura en dólares.');
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

            $idsUsados = [];
            $lineasActivas = 0;
            $baseCalculada = 0.0;
            $igvCalculado = 0.0;
            $totalCalculado = 0.0;

            foreach ($this->input('detalles', []) as $indice => $detalle) {
                $cantidad = round((float) ($detalle['cantidad'] ?? 0), 3);
                if ($cantidad <= 0) {
                    continue;
                }

                $lineasActivas++;
                $ruta = "detalles.{$indice}";
                $detalleId = (int) ($detalle['orden_compra_detalle_id'] ?? 0);
                $productoId = (int) ($detalle['producto_id'] ?? 0);

                if (in_array($detalleId, $idsUsados, true)) {
                    $validator->errors()->add("{$ruta}.orden_compra_detalle_id", 'La línea de la OC está repetida.');
                    continue;
                }
                $idsUsados[] = $detalleId;

                $ordenDetalle = DB::table('orden_compra_detalles')
                    ->where('id', $detalleId)
                    ->where('orden_compra_id', $ordenId)
                    ->where('producto_id', $productoId)
                    ->first(['cantidad_ordenada']);
                if (! $ordenDetalle) {
                    $validator->errors()->add("{$ruta}.producto_id", 'El producto no pertenece a la OC seleccionada.');
                    continue;
                }

                $yaFacturado = (float) DB::table('factura_proveedor_detalles as d')
                    ->join('facturas_proveedor as f', 'f.id', '=', 'd.factura_proveedor_id')
                    ->where('d.orden_compra_detalle_id', $detalleId)
                    ->where('f.estado', '!=', 'ANULADA')
                    ->sum('d.cantidad');
                $pendiente = max(0, round((float) $ordenDetalle->cantidad_ordenada - $yaFacturado, 3));
                if ($cantidad > $pendiente + 0.0001) {
                    $validator->errors()->add("{$ruta}.cantidad", "La cantidad supera el pendiente por facturar de {$pendiente}.");
                }

                $costoTotal = round((float) ($detalle['costo_unitario_total'] ?? 0), 4);
                if ($costoTotal <= 0) {
                    $validator->errors()->add("{$ruta}.costo_unitario_total", 'El costo total unitario debe ser mayor que cero.');
                    continue;
                }

                $totalLinea = round($cantidad * $costoTotal, 4);
                $afectoIgv = (bool) ($detalle['afecto_igv'] ?? false);
                $baseLinea = $afectoIgv ? round($totalLinea / 1.18, 4) : $totalLinea;
                $baseCalculada += $baseLinea;
                $igvCalculado += round($totalLinea - $baseLinea, 4);
                $totalCalculado += $totalLinea;
            }

            if ($lineasActivas === 0) {
                $validator->errors()->add('detalles', 'Ingresa al menos una cantidad facturada.');
                return;
            }

            $subtotalDocumento = round((float) $this->input('subtotal_documento'), 4);
            $impuestoDocumento = round((float) $this->input('impuesto_documento'), 4);
            $totalDocumento = round((float) $this->input('total_documento'), 4);
            if (abs(($subtotalDocumento + $impuestoDocumento) - $totalDocumento) > self::TOLERANCIA + 0.0001) {
                $validator->errors()->add('total_documento', 'La base imponible más el IGV no coincide con el total del documento.');
            }
            if (abs($totalCalculado - $totalDocumento) > self::TOLERANCIA + 0.0001) {
                $validator->errors()->add('total_documento', 'El total de las líneas no coincide con el total del documento (tolerancia S/ o US$ 0.05).');
            }
            if (abs($baseCalculada - $subtotalDocumento) > self::TOLERANCIA + 0.0001) {
                $validator->errors()->add('subtotal_documento', 'La base calculada de las líneas no coincide con la base del documento.');
            }
            if (abs($igvCalculado - $impuestoDocumento) > self::TOLERANCIA + 0.0001) {
                $validator->errors()->add('impuesto_documento', 'El IGV calculado de las líneas no coincide con el IGV del documento.');
            }
        }];
    }
}
