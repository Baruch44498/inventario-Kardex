<?php

namespace App\Services\Compras;

use App\Models\FacturaProveedor;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegistrarFacturaProveedorService
{
    /** @param array<string, mixed> $datos */
    public function registrar(array $datos, array $archivo, User $usuario): FacturaProveedor
    {
        return DB::transaction(function () use ($datos, $archivo, $usuario): FacturaProveedor {
            $orden = OrdenCompra::query()
                ->with('detalles.producto')
                ->whereKey($datos['orden_compra_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($orden->estaAnulada()) {
                throw ValidationException::withMessages([
                    'orden_compra_id' => 'La orden fue anulada y no admite facturas.',
                ]);
            }

            $detallesActivos = collect($datos['detalles'])
                ->filter(fn(array $detalle): bool => round((float) ($detalle['cantidad'] ?? 0), 3) > 0)
                ->values();
            if ($detallesActivos->isEmpty()) {
                throw ValidationException::withMessages(['detalles' => 'Ingresa al menos una línea facturada.']);
            }

            $totalLineas = round((float) $detallesActivos->sum(
                fn(array $detalle): float => round((float) $detalle['cantidad'], 3)
                    * round((float) $detalle['costo_unitario_total'], 4)
            ), 4);

            $factura = FacturaProveedor::query()->create([
                'orden_compra_id' => $orden->id,
                'proveedor_id' => $orden->proveedor_id,
                'tipo_documento' => $datos['tipo_documento'],
                'serie' => $datos['serie'],
                'numero' => $datos['numero'],
                'fecha_emision' => $datos['fecha_emision'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'moneda' => $datos['moneda'],
                'tipo_cambio' => $datos['moneda'] === 'USD' ? $datos['tipo_cambio'] : null,
                'subtotal' => round((float) $datos['subtotal_documento'], 4),
                'impuesto' => round((float) $datos['impuesto_documento'], 4),
                'total' => round((float) $datos['total_documento'], 4),
                'ajuste_redondeo' => round((float) $datos['total_documento'] - $totalLineas, 4),
                'observacion' => $datos['observacion'] ?? null,
                'archivo_original_path' => $archivo['path'],
                'archivo_original_nombre' => $archivo['nombre'],
                'archivo_original_mime' => $archivo['mime'],
                'archivo_original_hash' => $archivo['hash'],
                'estado' => 'REGISTRADA',
                'registrado_por' => $usuario->id,
            ]);

            foreach ($detallesActivos as $item) {
                $ordenDetalle = OrdenCompraDetalle::query()
                    ->whereKey($item['orden_compra_detalle_id'])
                    ->where('orden_compra_id', $orden->id)
                    ->where('producto_id', $item['producto_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $yaFacturado = (float) $ordenDetalle->facturaProveedorDetalles()
                    ->whereHas('facturaProveedor', fn($query) => $query->where('estado', '!=', 'ANULADA'))
                    ->sum('cantidad');
                $cantidad = round((float) $item['cantidad'], 3);
                $pendiente = max(0, round((float) $ordenDetalle->cantidad_ordenada - $yaFacturado, 3));
                if ($cantidad > $pendiente + 0.0001) {
                    throw ValidationException::withMessages([
                        'detalles' => "La cantidad de {$ordenDetalle->producto?->codigo} supera el pendiente por facturar de {$pendiente}.",
                    ]);
                }

                $costoTotal = round((float) $item['costo_unitario_total'], 4);
                $total = round($cantidad * $costoTotal, 4);
                $igvPorcentaje = ! empty($item['afecto_igv']) ? 18.0 : 0.0;
                $subtotal = $igvPorcentaje > 0 ? round($total / 1.18, 4) : $total;
                $impuesto = round($total - $subtotal, 4);

                $factura->detalles()->create([
                    'orden_compra_detalle_id' => $ordenDetalle->id,
                    'producto_id' => $ordenDetalle->producto_id,
                    'descripcion' => $ordenDetalle->producto?->descripcion ?? $ordenDetalle->observacion ?? 'Producto de OC',
                    'cantidad' => $cantidad,
                    'precio_unitario' => $cantidad > 0 ? round($subtotal / $cantidad, 4) : 0,
                    'descuento_porcentaje' => 0,
                    'igv_porcentaje' => $igvPorcentaje,
                    'subtotal' => $subtotal,
                    'impuesto' => $impuesto,
                    'total' => $total,
                    'observacion' => $item['observacion'] ?? null,
                ]);
            }

            return $factura->load([
                'ordenCompra.proveedor',
                'proveedor',
                'registrador',
                'detalles.producto.unidadMedida',
                'notasIngreso',
            ]);
        });
    }
}
