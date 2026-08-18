<?php

namespace App\Services\Ventas;

use App\Models\CotizacionCliente;
use App\Models\CotizacionPresupuesto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PresupuestoCotizacionService
{
    public function registrar(
        CotizacionCliente $cotizacion,
        array $datos,
        User $usuario
    ): CotizacionPresupuesto {
        return DB::transaction(function () use ($cotizacion, $datos, $usuario): CotizacionPresupuesto {
            $cotizacion = CotizacionCliente::query()
                ->lockForUpdate()
                ->findOrFail($cotizacion->id);
            $this->validarEditable($cotizacion);

            return $cotizacion->presupuestos()->create([
                ...$this->prepararLinea($datos),
                'estado' => 'VIGENTE',
                'registrado_por' => $usuario->id,
                'registrado_en' => now(),
            ]);
        });
    }

    public function actualizar(
        CotizacionPresupuesto $presupuesto,
        array $datos,
        User $usuario
    ): CotizacionPresupuesto {
        return DB::transaction(function () use ($presupuesto, $datos, $usuario): CotizacionPresupuesto {
            $presupuesto = CotizacionPresupuesto::query()
                ->with('cotizacionCliente')
                ->lockForUpdate()
                ->findOrFail($presupuesto->id);
            $this->validarEditable($presupuesto->cotizacionCliente);

            if (! $presupuesto->estaVigente()) {
                throw ValidationException::withMessages([
                    'tipo_costo' => 'Una partida anulada no puede modificarse.',
                ]);
            }

            $presupuesto->update([
                ...$this->prepararLinea($datos),
                'actualizado_por' => $usuario->id,
            ]);

            return $presupuesto->fresh();
        });
    }

    public function anular(
        CotizacionPresupuesto $presupuesto,
        string $motivo,
        User $usuario
    ): CotizacionPresupuesto {
        return DB::transaction(function () use ($presupuesto, $motivo, $usuario): CotizacionPresupuesto {
            $presupuesto = CotizacionPresupuesto::query()
                ->with('cotizacionCliente')
                ->lockForUpdate()
                ->findOrFail($presupuesto->id);
            $this->validarEditable($presupuesto->cotizacionCliente);

            if (! $presupuesto->estaVigente()) {
                throw ValidationException::withMessages([
                    'motivo_anulacion' => 'Esta partida ya se encuentra anulada.',
                ]);
            }

            $presupuesto->update([
                'estado' => 'ANULADO',
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
                'motivo_anulacion' => trim($motivo),
            ]);

            return $presupuesto->fresh();
        });
    }

    public function resumen(Collection $partidas): array
    {
        $vigentes = $partidas->where('estado', 'VIGENTE');
        $porTipo = collect(CotizacionPresupuesto::TIPOS)
            ->mapWithKeys(function (string $nombre, string $tipo) use ($vigentes): array {
                $lineas = $vigentes->where('tipo_costo', $tipo);

                return [$tipo => [
                    'nombre' => $nombre,
                    'lineas' => $lineas->count(),
                    'neto_soles' => round((float) $lineas->sum('costo_neto_soles'), 4),
                    'igv_soles' => round((float) $lineas->sum('igv_soles'), 4),
                    'total_soles' => round((float) $lineas->sum('costo_total_soles'), 4),
                    'neto_dolares' => round((float) $lineas->sum('costo_neto_dolares'), 4),
                    'igv_dolares' => round((float) $lineas->sum('igv_dolares'), 4),
                    'total_dolares' => round((float) $lineas->sum('costo_total_dolares'), 4),
                ]];
            });

        return [
            'lineas_vigentes' => $vigentes->count(),
            'lineas_anuladas' => $partidas->where('estado', 'ANULADO')->count(),
            'por_tipo' => $porTipo,
            'neto_soles' => round((float) $vigentes->sum('costo_neto_soles'), 4),
            'igv_soles' => round((float) $vigentes->sum('igv_soles'), 4),
            'total_soles' => round((float) $vigentes->sum('costo_total_soles'), 4),
            'neto_dolares' => round((float) $vigentes->sum('costo_neto_dolares'), 4),
            'igv_dolares' => round((float) $vigentes->sum('igv_dolares'), 4),
            'total_dolares' => round((float) $vigentes->sum('costo_total_dolares'), 4),
        ];
    }

    public function calcular(array $datos): array
    {
        $cantidad = round((float) $datos['cantidad'], 3);
        $unitario = round((float) $datos['costo_unitario'], 4);
        $tipoCambio = round((float) $datos['tipo_cambio'], 6);
        $cargaPorcentaje = $datos['tipo_costo'] === 'MANO_OBRA'
            ? round((float) ($datos['carga_social_porcentaje'] ?? 0), 4)
            : 0.0;
        $igvPorcentaje = round((float) $datos['igv_porcentaje'], 4);

        $base = round($cantidad * $unitario, 4);
        $carga = round($base * $cargaPorcentaje / 100, 4);
        $baseConCarga = round($base + $carga, 4);

        if ($datos['igv_modo'] === 'INCLUIDO' && $igvPorcentaje > 0) {
            $totalOriginal = $baseConCarga;
            $netoOriginal = round($totalOriginal / (1 + $igvPorcentaje / 100), 4);
            $igvOriginal = round($totalOriginal - $netoOriginal, 4);
        } elseif ($datos['igv_modo'] === 'AGREGAR') {
            $netoOriginal = $baseConCarga;
            $igvOriginal = round($netoOriginal * $igvPorcentaje / 100, 4);
            $totalOriginal = round($netoOriginal + $igvOriginal, 4);
        } else {
            $netoOriginal = $baseConCarga;
            $igvOriginal = 0.0;
            $totalOriginal = $baseConCarga;
        }

        $aSoles = fn(float $importe): float => round(
            $datos['moneda'] === 'USD' ? $importe * $tipoCambio : $importe,
            4
        );
        $aDolares = fn(float $importe): float => round(
            $datos['moneda'] === 'PEN' ? $importe / $tipoCambio : $importe,
            4
        );

        return [
            'cantidad' => $cantidad,
            'tipo_cambio' => $tipoCambio,
            'costo_unitario' => $unitario,
            'carga_social_porcentaje' => $cargaPorcentaje,
            'carga_social_original' => $carga,
            'igv_porcentaje' => $igvPorcentaje,
            'costo_neto_original' => $netoOriginal,
            'igv_original' => $igvOriginal,
            'costo_total_original' => $totalOriginal,
            'costo_neto_soles' => $aSoles($netoOriginal),
            'igv_soles' => $aSoles($igvOriginal),
            'costo_total_soles' => $aSoles($totalOriginal),
            'costo_neto_dolares' => $aDolares($netoOriginal),
            'igv_dolares' => $aDolares($igvOriginal),
            'costo_total_dolares' => $aDolares($totalOriginal),
        ];
    }

    private function prepararLinea(array $datos): array
    {
        return [
            'producto_id' => $datos['tipo_costo'] === 'MATERIAL'
                ? ($datos['producto_id'] ?? null)
                : null,
            'tipo_costo' => $datos['tipo_costo'],
            'descripcion' => trim($datos['descripcion']),
            'unidad' => $datos['unidad'],
            'moneda' => $datos['moneda'],
            'igv_modo' => $datos['igv_modo'],
            'observacion' => filled($datos['observacion'] ?? null)
                ? trim($datos['observacion'])
                : null,
            ...$this->calcular($datos),
        ];
    }

    private function validarEditable(CotizacionCliente $cotizacion): void
    {
        if (! $cotizacion->esEditable()) {
            throw ValidationException::withMessages([
                'tipo_costo' => 'El presupuesto solo puede modificarse mientras la cotización esté abierta.',
            ]);
        }
    }
}
