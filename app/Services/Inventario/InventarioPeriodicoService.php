<?php

namespace App\Services\Inventario;

use App\Models\Inventario;
use App\Models\InventarioPeriodico;
use App\Models\MovimientoInventario;
use App\Models\Repisa;
use App\Models\User;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioPeriodicoService
{
    public function __construct(
        private GenerarCodigoDocumentoService $codigos
    ) {}

    public function abrir(array $datos, User $usuario): InventarioPeriodico
    {
        $fecha = now();

        return $this->codigos->usarSiguiente(
            'inventarios_periodicos',
            'IP',
            $fecha->toDateString(),
            function (string $codigo) use ($datos, $usuario, $fecha): InventarioPeriodico {
                return DB::transaction(function () use ($datos, $usuario, $fecha, $codigo): InventarioPeriodico {
                    $repisa = Repisa::query()
                        ->lockForUpdate()
                        ->findOrFail($datos['repisa_id']);

                    if (! $repisa->estado) {
                        throw ValidationException::withMessages([
                            'repisa_id' => 'La repisa seleccionada está inactiva.',
                        ]);
                    }

                    $yaExiste = InventarioPeriodico::query()
                        ->where('repisa_id', $repisa->id)
                        ->where('estado', 'ABIERTO')
                        ->lockForUpdate()
                        ->first() !== null;

                    if ($yaExiste) {
                        throw ValidationException::withMessages([
                            'repisa_id' => 'Esta repisa ya tiene un inventario periódico abierto.',
                        ]);
                    }

                    $inventarios = Inventario::query()
                        ->where('repisa_id', $repisa->id)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($inventarios->isEmpty()) {
                        throw ValidationException::withMessages([
                            'repisa_id' => 'La repisa no tiene productos registrados para contar.',
                        ]);
                    }

                    $movimientosCorte = $this->ultimosMovimientos($inventarios->pluck('id'));
                    $valorSistema = round((float) $inventarios->sum(
                        fn(Inventario $inventario): float =>
                            (float) $inventario->stock_actual
                            * (float) $inventario->costo_promedio_soles
                    ), 4);

                    $periodico = InventarioPeriodico::query()->create([
                        'codigo' => $codigo,
                        'repisa_id' => $repisa->id,
                        'fecha_corte' => $fecha,
                        'estado' => 'ABIERTO',
                        'observacion' => $datos['observacion'] ?? null,
                        'total_lineas' => $inventarios->count(),
                        'valor_sistema_soles' => $valorSistema,
                        'abierto_por' => $usuario->id,
                        'abierto_en' => $fecha,
                    ]);

                    foreach ($inventarios as $inventario) {
                        $stock = round((float) $inventario->stock_actual, 3);
                        $costo = round((float) $inventario->costo_promedio_soles, 4);

                        $periodico->detalles()->create([
                            'inventario_id' => $inventario->id,
                            'producto_id' => $inventario->producto_id,
                            'repisa_id' => $inventario->repisa_id,
                            'stock_sistema' => $stock,
                            'costo_promedio_soles' => $costo,
                            'valor_sistema_soles' => round($stock * $costo, 4),
                            'movimiento_id_corte' => $movimientosCorte->get($inventario->id),
                        ]);
                    }

                    return $periodico->load(['repisa', 'abiertoPor', 'detalles.producto']);
                });
            }
        );
    }

    public function guardarConteo(
        InventarioPeriodico $periodico,
        array $detalles,
        User $usuario
    ): InventarioPeriodico {
        return DB::transaction(function () use ($periodico, $detalles, $usuario): InventarioPeriodico {
            $periodico = InventarioPeriodico::query()
                ->lockForUpdate()
                ->findOrFail($periodico->id);

            $this->validarAbierto($periodico);

            foreach ($detalles as $detalleId => $datos) {
                $detalle = $periodico->detalles()
                    ->whereKey($detalleId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $valorIngresado = $datos['stock_contado'] ?? null;
                $stockContado = $valorIngresado === null || $valorIngresado === ''
                    ? null
                    : round((float) $valorIngresado, 3);
                $diferencia = $stockContado === null
                    ? 0.0
                    : round($stockContado - (float) $detalle->stock_sistema, 3);

                $detalle->update([
                    'stock_contado' => $stockContado,
                    'diferencia' => $diferencia,
                    'valor_diferencia_soles' => round(
                        $diferencia * (float) $detalle->costo_promedio_soles,
                        4
                    ),
                    'observacion' => $datos['observacion'] ?? null,
                    'contado_por' => $stockContado === null ? null : $usuario->id,
                    'contado_en' => $stockContado === null ? null : now(),
                ]);
            }

            $this->actualizarResumen($periodico);

            return $periodico->fresh([
                'repisa',
                'abiertoPor',
                'detalles.producto.unidadMedida',
                'detalles.contadoPor',
            ]);
        });
    }

    public function cerrar(InventarioPeriodico $periodico, User $usuario): InventarioPeriodico
    {
        return DB::transaction(function () use ($periodico, $usuario): InventarioPeriodico {
            $periodico = InventarioPeriodico::query()
                ->lockForUpdate()
                ->findOrFail($periodico->id);

            $this->validarAbierto($periodico);

            $detalles = $periodico->detalles()
                ->with('producto')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($detalles->contains(fn($detalle): bool => $detalle->stock_contado === null)) {
                throw ValidationException::withMessages([
                    'conteo' => 'Debes registrar el conteo físico de todos los productos antes de cerrar.',
                ]);
            }

            $inventarios = Inventario::query()
                ->whereIn('id', $detalles->pluck('inventario_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $movimientosActuales = $this->ultimosMovimientos($detalles->pluck('inventario_id'));

            $desactualizados = $detalles->filter(function ($detalle) use (
                $inventarios,
                $movimientosActuales
            ): bool {
                $inventario = $inventarios->get($detalle->inventario_id);

                return ! $inventario
                    || (int) ($movimientosActuales->get($detalle->inventario_id) ?? 0)
                        !== (int) ($detalle->movimiento_id_corte ?? 0)
                    || abs((float) $inventario->stock_actual - (float) $detalle->stock_sistema) >= 0.0005
                    || abs((float) $inventario->costo_promedio_soles - (float) $detalle->costo_promedio_soles) >= 0.00005;
            });

            if ($desactualizados->isNotEmpty()) {
                $productos = $desactualizados
                    ->take(3)
                    ->map(fn($detalle): string => $detalle->producto?->codigo ?? "#{$detalle->producto_id}")
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'conteo' => "Hubo movimientos después de abrir el conteo ({$productos}). Anula este conteo y abre uno nuevo para evitar un ajuste incorrecto.",
                ]);
            }

            foreach ($detalles as $detalle) {
                $inventario = $inventarios->get($detalle->inventario_id);
                if (! $inventario) {
                    throw ValidationException::withMessages([
                        'conteo' => 'Uno de los inventarios del conteo ya no está disponible.',
                    ]);
                }

                $stockAnterior = round((float) $inventario->stock_actual, 3);
                $stockContado = round((float) $detalle->stock_contado, 3);
                $diferencia = round($stockContado - $stockAnterior, 3);
                $costo = round((float) $inventario->costo_promedio_soles, 4);

                $detalle->update([
                    'diferencia' => $diferencia,
                    'valor_diferencia_soles' => round($diferencia * $costo, 4),
                ]);

                if (abs($diferencia) < 0.0005) {
                    continue;
                }

                $inventario->update(['stock_actual' => $stockContado]);

                MovimientoInventario::query()->create([
                    'inventario_id' => $inventario->id,
                    'producto_id' => $inventario->producto_id,
                    'repisa_id' => $inventario->repisa_id,
                    'tipo_movimiento' => $diferencia > 0 ? 'ENTRADA' : 'SALIDA',
                    'motivo' => 'AJUSTE_INVENTARIO',
                    'origen_tipo' => 'INVENTARIO_PERIODICO',
                    'origen_id' => $periodico->id,
                    'origen_detalle_id' => $detalle->id,
                    'cantidad' => abs($diferencia),
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockContado,
                    'costo_unitario' => $costo,
                    'costo_promedio_anterior' => $costo,
                    'costo_promedio_nuevo' => $costo,
                    'fecha_movimiento' => now(),
                    'observacion' => $detalle->observacion
                        ?: "Ajuste por cierre del inventario {$periodico->codigo}.",
                    'registrado_por' => $usuario->id,
                ]);

                app(EvaluarAlertasStockService::class)
                    ->evaluarInventario($inventario->refresh(), $usuario);
            }

            $this->actualizarResumen($periodico);
            $periodico->update([
                'estado' => 'CERRADO',
                'cerrado_por' => $usuario->id,
                'cerrado_en' => now(),
            ]);

            return $periodico->fresh([
                'repisa',
                'abiertoPor',
                'cerradoPor',
                'detalles.producto.unidadMedida',
                'detalles.contadoPor',
            ]);
        });
    }

    public function anular(
        InventarioPeriodico $periodico,
        string $motivo,
        User $usuario
    ): InventarioPeriodico {
        return DB::transaction(function () use ($periodico, $motivo, $usuario): InventarioPeriodico {
            $periodico = InventarioPeriodico::query()
                ->lockForUpdate()
                ->findOrFail($periodico->id);

            $this->validarAbierto($periodico);
            $periodico->update([
                'estado' => 'ANULADO',
                'motivo_anulacion' => $motivo,
                'anulado_por' => $usuario->id,
                'anulado_en' => now(),
            ]);

            return $periodico->fresh(['repisa', 'abiertoPor', 'anuladoPor']);
        });
    }

    private function validarAbierto(InventarioPeriodico $periodico): void
    {
        if (! $periodico->estaAbierto()) {
            throw ValidationException::withMessages([
                'inventario_periodico' => 'Este inventario periódico ya no está abierto.',
            ]);
        }
    }

    private function actualizarResumen(InventarioPeriodico $periodico): void
    {
        $resumen = $periodico->detalles()
            ->selectRaw('COUNT(*) as total_lineas')
            ->selectRaw(
                'SUM(CASE WHEN stock_contado IS NOT NULL AND ABS(diferencia) >= 0.0005 THEN 1 ELSE 0 END) as lineas_con_diferencia'
            )
            ->selectRaw('COALESCE(SUM(valor_diferencia_soles), 0) as valor_diferencia_soles')
            ->first();

        $periodico->update([
            'total_lineas' => (int) ($resumen->total_lineas ?? 0),
            'lineas_con_diferencia' => (int) ($resumen->lineas_con_diferencia ?? 0),
            'valor_diferencia_soles' => round(
                (float) ($resumen->valor_diferencia_soles ?? 0),
                4
            ),
        ]);
    }

    private function ultimosMovimientos(Collection $inventarioIds): Collection
    {
        if ($inventarioIds->isEmpty()) {
            return collect();
        }

        return MovimientoInventario::query()
            ->whereIn('inventario_id', $inventarioIds)
            ->groupBy('inventario_id')
            ->selectRaw('inventario_id, MAX(id) as ultimo_id')
            ->pluck('ultimo_id', 'inventario_id');
    }
}
