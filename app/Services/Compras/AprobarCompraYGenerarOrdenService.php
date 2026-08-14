<?php

namespace App\Services\Compras;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\OrdenCompra;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraDetalle;
use App\Models\User;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AprobarCompraYGenerarOrdenService
{
    public function __construct(
        private GenerarCodigoDocumentoService $codigos
    ) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(Cotizacion $cotizacion, array $datos, User $usuario): OrdenCompra
    {
        $ids = collect($datos['detalle_ids'])
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values();

        return $this->codigos->usarSiguientes([
            'solicitud' => [
                'tabla' => 'solicitudes_compra',
                'prefijo' => 'SC',
                'fecha' => now()->toDateString(),
            ],
            'orden' => [
                'tabla' => 'ordenes_compra',
                'prefijo' => 'OC',
                'fecha' => $datos['fecha_emision'],
            ],
        ], function (array $codigos) use ($cotizacion, $datos, $ids, $usuario): OrdenCompra {
            return DB::transaction(function () use ($cotizacion, $datos, $ids, $usuario, $codigos): OrdenCompra {
                $bloqueada = Cotizacion::query()
                    ->whereKey($cotizacion->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $bloqueada->puedeAprobarParaCompra()) {
                    throw ValidationException::withMessages([
                        'cotizacion' => 'Solo una cotización pendiente de decisión puede aprobarse para compra.',
                    ]);
                }

                $detalles = $bloqueada->detalles()
                    ->with('requisicionDetalle')
                    ->whereIn('id', $ids)
                    ->get()
                    ->filter(fn(CotizacionDetalle $detalle): bool => in_array(
                        $detalle->tipoVinculacionEfectivo(),
                        ['SOLICITADO', 'ALTERNATIVA'],
                        true
                    ))
                    ->values();

                if ($detalles->count() !== $ids->count() || $detalles->isEmpty()) {
                    throw ValidationException::withMessages([
                        'detalle_ids' => 'Hay productos que no pertenecen a la cotización o son adicionales no solicitados.',
                    ]);
                }

                $lineas = $detalles->map(function (CotizacionDetalle $detalle): array {
                    $precioFinal = $detalle->precioFinalUnitario();

                    return [
                        'cotizacion_detalle_id' => $detalle->id,
                        'producto_id' => $detalle->producto_id,
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => $precioFinal,
                        'descuento_porcentaje' => 0,
                        'subtotal' => round((float) $detalle->cantidad * $precioFinal, 4),
                        'observacion' => $detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA'
                            ? 'Alternativa seleccionada y revisada por Compras.'
                            : null,
                    ];
                })->values();

                $totalLineas = round((float) $lineas->sum('subtotal'), 4);
                $incluyeDocumentoCompleto = $detalles->count() === $bloqueada->detalles()->count();
                $totalSeleccionado = $incluyeDocumentoCompleto
                    ? round((float) $bloqueada->total, 4)
                    : $totalLineas;
                $ajusteRedondeo = round($totalSeleccionado - $totalLineas, 4);
                $fechaAprobacion = now();

                $solicitud = SolicitudCompra::query()->create([
                    'cotizacion_id' => $bloqueada->id,
                    'codigo' => $codigos['solicitud'],
                    'fecha_solicitud' => now()->toDateString(),
                    'descripcion' => ! empty($datos['descripcion'])
                        ? trim((string) $datos['descripcion'])
                        : "Cotización {$bloqueada->codigo} seleccionada y aprobada por Compras.",
                    'total_lineas' => $totalLineas,
                    'ajuste_redondeo' => $ajusteRedondeo,
                    'total_seleccionado' => $totalSeleccionado,
                    'estado' => 'CONVERTIDA',
                    'solicitado_por' => $usuario->id,
                    'aprobado_por' => $usuario->id,
                    'aprobado_en' => $fechaAprobacion,
                ]);

                $solicitud->detalles()->createMany($lineas->all());
                $solicitud->load('detalles.cotizacionDetalle');

                $factorDescuentoGlobal = (float) $bloqueada->subtotal > 0
                    ? max(0, 1 - ((float) $bloqueada->descuento_global_monto / (float) $bloqueada->subtotal))
                    : 1.0;
                $subtotalOrden = $solicitud->detalles->every(
                    fn(SolicitudCompraDetalle $detalle): bool => $detalle->cotizacionDetalle !== null
                )
                    ? round((float) $solicitud->detalles->sum(
                        fn(SolicitudCompraDetalle $detalle): float =>
                            (float) $detalle->cotizacionDetalle->subtotal * $factorDescuentoGlobal
                    ), 4)
                    : $totalLineas;
                $impuestoOrden = round($totalLineas - $subtotalOrden, 4);

                $orden = OrdenCompra::query()->create([
                    'solicitud_compra_id' => $solicitud->id,
                    'proveedor_id' => $bloqueada->proveedor_id,
                    'codigo' => $codigos['orden'],
                    'numero_documento_proveedor' => ($datos['numero_documento_proveedor'] ?? null) ?: $bloqueada->numero_documento,
                    'fecha_emision' => $datos['fecha_emision'],
                    'fecha_entrega_requerida' => $datos['fecha_entrega_requerida'] ?? null,
                    'moneda' => $bloqueada->moneda,
                    'tipo_cambio' => $bloqueada->tipo_cambio,
                    'subtotal' => $subtotalOrden,
                    'impuesto' => $impuestoOrden,
                    'ajuste_redondeo' => $ajusteRedondeo,
                    'total' => $totalSeleccionado,
                    'condiciones_pago' => $datos['condiciones_pago'] ?? null,
                    'condiciones_entrega' => $datos['condiciones_entrega'] ?? null,
                    'observacion' => $datos['observacion'] ?? null,
                    'estado' => 'APROBADA',
                    'emitido_por' => $usuario->id,
                    'aprobado_por' => $usuario->id,
                    'aprobado_en' => $fechaAprobacion,
                ]);

                $orden->detalles()->createMany(
                    $solicitud->detalles->map(fn(SolicitudCompraDetalle $detalle): array => [
                        'solicitud_compra_detalle_id' => $detalle->id,
                        'producto_id' => $detalle->producto_id,
                        'cantidad_ordenada' => $detalle->cantidad,
                        'cantidad_recibida' => 0,
                        'precio_unitario' => $detalle->precio_unitario,
                        'descuento_porcentaje' => $detalle->descuento_porcentaje,
                        'subtotal' => $detalle->subtotal,
                        'observacion' => $detalle->observacion,
                    ])->all()
                );

                $bloqueada->update([
                    'estado' => 'SELECCIONADA',
                    'evaluado_por' => $usuario->id,
                    'evaluado_en' => $fechaAprobacion,
                    'motivo_evaluacion' => "Compra aprobada mediante {$solicitud->codigo} y {$orden->codigo}.",
                ]);

                return $orden->load(['proveedor', 'solicitudCompra', 'detalles.producto']);
            });
        });
    }
}
