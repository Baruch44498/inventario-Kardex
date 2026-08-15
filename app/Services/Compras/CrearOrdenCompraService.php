<?php

namespace App\Services\Compras;

use App\Models\OrdenCompra;
use App\Models\SolicitudCompra;
use App\Models\SolicitudCompraDetalle;
use App\Models\User;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CrearOrdenCompraService
{
    public function __construct(
        private GenerarCodigoDocumentoService $codigos
    ) {}

    /** @param array<string, mixed> $datos */
    public function crear(array $datos, User $usuario): OrdenCompra
    {
        return $this->codigos->usarSiguiente(
            'ordenes_compra',
            'OC',
            $datos['fecha_emision'],
            function (string $codigo) use ($datos, $usuario): OrdenCompra {
                return DB::transaction(function () use ($datos, $usuario, $codigo): OrdenCompra {
                    $solicitud = SolicitudCompra::query()
                        ->with(['cotizacion.proveedor', 'detalles.cotizacionDetalle'])
                        ->whereKey($datos['solicitud_compra_id'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $solicitud->puedeConvertirseEnOrden()) {
                        throw ValidationException::withMessages([
                            'solicitud_compra_id' => 'El registro de compra debe estar disponible y no haber generado otra orden.',
                        ]);
                    }

                    if ($solicitud->detalles->isEmpty()) {
                        throw ValidationException::withMessages([
                            'solicitud_compra_id' => 'La solicitud aprobada no contiene productos.',
                        ]);
                    }

                    if ($solicitud->estaPendiente()) {
                        $solicitud->update([
                            'estado' => 'APROBADA',
                            'aprobado_por' => $usuario->id,
                            'aprobado_en' => now(),
                        ]);
                    }

                    $cotizacion = $solicitud->cotizacion;
                    $todasConDetalleCotizado = $solicitud->detalles->every(
                        fn(SolicitudCompraDetalle $detalle): bool => $detalle->cotizacionDetalle !== null
                    );
                    $factorDescuentoGlobal = (float) $cotizacion->subtotal > 0
                        ? max(0, 1 - ((float) $cotizacion->descuento_global_monto / (float) $cotizacion->subtotal))
                        : 1.0;
                    $subtotalOrden = $todasConDetalleCotizado
                        ? round((float) $solicitud->detalles->sum(
                            fn(SolicitudCompraDetalle $detalle): float =>
                            (float) $detalle->cotizacionDetalle->subtotal * $factorDescuentoGlobal
                        ), 4)
                        : round((float) $solicitud->total_lineas, 4);
                    $impuestoOrden = round((float) $solicitud->total_lineas - $subtotalOrden, 4);

                    $orden = OrdenCompra::query()->create([
                        'solicitud_compra_id' => $solicitud->id,
                        'proveedor_id' => $cotizacion->proveedor_id,
                        'codigo' => $codigo,
                        'numero_documento_proveedor' => ($datos['numero_documento_proveedor'] ?? null) ?: $cotizacion->numero_documento,
                        'origen' => $solicitud->origen ?: 'REQUERIMIENTO',
                        'justificacion_origen' => $solicitud->justificacion_origen,
                        'fecha_emision' => $datos['fecha_emision'],
                        'fecha_entrega_requerida' => $datos['fecha_entrega_requerida'] ?? null,
                        'moneda' => $cotizacion->moneda,
                        'tipo_cambio' => $cotizacion->tipo_cambio,
                        'subtotal' => $subtotalOrden,
                        'impuesto' => $impuestoOrden,
                        'ajuste_redondeo' => $solicitud->ajuste_redondeo,
                        'total' => $solicitud->total_seleccionado,
                        'condiciones_pago' => $datos['condiciones_pago'] ?? null,
                        'condiciones_entrega' => $datos['condiciones_entrega'] ?? null,
                        'observacion' => $datos['observacion'] ?? null,
                        'estado' => 'APROBADA',
                        'emitido_por' => $usuario->id,
                        'aprobado_por' => $solicitud->aprobado_por,
                        'aprobado_en' => $solicitud->aprobado_en,
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

                    $solicitud->update(['estado' => 'CONVERTIDA']);

                    return $orden->load(['proveedor', 'solicitudCompra', 'detalles.producto']);
                });
            }
        );
    }
}
