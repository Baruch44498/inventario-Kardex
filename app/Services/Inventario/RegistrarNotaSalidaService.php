<?php

namespace App\Services\Inventario;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Models\User;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarNotaSalidaService
{
    public function __construct(
        private GenerarCodigoDocumentoService $codigos,
        private ReservaMaterialService $reservas
    ) {}

    public function registrarYConfirmar(array $datos, User $usuario): NotaSalida
    {
        return $this->codigos->usarSiguiente(
            'notas_salida',
            'NS',
            $datos['fecha_salida'],
            function (string $codigo) use ($datos, $usuario): NotaSalida {
                return DB::transaction(function () use ($datos, $usuario, $codigo): NotaSalida {
                    $motivo = $datos['motivo_salida'];
                    $orden = null;
                    $proforma = null;

                    if ($motivo === 'ORDEN_OPERACION') {
                        $orden = OrdenOperacion::query()
                            ->lockForUpdate()
                            ->findOrFail($datos['orden_operacion_id']);

                        if (in_array($orden->estado, ['ANULADA', 'CERRADA'], true)) {
                            throw ValidationException::withMessages([
                                'orden_operacion_id' =>
                                'No se pueden registrar salidas para una orden anulada o cerrada.',
                            ]);
                        }
                    }

                    if ($motivo === 'PROFORMA') {
                        $proforma = Proforma::query()
                            ->lockForUpdate()
                            ->findOrFail($datos['proforma_id']);

                        if ($proforma->estado === 'ANULADA') {
                            throw ValidationException::withMessages([
                                'proforma_id' => 'No se puede despachar una Proforma anulada.',
                            ]);
                        }
                    }

                    if (empty($datos['detalles'])) {
                        throw ValidationException::withMessages([
                            'detalles' => 'La nota de salida debe contener al menos un detalle.',
                        ]);
                    }

                    $nota = NotaSalida::create([
                        'orden_operacion_id' => $orden?->id,
                        'motivo_salida' => $motivo,
                        'proforma_id' => $proforma?->id,
                        'codigo' => $codigo,
                        'fecha_salida' => $datos['fecha_salida'],
                        'entregado_a' => $datos['entregado_a'] ?? null,
                        'observacion' => $datos['observacion'] ?? null,
                        'estado' => 'CONFIRMADA',
                        'registrado_por' => $usuario->id,
                        'confirmado_por' => $usuario->id,
                        'confirmado_en' => now(),
                    ]);

                    $cantidadProforma = [];

                    foreach ($datos['detalles'] as $item) {
                        $cantidad = round((float) $item['cantidad'], 3);
                        if ($cantidad <= 0) {
                            throw ValidationException::withMessages([
                                'cantidad' => 'La cantidad de salida debe ser mayor que cero.',
                            ]);
                        }

                        $inventario = Inventario::query()
                            ->where('producto_id', $item['producto_id'])
                            ->where('repisa_id', $item['repisa_id'])
                            ->lockForUpdate()
                            ->first();

                        if (! $inventario) {
                            throw ValidationException::withMessages([
                                'inventario' =>
                                'No existe inventario para el producto y la repisa indicados.',
                            ]);
                        }

                        $stockAnterior = round((float) $inventario->stock_actual, 3);
                        if ($cantidad > $stockAnterior) {
                            throw ValidationException::withMessages([
                                'cantidad' => "Stock físico insuficiente. Disponible: {$stockAnterior}.",
                            ]);
                        }

                        $tratamiento = $item['tratamiento'];
                        $proformaDetalle = null;

                        if ($motivo === 'PROFORMA') {
                            $proformaDetalle = ProformaDetalle::query()
                                ->whereKey($item['proforma_detalle_id'])
                                ->where('proforma_id', $proforma->id)
                                ->where('producto_id', $item['producto_id'])
                                ->lockForUpdate()
                                ->firstOrFail();

                            $esperado = $proformaDetalle->tratamiento === 'PRESTAMO'
                                ? 'PRESTAMO_EXTERNO'
                                : 'VENTA_DIRECTA';

                            if ($tratamiento !== $esperado) {
                                throw ValidationException::withMessages([
                                    'tratamiento' =>
                                    'El tratamiento de salida no coincide con la Proforma.',
                                ]);
                            }

                            $cantidadProforma[$proformaDetalle->id] = round(
                                ($cantidadProforma[$proformaDetalle->id] ?? 0) + $cantidad,
                                3
                            );
                        } elseif (! in_array($tratamiento, ['CONSUMO', 'USO_TEMPORAL'], true)) {
                            throw ValidationException::withMessages([
                                'tratamiento' =>
                                'Las salidas internas solo admiten Consumo o Uso temporal.',
                            ]);
                        }

                        $aplicacionReserva = ['reserva_id' => null, 'aplicada' => 0.0];
                        if ($orden && $tratamiento === 'CONSUMO') {
                            $aplicacionReserva = $this->reservas->aplicarSalida(
                                $orden,
                                (int) $item['producto_id'],
                                $cantidad,
                                $usuario
                            );
                        }

                        $costoPromedio = round((float) $inventario->costo_promedio_soles, 4);
                        $stockPosterior = round($stockAnterior - $cantidad, 3);

                        $detalle = $nota->detalles()->create([
                            'proforma_detalle_id' => $proformaDetalle?->id,
                            'reserva_material_orden_id' => $aplicacionReserva['reserva_id'],
                            'producto_id' => $item['producto_id'],
                            'repisa_id' => $item['repisa_id'],
                            'cantidad' => $cantidad,
                            'cantidad_aplicada_reserva' => $aplicacionReserva['aplicada'],
                            'tratamiento' => $tratamiento,
                            'costo_unitario_promedio' => $costoPromedio,
                            'subtotal' => round($cantidad * $costoPromedio, 4),
                            'observacion' => $item['observacion'] ?? null,
                        ]);

                        $inventario->update(['stock_actual' => $stockPosterior]);

                        MovimientoInventario::create([
                            'inventario_id' => $inventario->id,
                            'producto_id' => $item['producto_id'],
                            'repisa_id' => $item['repisa_id'],
                            'tipo_movimiento' => 'SALIDA',
                            'motivo' => $this->motivoMovimiento($motivo, $tratamiento),
                            'origen_tipo' => 'NOTA_SALIDA',
                            'origen_id' => $nota->id,
                            'origen_detalle_id' => $detalle->id,
                            'cantidad' => $cantidad,
                            'stock_anterior' => $stockAnterior,
                            'stock_posterior' => $stockPosterior,
                            'costo_unitario' => $costoPromedio,
                            'costo_promedio_anterior' => $costoPromedio,
                            'costo_promedio_nuevo' => $costoPromedio,
                            'fecha_movimiento' => now(),
                            'observacion' => $item['observacion'] ?? null,
                            'registrado_por' => $usuario->id,
                        ]);

                        app(EvaluarAlertasStockService::class)
                            ->evaluarInventario($inventario->refresh(), $usuario);
                    }

                    if ($proforma) {
                        foreach ($cantidadProforma as $detalleId => $cantidadNueva) {
                            $detalle = ProformaDetalle::query()->lockForUpdate()->findOrFail($detalleId);
                            $yaDespachado = (float) $detalle->notasSalidaDetalles()
                                ->whereHas('notaSalida', fn($query) => $query
                                    ->where('estado', 'CONFIRMADA')
                                    ->where('id', '!=', $nota->id))
                                ->sum('cantidad');
                            $pendiente = max(0, round((float) $detalle->cantidad - $yaDespachado, 3));

                            if ($cantidadNueva > $pendiente + 0.0001) {
                                throw ValidationException::withMessages([
                                    'detalles' =>
                                    "La salida supera el pendiente de {$pendiente} de la Proforma.",
                                ]);
                            }
                        }
                    }

                    if ($orden && $orden->estado === 'ABIERTA') {
                        $orden->update(['estado' => 'EN_PROCESO']);
                    }

                    return $nota->load([
                        'ordenOperacion.tipoOrden',
                        'ordenOperacion.cliente',
                        'ordenOperacion.vehiculo',
                        'proforma.cliente',
                        'registrador',
                        'confirmador',
                        'detalles.producto',
                        'detalles.repisa',
                        'detalles.proformaDetalle',
                    ]);
                });
            }
        );
    }

    private function motivoMovimiento(string $motivo, string $tratamiento): string
    {
        if ($motivo === 'PROFORMA') {
            return $tratamiento === 'PRESTAMO_EXTERNO'
                ? 'PRESTAMO_EXTERNO'
                : 'VENTA_DIRECTA';
        }

        if ($tratamiento === 'USO_TEMPORAL') {
            return 'HERRAMIENTA_EN_USO';
        }

        return $motivo === 'ORDEN_OPERACION'
            ? 'CONSUMO_ORDEN'
            : ($motivo === 'USO_INTERNO' ? 'USO_INTERNO' : 'OTRA_SALIDA');
    }
}
