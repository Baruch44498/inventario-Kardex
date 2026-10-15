<?php

namespace App\Services\Contabilidad;

use App\Models\FacturaProveedor;
use App\Models\PagoFacturaProveedor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarPagoFacturaProveedorService
{
    /** @param array<string, mixed> $datos */
    public function registrar(FacturaProveedor $factura, array $datos, User $usuario): PagoFacturaProveedor
    {
        return DB::transaction(function () use ($factura, $datos, $usuario): PagoFacturaProveedor {
            $factura = FacturaProveedor::query()->lockForUpdate()->findOrFail($factura->id);
            if ($factura->estaAnulada()) {
                throw ValidationException::withMessages(['monto' => 'La factura está anulada.']);
            }

            $pagado = $this->unidades($factura->pagosVigentes()->sum('monto'));
            $saldo = $this->unidades($factura->total) - $pagado;
            $monto = $this->unidades($datos['monto']);
            // Facturas anteriores podían guardar fracciones de centavo. Solo
            // el pago final en centavos puede cerrar esa diferencia, ajustando
            // el total documental dentro de la misma transacción.
            $totalCentavos = $this->unidades(round((float) $factura->total, 2));
            $ajuste = $totalCentavos - $this->unidades($factura->total);
            $cierreRedondeado = $ajuste !== 0
                && abs($ajuste) <= 50
                && $monto % 100 === 0
                && $monto === $totalCentavos - $pagado;
            if ($saldo <= 0 || $monto <= 0 || ($monto > $saldo && ! $cierreRedondeado)) {
                throw ValidationException::withMessages(['monto' => 'El pago debe ser mayor que cero y no superar el saldo de la factura.']);
            }

            if ($cierreRedondeado) {
                $factura->update([
                    'total' => $totalCentavos / 10000,
                    'ajuste_redondeo' => round((float) $factura->ajuste_redondeo + $ajuste / 10000, 4),
                ]);
            }

            $pago = $factura->pagos()->create($datos + ['registrado_por' => $usuario->id]);
            $factura->update(['estado' => $monto === $saldo || $cierreRedondeado ? 'PAGADA' : 'PARCIAL']);

            return $pago;
        });
    }

    public function anular(FacturaProveedor $factura, PagoFacturaProveedor $pago, string $motivo, User $usuario): void
    {
        DB::transaction(function () use ($factura, $pago, $motivo, $usuario): void {
            $factura = FacturaProveedor::query()->lockForUpdate()->findOrFail($factura->id);
            $pago = $factura->pagos()->whereKey($pago->id)->firstOrFail();
            if ($pago->estaAnulado() || $factura->estaAnulada()) {
                throw ValidationException::withMessages(['motivo_anulacion' => 'No se puede anular este pago.']);
            }

            $pago->update([
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
                'motivo_anulacion' => trim($motivo),
            ]);
            $pagado = $this->unidades($factura->pagosVigentes()->sum('monto'));
            $factura->update([
                'estado' => $pagado === 0 ? 'REGISTRADA' : ($pagado >= $this->unidades($factura->total) ? 'PAGADA' : 'PARCIAL'),
            ]);
        });
    }

    private function unidades(string|float|int $monto): int
    {
        return (int) round((float) $monto * 10000);
    }
}
