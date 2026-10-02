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

            $saldo = $this->unidades($factura->total) - $this->unidades($factura->pagosVigentes()->sum('monto'));
            $monto = $this->unidades($datos['monto']);
            if ($saldo <= 0 || $monto <= 0 || $monto > $saldo) {
                throw ValidationException::withMessages(['monto' => 'El pago debe ser mayor que cero y no superar el saldo de la factura.']);
            }

            $pago = $factura->pagos()->create($datos + ['registrado_por' => $usuario->id]);
            $factura->update(['estado' => $monto === $saldo ? 'PAGADA' : 'PARCIAL']);

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
