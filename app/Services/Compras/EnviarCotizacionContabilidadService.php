<?php

namespace App\Services\Compras;

use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\SolicitudCompra;
use App\Models\User;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EnviarCotizacionContabilidadService
{
    public function __construct(
        private GenerarCodigoDocumentoService $codigos
    ) {}

    /** @param array<int, int|string> $detalleIds */
    public function enviar(
        Cotizacion $cotizacion,
        array $detalleIds,
        User $usuario,
        ?string $descripcion = null
    ): SolicitudCompra {
        $ids = collect($detalleIds)->map(fn($id): int => (int) $id)->unique()->values();

        return $this->codigos->usarSiguiente(
            'solicitudes_compra',
            'SC',
            now()->toDateString(),
            function (string $codigo) use ($cotizacion, $ids, $usuario, $descripcion): SolicitudCompra {
                return DB::transaction(function () use (
                    $cotizacion,
                    $ids,
                    $usuario,
                    $descripcion,
                    $codigo
                ): SolicitudCompra {
                    $bloqueada = Cotizacion::query()
                        ->whereKey($cotizacion->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $bloqueada->puedeEnviarAContabilidad()) {
                        throw ValidationException::withMessages([
                            'cotizacion' => 'Solo una cotización pendiente de decisión puede enviarse a Contabilidad.',
                        ]);
                    }

                    $detalles = $bloqueada->detalles()
                        ->with(['cotizacion', 'requisicionDetalle'])
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

                    $lineasSolicitud = $detalles->map(function (CotizacionDetalle $detalle): array {
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

                    $totalLineas = round((float) $lineasSolicitud->sum('subtotal'), 4);
                    $incluyeDocumentoCompleto = $detalles->count() === $bloqueada->detalles()->count();
                    $totalSeleccionado = $incluyeDocumentoCompleto
                        ? round((float) $bloqueada->total, 4)
                        : $totalLineas;
                    $ajusteRedondeo = round($totalSeleccionado - $totalLineas, 4);

                    $solicitud = SolicitudCompra::query()->create([
                        'cotizacion_id' => $bloqueada->id,
                        'codigo' => $codigo,
                        'fecha_solicitud' => now()->toDateString(),
                        'descripcion' => $descripcion
                            ? trim($descripcion)
                            : "Cotización {$bloqueada->codigo} seleccionada para revisión contable.",
                        'total_lineas' => $totalLineas,
                        'ajuste_redondeo' => $ajusteRedondeo,
                        'total_seleccionado' => $totalSeleccionado,
                        'estado' => 'PENDIENTE',
                        'solicitado_por' => $usuario->id,
                    ]);

                    $solicitud->detalles()->createMany($lineasSolicitud->all());

                    $bloqueada->update([
                        'estado' => 'SELECCIONADA',
                        'evaluado_por' => $usuario->id,
                        'evaluado_en' => now(),
                        'motivo_evaluacion' => "Enviada a Contabilidad mediante {$codigo}.",
                    ]);

                    return $solicitud->load(['cotizacion.proveedor', 'detalles.producto']);
                });
            }
        );
    }
}
