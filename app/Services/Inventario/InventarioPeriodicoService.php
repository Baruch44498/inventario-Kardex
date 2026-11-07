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
                    $ids = collect($datos['repisa_ids'] ?? [$datos['repisa_id']])->map(fn ($id) => (int) $id)->unique()->sort()->values();
                    $repisas = Repisa::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

                    if ($repisas->count() !== $ids->count() || $repisas->contains(fn (Repisa $repisa) => ! $repisa->estado)) {
                        throw ValidationException::withMessages([
                            'repisa_ids' => 'Todas las repisas seleccionadas deben estar activas.',
                        ]);
                    }

                    $yaExiste = InventarioPeriodico::query()
                        ->where('estado', 'ABIERTO')
                        ->whereHas('repisas', fn ($query) => $query->whereIn('repisas.id', $ids))
                        ->lockForUpdate()
                        ->first() !== null;

                    if ($yaExiste) {
                        throw ValidationException::withMessages([
                            'repisa_ids' => 'Una de las repisas ya tiene un inventario periódico abierto.',
                        ]);
                    }

                    $inventarios = Inventario::query()
                        ->whereIn('repisa_id', $ids)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($inventarios->isEmpty() && $ids->count() === 1) {
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
                        'repisa_id' => $repisas->first()->id,
                        'fecha_corte' => $fecha,
                        'estado' => 'ABIERTO',
                        'observacion' => $datos['observacion'] ?? null,
                        'total_lineas' => $inventarios->count(),
                        'valor_sistema_soles' => $valorSistema,
                        'abierto_por' => $usuario->id,
                        'abierto_en' => $fecha,
                    ]);

                    $periodico->repisas()->attach($ids->all());

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

                    return $periodico->load(['repisa', 'repisas', 'abiertoPor', 'detalles.producto']);
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

            $repisasVacias = $periodico->repisas()
                ->whereDoesntHave('inventarios')
                ->wherePivotNull('vacia_confirmada_en')
                ->wherePivotNull('hallazgo_en')
                ->exists();
            if ($repisasVacias) {
                throw ValidationException::withMessages([
                    'conteo' => 'Confirma las repisas sin productos registrados antes de cerrar el conteo.',
                ]);
            }

            // A product placed in a selected shelf after opening was never part of the snapshot.
            $repisaIds = $periodico->repisas()->pluck('repisas.id');
            $idsActuales = Inventario::query()->whereIn('repisa_id', $repisaIds)
                ->orderBy('id')->lockForUpdate()->pluck('id');
            if ($idsActuales->diff($detalles->pluck('inventario_id'))->isNotEmpty()
                || $detalles->pluck('inventario_id')->diff($idsActuales)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'conteo' => 'Cambió la lista de productos de las repisas durante el conteo. Anula y vuelve a abrir el conteo.',
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

    public function confirmarRepisaVacia(InventarioPeriodico $periodico, Repisa $repisa, User $usuario): void
    {
        DB::transaction(function () use ($periodico, $repisa, $usuario): void {
            $periodico = InventarioPeriodico::query()->lockForUpdate()->findOrFail($periodico->id);
            $this->validarAbierto($periodico);
            if (! $periodico->repisas()->whereKey($repisa->id)->exists()) {
                throw ValidationException::withMessages(['repisa' => 'La repisa no pertenece a este conteo.']);
            }
            if (Inventario::query()->where('repisa_id', $repisa->id)->exists()) {
                throw ValidationException::withMessages(['repisa' => 'Ya hay productos registrados en esta repisa; revisa el conteo.']);
            }
            $periodico->repisas()->updateExistingPivot($repisa->id, [
                'vacia_confirmada_en' => now(),
                'vacia_confirmada_por' => $usuario->id,
                'hallazgo_descripcion' => null,
                'hallazgo_cantidad' => null,
                'hallazgo_en' => null,
                'hallazgo_por' => null,
            ]);
        });
    }

    public function registrarHallazgo(InventarioPeriodico $periodico, Repisa $repisa, array $datos, User $usuario): void
    {
        DB::transaction(function () use ($periodico, $repisa, $datos, $usuario): void {
            $periodico = InventarioPeriodico::query()->lockForUpdate()->findOrFail($periodico->id);
            $this->validarAbierto($periodico);
            if (! $periodico->repisas()->whereKey($repisa->id)->exists()) {
                throw ValidationException::withMessages(['repisa' => 'La repisa no pertenece a este conteo.']);
            }
            if (Inventario::query()->where('repisa_id', $repisa->id)->exists()) {
                throw ValidationException::withMessages(['repisa' => 'La repisa ya tiene productos registrados; cuenta sus líneas.']);
            }
            $periodico->repisas()->updateExistingPivot($repisa->id, [
                'vacia_confirmada_en' => null,
                'vacia_confirmada_por' => null,
                'hallazgo_descripcion' => $datos['descripcion'],
                'hallazgo_cantidad' => $datos['cantidad'],
                'hallazgo_en' => now(),
                'hallazgo_por' => $usuario->id,
            ]);
        });
    }

    public function corregirRepisa(InventarioPeriodico $periodico, Repisa $repisa): void
    {
        DB::transaction(function () use ($periodico, $repisa): void {
            $periodico = InventarioPeriodico::query()->lockForUpdate()->findOrFail($periodico->id);
            $this->validarAbierto($periodico);
            if (! $periodico->repisas()->whereKey($repisa->id)->exists()) {
                throw ValidationException::withMessages(['repisa' => 'La repisa no pertenece a este conteo.']);
            }
            $periodico->repisas()->updateExistingPivot($repisa->id, [
                'vacia_confirmada_en' => null,
                'vacia_confirmada_por' => null,
                'hallazgo_descripcion' => null,
                'hallazgo_cantidad' => null,
                'hallazgo_en' => null,
                'hallazgo_por' => null,
            ]);
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
