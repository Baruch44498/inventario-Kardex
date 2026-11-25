<?php

namespace App\Services\Contabilidad;

use App\Models\CobroCotizacionCliente;
use App\Models\CotizacionCliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarCobroCotizacionClienteService
{
    /** @param array<string, mixed> $datos */
    public function registrar(CotizacionCliente $cotizacion, array $datos, User $usuario): CobroCotizacionCliente
    {
        return DB::transaction(function () use ($cotizacion, $datos, $usuario): CobroCotizacionCliente {
            $cotizacion = CotizacionCliente::query()->lockForUpdate()->findOrFail($cotizacion->id);
            if (! $cotizacion->puedeRegistrarseCobro()) {
                throw ValidationException::withMessages(['monto' => 'Esta cotización aún no está habilitada para cobro.']);
            }

            $saldo = $this->unidades($cotizacion->total) - $this->unidades($cotizacion->cobrosVigentes()->sum('monto'));
            $monto = $this->unidades($datos['monto']);
            if ($saldo <= 0 || $monto <= 0 || $monto > $saldo) {
                throw ValidationException::withMessages(['monto' => 'El cobro debe ser mayor que cero y no superar el saldo.']);
            }

            return $cotizacion->cobros()->create($datos + ['registrado_por' => $usuario->id]);
        });
    }

    public function anular(CotizacionCliente $cotizacion, CobroCotizacionCliente $cobro, string $motivo, User $usuario): void
    {
        DB::transaction(function () use ($cotizacion, $cobro, $motivo, $usuario): void {
            $cotizacion = CotizacionCliente::query()->lockForUpdate()->findOrFail($cotizacion->id);
            $cobro = $cotizacion->cobros()->whereKey($cobro->id)->firstOrFail();
            if ($cobro->estaAnulado()) {
                throw ValidationException::withMessages(['motivo_anulacion' => 'El cobro ya está anulado.']);
            }
            $cobro->update([
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
                'motivo_anulacion' => trim($motivo),
            ]);
        });
    }

    private function unidades(string|float|int $monto): int
    {
        return (int) round((float) $monto * 10000);
    }
}
