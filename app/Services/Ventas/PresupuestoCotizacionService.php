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
            $datos['componente_id'] = $this->resolverComponente(
                $cotizacion,
                $datos['componente_id'] ?? null
            );

            $presupuesto = $cotizacion->presupuestos()->create([
                ...$this->prepararLinea($datos),
                'estado' => 'VIGENTE',
                'registrado_por' => $usuario->id,
                'registrado_en' => now(),
            ]);

            $this->marcarCotizacionPendiente($cotizacion);

            return $presupuesto;
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
            $datos['componente_id'] = $this->resolverComponente(
                $presupuesto->cotizacionCliente,
                $datos['componente_id'] ?? null
            );

            if (! $presupuesto->estaVigente()) {
                throw ValidationException::withMessages([
                    'tipo_costo' => 'Una partida anulada no puede modificarse.',
                ]);
            }

            $presupuesto->update([
                ...$this->prepararLinea($datos),
                'actualizado_por' => $usuario->id,
            ]);

            $this->marcarCotizacionPendiente($presupuesto->cotizacionCliente);

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

            $this->marcarCotizacionPendiente($presupuesto->cotizacionCliente);

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
                    'venta_neta_soles' => round((float) $lineas->sum('precio_venta_neto_soles'), 4),
                    'igv_venta_soles' => round((float) $lineas->sum('igv_venta_soles'), 4),
                    'venta_total_soles' => round((float) $lineas->sum('precio_venta_total_soles'), 4),
                    'utilidad_soles' => round((float) $lineas->sum('utilidad_estimada_soles'), 4),
                    'igv_por_pagar_soles' => round((float) $lineas->sum('igv_por_pagar_soles'), 4),
                    'venta_neta_dolares' => round((float) $lineas->sum('precio_venta_neto_dolares'), 4),
                    'venta_total_dolares' => round((float) $lineas->sum('precio_venta_total_dolares'), 4),
                    'utilidad_dolares' => round((float) $lineas->sum('utilidad_estimada_dolares'), 4),
                ]];
            });

        $porComponente = $vigentes
            ->groupBy(fn(CotizacionPresupuesto $partida): int => (int) ($partida->componente_id ?: 0))
            ->map(function (Collection $lineas, int $componenteId): array {
                $componente = $lineas->first()?->componente;

                return [
                    'componente_id' => $componenteId ?: null,
                    'codigo' => $componente?->nombreVisible() ?: 'General',
                    'descripcion' => $componente?->descripcion_componente ?: 'Presupuesto general',
                    'tipo_orden' => $componente?->tipoOrden?->codigo,
                    'lineas' => $lineas->count(),
                    'costo_neto_soles' => round((float) $lineas->sum('costo_neto_soles'), 4),
                    'costo_total_soles' => round((float) $lineas->sum('costo_total_soles'), 4),
                    'venta_neta_soles' => round((float) $lineas->sum('precio_venta_neto_soles'), 4),
                    'venta_total_soles' => round((float) $lineas->sum('precio_venta_total_soles'), 4),
                    'utilidad_soles' => round((float) $lineas->sum('utilidad_estimada_soles'), 4),
                    'igv_por_pagar_soles' => round((float) $lineas->sum('igv_por_pagar_soles'), 4),
                    'costo_neto_dolares' => round((float) $lineas->sum('costo_neto_dolares'), 4),
                    'venta_neta_dolares' => round((float) $lineas->sum('precio_venta_neto_dolares'), 4),
                    'utilidad_dolares' => round((float) $lineas->sum('utilidad_estimada_dolares'), 4),
                ];
            })
            ->values();

        return [
            'lineas_vigentes' => $vigentes->count(),
            'lineas_anuladas' => $partidas->where('estado', 'ANULADO')->count(),
            'por_tipo' => $porTipo,
            'por_componente' => $porComponente,
            'neto_soles' => round((float) $vigentes->sum('costo_neto_soles'), 4),
            'igv_soles' => round((float) $vigentes->sum('igv_soles'), 4),
            'total_soles' => round((float) $vigentes->sum('costo_total_soles'), 4),
            'neto_dolares' => round((float) $vigentes->sum('costo_neto_dolares'), 4),
            'igv_dolares' => round((float) $vigentes->sum('igv_dolares'), 4),
            'total_dolares' => round((float) $vigentes->sum('costo_total_dolares'), 4),
            'venta_neta_soles' => round((float) $vigentes->sum('precio_venta_neto_soles'), 4),
            'igv_venta_soles' => round((float) $vigentes->sum('igv_venta_soles'), 4),
            'venta_total_soles' => round((float) $vigentes->sum('precio_venta_total_soles'), 4),
            'utilidad_soles' => round((float) $vigentes->sum('utilidad_estimada_soles'), 4),
            'igv_por_pagar_soles' => round((float) $vigentes->sum('igv_por_pagar_soles'), 4),
            'venta_neta_dolares' => round((float) $vigentes->sum('precio_venta_neto_dolares'), 4),
            'venta_total_dolares' => round((float) $vigentes->sum('precio_venta_total_dolares'), 4),
            'utilidad_dolares' => round((float) $vigentes->sum('utilidad_estimada_dolares'), 4),
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
        $margenPorcentaje = round((float) ($datos['margen_porcentaje'] ?? 0), 4);
        $igvVentaPorcentaje = round((float) ($datos['igv_venta_porcentaje'] ?? 18), 4);

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

        $ventaNetaOriginal = round(
            $netoOriginal * (1 + $margenPorcentaje / 100),
            4
        );
        $igvVentaOriginal = round(
            $ventaNetaOriginal * $igvVentaPorcentaje / 100,
            4
        );
        $ventaTotalOriginal = round($ventaNetaOriginal + $igvVentaOriginal, 4);
        $utilidadOriginal = round($ventaNetaOriginal - $netoOriginal, 4);
        $igvPorPagarOriginal = round($igvVentaOriginal - $igvOriginal, 4);

        return [
            'cantidad' => $cantidad,
            'tipo_cambio' => $tipoCambio,
            'costo_unitario' => $unitario,
            'margen_porcentaje' => $margenPorcentaje,
            'carga_social_porcentaje' => $cargaPorcentaje,
            'carga_social_original' => $carga,
            'igv_porcentaje' => $igvPorcentaje,
            'igv_venta_porcentaje' => $igvVentaPorcentaje,
            'costo_neto_original' => $netoOriginal,
            'igv_original' => $igvOriginal,
            'costo_total_original' => $totalOriginal,
            'costo_neto_soles' => $aSoles($netoOriginal),
            'igv_soles' => $aSoles($igvOriginal),
            'costo_total_soles' => $aSoles($totalOriginal),
            'costo_neto_dolares' => $aDolares($netoOriginal),
            'igv_dolares' => $aDolares($igvOriginal),
            'costo_total_dolares' => $aDolares($totalOriginal),
            'precio_venta_neto_original' => $ventaNetaOriginal,
            'igv_venta_original' => $igvVentaOriginal,
            'precio_venta_total_original' => $ventaTotalOriginal,
            'utilidad_estimada_original' => $utilidadOriginal,
            'igv_por_pagar_original' => $igvPorPagarOriginal,
            'precio_venta_neto_soles' => $aSoles($ventaNetaOriginal),
            'igv_venta_soles' => $aSoles($igvVentaOriginal),
            'precio_venta_total_soles' => $aSoles($ventaTotalOriginal),
            'utilidad_estimada_soles' => $aSoles($utilidadOriginal),
            'igv_por_pagar_soles' => $aSoles($igvPorPagarOriginal),
            'precio_venta_neto_dolares' => $aDolares($ventaNetaOriginal),
            'igv_venta_dolares' => $aDolares($igvVentaOriginal),
            'precio_venta_total_dolares' => $aDolares($ventaTotalOriginal),
            'utilidad_estimada_dolares' => $aDolares($utilidadOriginal),
            'igv_por_pagar_dolares' => $aDolares($igvPorPagarOriginal),
        ];
    }

    private function prepararLinea(array $datos): array
    {
        return [
            'componente_id' => $datos['componente_id'] ?? null,
            'producto_id' => $datos['tipo_costo'] === 'MATERIAL'
                ? ($datos['producto_id'] ?? null)
                : null,
            'tipo_costo' => $datos['tipo_costo'],
            'grupo_costo' => filled($datos['grupo_costo'] ?? null)
                ? trim($datos['grupo_costo'])
                : null,
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

    private function marcarCotizacionPendiente(CotizacionCliente $cotizacion): void
    {
        if ($cotizacion->costeo_sincronizado_en !== null) {
            $cotizacion->update(['costeo_sincronizado_en' => null]);
        }
    }

    private function resolverComponente(
        CotizacionCliente $cotizacion,
        mixed $componenteId
    ): ?int {
        if ($cotizacion->proforma_id !== null) {
            return null;
        }

        if (
            $componenteId
            && $cotizacion->componentes()->whereKey((int) $componenteId)->exists()
        ) {
            return (int) $componenteId;
        }

        if (! $componenteId && $cotizacion->componentes()->count() === 1) {
            return (int) $cotizacion->componentes()->value('id');
        }

        if (
            ! $componenteId && $cotizacion->componentes()->doesntExist()
            && $cotizacion->tipo_orden_id
        ) {
            return $cotizacion->componentes()->create([
                'tipo_orden_id' => $cotizacion->tipo_orden_id,
                'descripcion_componente' => $cotizacion->descripcion_trabajo
                    ?: 'Trabajo de ' . $cotizacion->codigo,
                'cliente_direccion_id' => $cotizacion->cliente_direccion_id,
                'vehiculo_id' => $cotizacion->vehiculo_id,
                'tipo_cambio_comparacion' => $cotizacion->tipo_cambio,
                'orden_secuencia' => 1,
            ])->id;
        }

        throw ValidationException::withMessages([
            'componente_id' => 'Selecciona un componente de esta cotización.',
        ]);
    }
}
