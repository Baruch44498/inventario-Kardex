<?php

namespace App\Services\Inventario;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaIngreso;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\User;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarNotaIngresoService
{
    public function __construct(
        private GenerarCodigoDocumentoService $codigos
    ) {
    }

    public function registrarYConfirmar(array $datos, User $usuario): NotaIngreso
    {
        return $this->codigos->usarSiguiente(
            'notas_ingreso',
            'NI',
            $datos['fecha_ingreso'],
            function (string $codigo) use ($datos, $usuario): NotaIngreso {
                return DB::transaction(function () use ($datos, $usuario, $codigo) {
            $ordenCompra = OrdenCompra::query()
                ->lockForUpdate()
                ->findOrFail($datos['orden_compra_id']);

            if (empty($datos['detalles'])) {
                throw ValidationException::withMessages([
                    'detalles' => 'La nota de ingreso debe contener al menos un detalle.',
                ]);
            }

            $nota = NotaIngreso::create([
                'orden_compra_id' => $ordenCompra->id,
                'factura_proveedor_id' => $datos['factura_proveedor_id'] ?? null,
                'codigo' => $codigo,
                'fecha_ingreso' => $datos['fecha_ingreso'],
                'numero_guia_remision' => $datos['numero_guia_remision'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'estado' => 'CONFIRMADA',
                'registrado_por' => $usuario->id,
                'confirmado_por' => $usuario->id,
                'confirmado_en' => now(),
            ]);

            foreach ($datos['detalles'] as $item) {
                $cantidad = round((float) $item['cantidad'], 3);
                $costoUnitario = round((float) $item['costo_unitario'], 4);

                if ($cantidad <= 0) {
                    throw ValidationException::withMessages([
                        'cantidad' => 'La cantidad recibida debe ser mayor que cero.',
                    ]);
                }

                $ordenDetalle = OrdenCompraDetalle::query()
                    ->whereKey($item['orden_compra_detalle_id'])
                    ->where('orden_compra_id', $ordenCompra->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $ordenDetalle->producto_id !== (int) $item['producto_id']) {
                    throw ValidationException::withMessages([
                        'producto_id' => 'El producto no coincide con el detalle de la orden de compra.',
                    ]);
                }

                $pendiente = round(
                    (float) $ordenDetalle->cantidad_ordenada
                    - (float) $ordenDetalle->cantidad_recibida,
                    3
                );

                if ($cantidad > $pendiente) {
                    throw ValidationException::withMessages([
                        'cantidad' => "La cantidad recibida supera el pendiente de {$pendiente}.",
                    ]);
                }

                $detalle = $nota->detalles()->create([
                    'orden_compra_detalle_id' => $ordenDetalle->id,
                    'producto_id' => $item['producto_id'],
                    'repisa_id' => $item['repisa_id'],
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'subtotal' => round($cantidad * $costoUnitario, 4),
                    'lote' => $item['lote'] ?? null,
                    'fecha_vencimiento' => $item['fecha_vencimiento'] ?? null,
                    'observacion' => $item['observacion'] ?? null,
                ]);

                $inventario = Inventario::query()
                    ->where('producto_id', $item['producto_id'])
                    ->where('repisa_id', $item['repisa_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$inventario) {
                    $inventario = Inventario::create([
                        'producto_id' => $item['producto_id'],
                        'repisa_id' => $item['repisa_id'],
                        'stock_actual' => 0,
                        'stock_minimo' => 0,
                        'stock_maximo' => null,
                        'costo_promedio_soles' => 0,
                    ]);
                }

                $stockAnterior = (float) $inventario->stock_actual;
                $costoAnterior = (float) $inventario->costo_promedio_soles;
                $stockPosterior = round($stockAnterior + $cantidad, 3);

                $costoNuevo = $stockPosterior > 0
                    ? round(
                        (($stockAnterior * $costoAnterior) + ($cantidad * $costoUnitario))
                        / $stockPosterior,
                        4
                    )
                    : 0;

                $inventario->update([
                    'stock_actual' => $stockPosterior,
                    'costo_promedio_soles' => $costoNuevo,
                ]);

                MovimientoInventario::create([
                    'inventario_id' => $inventario->id,
                    'producto_id' => $item['producto_id'],
                    'repisa_id' => $item['repisa_id'],
                    'tipo_movimiento' => 'ENTRADA',
                    'motivo' => 'COMPRA',
                    'origen_tipo' => 'NOTA_INGRESO',
                    'origen_id' => $nota->id,
                    'origen_detalle_id' => $detalle->id,
                    'cantidad' => $cantidad,
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockPosterior,
                    'costo_unitario' => $costoUnitario,
                    'costo_promedio_anterior' => $costoAnterior,
                    'costo_promedio_nuevo' => $costoNuevo,
                    'fecha_movimiento' => now(),
                    'observacion' => $item['observacion'] ?? null,
                    'registrado_por' => $usuario->id,
                ]);

                $ordenDetalle->update([
                    'cantidad_recibida' => round(
                        (float) $ordenDetalle->cantidad_recibida + $cantidad,
                        3
                    ),
                ]);
            }

            $ordenCompra->load('detalles');

            $completa = $ordenCompra->detalles->every(
                fn ($detalle) =>
                    (float) $detalle->cantidad_recibida
                    >= (float) $detalle->cantidad_ordenada
            );

            $ordenCompra->update([
                'estado' => $completa
                    ? 'RECIBIDA'
                    : 'PARCIALMENTE_RECIBIDA',
            ]);

            return $nota->load([
                'ordenCompra.detalles.producto',
                'facturaProveedor',
                'registrador',
                'confirmador',
                'detalles.producto',
                'detalles.repisa',
            ]);
                });
            }
        );
    }
}
