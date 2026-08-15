<?php

namespace App\Services\Inventario;

use App\Models\FacturaProveedorDetalle;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaIngresoDetalle;
use App\Models\User;

final class RegistrarAjusteCostoFacturaPosteriorService
{
    public function aplicar(
        FacturaProveedorDetalle $facturaDetalle,
        NotaIngresoDetalle $ingresoDetalle,
        User $usuario
    ): void {
        $costoProvisional = round((float) $ingresoDetalle->costo_unitario, 4);
        $costoReal = $facturaDetalle->costoUnitarioTotalSoles();
        $cantidadFacturada = round((float) $facturaDetalle->cantidad, 3);
        $diferenciaUnitaria = round($costoReal - $costoProvisional, 4);
        $diferenciaTotal = round($cantidadFacturada * $diferenciaUnitaria, 4);

        $facturaDetalle->update([
            'costo_provisional_soles' => $costoProvisional,
            'ajuste_inventario_soles' => 0,
            'diferencia_contable_soles' => $diferenciaTotal,
        ]);

        if (abs($diferenciaTotal) < 0.00005) {
            return;
        }

        $inventario = Inventario::query()
            ->where('producto_id', $ingresoDetalle->producto_id)
            ->where('repisa_id', $ingresoDetalle->repisa_id)
            ->lockForUpdate()
            ->first();

        if (! $inventario || (float) $inventario->stock_actual <= 0) {
            return;
        }

        // El Kardex previo permanece intacto. Solo valorizamos la porción que
        // todavía existe físicamente; el resto queda expuesto para Contabilidad.
        $cantidadEnStock = min($cantidadFacturada, (float) $inventario->stock_actual);
        $ajusteInventario = round($cantidadEnStock * $diferenciaUnitaria, 4);
        $diferenciaContable = round($diferenciaTotal - $ajusteInventario, 4);
        $stockActual = round((float) $inventario->stock_actual, 3);
        $promedioAnterior = round((float) $inventario->costo_promedio_soles, 4);
        $promedioNuevo = round(
            (($stockActual * $promedioAnterior) + $ajusteInventario) / $stockActual,
            4
        );

        $inventario->update(['costo_promedio_soles' => $promedioNuevo]);
        $facturaDetalle->update([
            'ajuste_inventario_soles' => $ajusteInventario,
            'diferencia_contable_soles' => $diferenciaContable,
        ]);

        MovimientoInventario::query()->create([
            'inventario_id' => $inventario->id,
            'producto_id' => $ingresoDetalle->producto_id,
            'repisa_id' => $ingresoDetalle->repisa_id,
            'tipo_movimiento' => 'AJUSTE_COSTO',
            'motivo' => 'FACTURA_POSTERIOR',
            'origen_tipo' => 'FACTURA_PROVEEDOR',
            'origen_id' => $facturaDetalle->factura_proveedor_id,
            'origen_detalle_id' => $facturaDetalle->id,
            'cantidad' => 0,
            'stock_anterior' => $stockActual,
            'stock_posterior' => $stockActual,
            'costo_unitario' => $diferenciaUnitaria,
            'costo_promedio_anterior' => $promedioAnterior,
            'costo_promedio_nuevo' => $promedioNuevo,
            'fecha_movimiento' => now(),
            'observacion' => 'Ajuste por factura registrada después de la recepción.',
            'registrado_por' => $usuario->id,
        ]);
    }
}
