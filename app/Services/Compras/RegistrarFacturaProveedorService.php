<?php

namespace App\Services\Compras;

use App\Models\FacturaProveedor;
use App\Models\FacturaProveedorDetalle;
use App\Models\NotaIngresoDetalle;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\User;
use App\Services\Inventario\RegistrarAjusteCostoFacturaPosteriorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegistrarFacturaProveedorService
{
    public function __construct(
        private RegistrarAjusteCostoFacturaPosteriorService $ajustesCosto
    ) {}

    /** @param array<string, mixed> $datos */
    public function registrar(array $datos, array $archivo, User $usuario): FacturaProveedor
    {
        return DB::transaction(function () use ($datos, $archivo, $usuario): FacturaProveedor {
            $orden = OrdenCompra::query()
                ->whereKey($datos['orden_compra_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($orden->estado, ['APROBADA', 'PARCIALMENTE_RECIBIDA', 'RECIBIDA'], true)) {
                throw ValidationException::withMessages([
                    'orden_compra_id' => 'La orden no está disponible para facturación.',
                ]);
            }

            $documentoRepetido = FacturaProveedor::query()
                ->where('proveedor_id', $orden->proveedor_id)
                ->where('tipo_documento', $datos['tipo_documento'])
                ->where('serie', $datos['serie'])
                ->where('numero', $datos['numero'])
                ->lockForUpdate()
                ->exists();
            if ($documentoRepetido) {
                throw ValidationException::withMessages([
                    'numero' => 'Este documento ya fue registrado para el proveedor.',
                ]);
            }

            $detallesActivos = collect($datos['detalles'])
                ->filter(fn(array $detalle): bool => round((float) ($detalle['cantidad'] ?? 0), 3) > 0)
                ->values();
            if ($detallesActivos->isEmpty()) {
                throw ValidationException::withMessages([
                    'detalles' => 'Ingresa al menos una línea facturada.',
                ]);
            }

            $lineas = $this->construirLineasAutorizadas($orden, $detallesActivos);
            $subtotal = round((float) $lineas->sum('subtotal'), 4);
            $impuesto = round((float) $lineas->sum('impuesto'), 4);
            $total = round((float) $lineas->sum('total'), 4);

            if ($orden->moneda === 'USD' && (float) $orden->tipo_cambio <= 0) {
                throw ValidationException::withMessages([
                    'orden_compra_id' => 'La OC en dólares no tiene un tipo de cambio válido.',
                ]);
            }

            $factura = FacturaProveedor::query()->create([
                'orden_compra_id' => $orden->id,
                'proveedor_id' => $orden->proveedor_id,
                'tipo_documento' => $datos['tipo_documento'],
                'serie' => $datos['serie'],
                'numero' => $datos['numero'],
                'fecha_emision' => $datos['fecha_emision'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'moneda' => $orden->moneda,
                'tipo_cambio' => $orden->moneda === 'USD' ? $orden->tipo_cambio : null,
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total' => $total,
                'ajuste_redondeo' => 0,
                'observacion' => $datos['observacion'] ?? null,
                'archivo_original_path' => $archivo['path'],
                'archivo_original_nombre' => $archivo['nombre'],
                'archivo_original_mime' => $archivo['mime'],
                'archivo_original_hash' => $archivo['hash'],
                'estado' => 'REGISTRADA',
                'registrado_por' => $usuario->id,
            ]);

            foreach ($lineas as $linea) {
                /** @var OrdenCompraDetalle $ordenDetalle */
                $ordenDetalle = $linea['orden_detalle'];
                /** @var NotaIngresoDetalle $ingresoDetalle */
                $ingresoDetalle = $linea['ingreso_detalle'];

                $facturaDetalle = $factura->detalles()->create([
                    'orden_compra_detalle_id' => $ordenDetalle->id,
                    'nota_ingreso_detalle_id' => $ingresoDetalle->id,
                    'producto_id' => $ordenDetalle->producto_id,
                    'descripcion' => $ordenDetalle->producto?->descripcion
                        ?? $ordenDetalle->observacion
                        ?? 'Producto de OC',
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precio_unitario'],
                    'descuento_porcentaje' => 0,
                    'igv_porcentaje' => $linea['igv_porcentaje'],
                    'subtotal' => $linea['subtotal'],
                    'impuesto' => $linea['impuesto'],
                    'total' => $linea['total'],
                    'observacion' => null,
                ]);

                $this->ajustesCosto->aplicar($facturaDetalle, $ingresoDetalle, $usuario);
            }

            return $factura->load([
                'ordenCompra.proveedor',
                'proveedor',
                'registrador',
                'detalles.producto.unidadMedida',
                'detalles.notaIngresoDetalle.notaIngreso',
                'notasIngreso',
            ]);
        });
    }

    /**
     * Los identificadores y la cantidad son la única selección del formulario.
     * Producto, moneda, tipo de cambio, precio e IGV se reconstruyen desde la
     * recepción confirmada y su OC aprobada para que no puedan manipularse.
     *
     * @param Collection<int, array<string, mixed>> $detalles
     * @return Collection<int, array<string, mixed>>
     */
    private function construirLineasAutorizadas(OrdenCompra $orden, Collection $detalles): Collection
    {
        $ingresosUsados = [];

        return $detalles->map(function (array $item) use ($orden, &$ingresosUsados): array {
            $ordenDetalle = OrdenCompraDetalle::query()
                ->with([
                    'producto',
                    'solicitudCompraDetalle.cotizacionDetalle.cotizacion',
                ])
                ->whereKey($item['orden_compra_detalle_id'])
                ->where('orden_compra_id', $orden->id)
                ->lockForUpdate()
                ->firstOrFail();

            $ingresoDetalle = NotaIngresoDetalle::query()
                ->with('notaIngreso')
                ->whereKey($item['nota_ingreso_detalle_id'])
                ->where('orden_compra_detalle_id', $ordenDetalle->id)
                ->where('producto_id', $ordenDetalle->producto_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($ingresoDetalle->id, $ingresosUsados, true)) {
                throw ValidationException::withMessages([
                    'detalles' => 'La misma línea de recepción no puede facturarse dos veces en el documento.',
                ]);
            }
            $ingresosUsados[] = $ingresoDetalle->id;

            if (
                ! $ingresoDetalle->notaIngreso
                || ! $ingresoDetalle->notaIngreso->estaConfirmada()
                || $ingresoDetalle->notaIngreso->motivo_ingreso !== 'COMPRA'
                || (int) $ingresoDetalle->notaIngreso->orden_compra_id !== (int) $orden->id
            ) {
                throw ValidationException::withMessages([
                    'detalles' => 'La factura solo puede registrarse sobre una recepción confirmada de esta orden.',
                ]);
            }

            $yaFacturadoOrden = (float) $ordenDetalle->facturaProveedorDetalles()
                ->whereHas('facturaProveedor', fn($query) => $query->where('estado', '!=', 'ANULADA'))
                ->sum('cantidad');
            $yaFacturadoIngreso = (float) FacturaProveedorDetalle::query()
                ->where('nota_ingreso_detalle_id', $ingresoDetalle->id)
                ->whereHas('facturaProveedor', fn($query) => $query->where('estado', '!=', 'ANULADA'))
                ->sum('cantidad');
            $pendienteOrden = max(0, round(
                (float) $ordenDetalle->cantidad_ordenada - $yaFacturadoOrden,
                3
            ));
            $pendienteIngreso = max(0, round(
                (float) $ingresoDetalle->cantidad - $yaFacturadoIngreso,
                3
            ));
            $pendiente = min($pendienteOrden, $pendienteIngreso);
            $cantidad = round((float) $item['cantidad'], 3);

            if ($cantidad <= 0 || $cantidad > $pendiente + 0.0001) {
                throw ValidationException::withMessages([
                    'detalles' => "La cantidad de {$ordenDetalle->producto?->codigo} supera el saldo recibido pendiente de facturar de {$pendiente}.",
                ]);
            }

            $costoTotal = round($ordenDetalle->costoUnitarioInventarioDocumento(), 4);
            if ($costoTotal <= 0) {
                throw ValidationException::withMessages([
                    'detalles' => "La OC no conserva un costo autorizado válido para {$ordenDetalle->producto?->codigo}.",
                ]);
            }

            $cotizacionDetalle = $ordenDetalle->solicitudCompraDetalle?->cotizacionDetalle;
            $afectoIgv = $cotizacionDetalle
                ? $cotizacionDetalle->igv_modo !== 'NO_APLICA' && (float) $cotizacionDetalle->impuesto > 0
                : (float) $orden->impuesto > 0;
            $igvPorcentaje = $afectoIgv ? 18.0 : 0.0;
            $total = round($cantidad * $costoTotal, 4);
            $subtotal = $afectoIgv ? round($total / 1.18, 4) : $total;
            $impuesto = round($total - $subtotal, 4);

            return [
                'orden_detalle' => $ordenDetalle,
                'ingreso_detalle' => $ingresoDetalle,
                'cantidad' => $cantidad,
                'precio_unitario' => $cantidad > 0 ? round($subtotal / $cantidad, 4) : 0,
                'igv_porcentaje' => $igvPorcentaje,
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total' => $total,
            ];
        })->values();
    }
}
