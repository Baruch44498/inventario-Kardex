<?php

namespace App\Services\Inventario;

use App\Models\Inventario;
use App\Models\InventarioPeriodico;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RegistrarOperacionAlmacenService
{
    public function __construct(private EvaluarAlertasStockService $alertas) {}

    public function registrar(Producto $producto, array $datos, User $usuario): int
    {
        $origenId = $datos['tipo'] === 'TRASLADO' ? (int) $datos['origen_id'] : null;
        $destinoId = (int) $datos['destino_id'];
        $cantidad = (float) $datos['cantidad'];
        $repisasIds = $origenId === null ? [$destinoId] : [$origenId, $destinoId];
        $costoInicial = isset($datos['costo_inicial']) ? (float) $datos['costo_inicial'] : null;

        return DB::transaction(function () use (
            $producto, $datos, $usuario, $origenId, $destinoId, $repisasIds, $cantidad, $costoInicial
        ): int {
            $repisas = Repisa::query()->whereKey($repisasIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $producto = Producto::query()->lockForUpdate()->findOrFail($producto->id);
            $previa = DB::table('operaciones_almacen')->where('solicitud', $datos['solicitud'])->lockForUpdate()->first();

            if ($previa) {
                if ((int) $previa->registrado_por !== $usuario->id || (int) $previa->producto_id !== $producto->id
                    || $previa->tipo !== $datos['tipo'] || (int) $previa->origen_id !== (int) $origenId
                    || (int) $previa->destino_id !== $destinoId || (float) $previa->cantidad !== $cantidad
                    || $previa->observacion !== $datos['observacion']
                    || ($origenId === null && ($previa->costo_unitario_soles === null ? null : (float) $previa->costo_unitario_soles) !== $costoInicial)) {
                    $this->rechazar('solicitud', 'Esta solicitud ya fue utilizada. Vuelve a abrir el formulario.');
                }

                return (int) $previa->id;
            }

            if (! $producto->estado || ! $producto->cantidadAdmitida($cantidad)) {
                $this->rechazar('cantidad', 'Verifica que el producto esté activo y que su unidad admita la cantidad indicada.');
            }

            foreach (['origen_id' => $origenId, 'destino_id' => $destinoId] as $campo => $id) {
                if ($id !== null && ! $repisas->get($id)?->estado) {
                    $this->rechazar($campo, 'Selecciona una repisa activa.');
                }
            }

            $conteos = InventarioPeriodico::query()->where('estado', 'ABIERTO')
                ->where(function ($query) use ($repisasIds): void {
                    $query->whereIn('repisa_id', $repisasIds);
                    if (Schema::hasTable('inventario_periodico_repisas')) {
                        $query->orWhereExists(fn ($repisas) => $repisas->selectRaw('1')
                            ->from('inventario_periodico_repisas')
                            ->whereColumn('inventario_periodico_id', 'inventarios_periodicos.id')
                            ->whereIn('repisa_id', $repisasIds));
                    }
                });

            if ($conteos->lockForUpdate()->first(['id'])) {
                $this->rechazar('destino_id', 'Hay un conteo físico abierto en una de las repisas. Termínalo antes de registrar stock.');
            }

            $existencias = Inventario::query()->whereIn('repisa_id', $repisasIds)->orderBy('id')->lockForUpdate()->get();
            $propias = $existencias->where('producto_id', $producto->id)->keyBy('repisa_id');
            $origen = $origenId === null ? null : $propias->get($origenId);
            $destino = $propias->get($destinoId);

            if ($origenId !== null && (! $origen || (float) $origen->stock_actual < $cantidad)) {
                $this->rechazar('cantidad', 'La cantidad supera el stock disponible en la repisa de origen.');
            }
            if ($origenId === null && $destino) {
                $this->rechazar('destino_id', 'El producto ya está registrado en esta repisa. Usa el conteo físico para revisar su saldo.');
            }
            if (empty($datos['confirmar_compartida']) && $existencias->contains(
                fn (Inventario $fila): bool => (int) $fila->repisa_id === $destinoId
                    && (int) $fila->producto_id !== $producto->id && (float) $fila->stock_actual > 0
            )) {
                $this->rechazar('confirmar_compartida', 'La repisa de destino contiene otro producto. Confirma si la repisa será compartida.');
            }

            $saldoDestino = (float) ($destino?->stock_actual ?? 0);
            $nuevoSaldo = round($saldoDestino + $cantidad, 3);
            if ($nuevoSaldo > 99999999999.999) {
                $this->rechazar('cantidad', 'El saldo de la repisa de destino supera el límite permitido.');
            }

            $costoAnterior = (float) ($destino?->costo_promedio_soles ?? 0);
            $costoUnitario = $origen ? (float) $origen->costo_promedio_soles : ($costoInicial ?? 0.0);
            $nuevoCosto = round(($saldoDestino * $costoAnterior + $cantidad * $costoUnitario) / $nuevoSaldo, 4);
            $fecha = now();
            $operacionId = DB::table('operaciones_almacen')->insertGetId([
                'solicitud' => $datos['solicitud'],
                'tipo' => $datos['tipo'],
                'producto_id' => $producto->id,
                'origen_id' => $origenId,
                'destino_id' => $destinoId,
                'cantidad' => $cantidad,
                'costo_unitario_soles' => $origen ? $costoUnitario : $costoInicial,
                'observacion' => $datos['observacion'],
                'registrado_por' => $usuario->id,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);

            $destino ??= Inventario::query()->create(['producto_id' => $producto->id, 'repisa_id' => $destinoId]);
            $motivo = $origen ? 'TRASLADO_INTERNO' : 'SALDO_INICIAL';
            $comunes = [
                'producto_id' => $producto->id,
                'motivo' => $motivo,
                'origen_tipo' => 'REGISTRO_ALMACEN',
                'origen_id' => $operacionId,
                'cantidad' => $cantidad,
                'costo_unitario' => $costoUnitario,
                'fecha_movimiento' => $fecha,
                'observacion' => $datos['observacion'],
                'registrado_por' => $usuario->id,
            ];

            if ($origen) {
                $saldoOrigen = (float) $origen->stock_actual;
                $nuevoOrigen = round($saldoOrigen - $cantidad, 3);
                $origen->update(['stock_actual' => $nuevoOrigen]);
                MovimientoInventario::query()->create($comunes + [
                    'inventario_id' => $origen->id,
                    'repisa_id' => $origen->repisa_id,
                    'tipo_movimiento' => 'SALIDA',
                    'stock_anterior' => $saldoOrigen,
                    'stock_posterior' => $nuevoOrigen,
                    'costo_promedio_anterior' => $costoUnitario,
                    'costo_promedio_nuevo' => $costoUnitario,
                ]);
                $this->alertas->evaluarInventario($origen, $usuario);
            }

            $destino->update(['stock_actual' => $nuevoSaldo, 'costo_promedio_soles' => $nuevoCosto]);
            MovimientoInventario::query()->create($comunes + [
                'inventario_id' => $destino->id,
                'repisa_id' => $destino->repisa_id,
                'tipo_movimiento' => 'ENTRADA',
                'stock_anterior' => $saldoDestino,
                'stock_posterior' => $nuevoSaldo,
                'costo_promedio_anterior' => $costoAnterior,
                'costo_promedio_nuevo' => $nuevoCosto,
            ]);
            $this->alertas->evaluarInventario($destino, $usuario);

            return $operacionId;
        }, 3);
    }

    private function rechazar(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
