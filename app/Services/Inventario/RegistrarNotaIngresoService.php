<?php

namespace App\Services\Inventario;

use App\Models\FacturaProveedor;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\NotaIngreso;
use App\Models\NotaIngresoDetalle;
use App\Models\NotaSalida;
use App\Models\NotaSalidaDetalle;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Models\User;
use App\Services\Documentos\GenerarCodigoDocumentoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarNotaIngresoService
{
    public function __construct(
        private GenerarCodigoDocumentoService $codigos
    ) {}

    public function registrarYConfirmar(array $datos, User $usuario): NotaIngreso
    {
        return $this->codigos->usarSiguiente(
            'notas_ingreso',
            'NI',
            $datos['fecha_ingreso'],
            function (string $codigo) use ($datos, $usuario): NotaIngreso {
                return DB::transaction(function () use ($datos, $usuario, $codigo): NotaIngreso {
                    $motivo = $datos['motivo_ingreso'];
                    $ordenCompra = null;
                    $facturaProveedor = null;
                    $notaSalida = null;
                    $proforma = null;

                    if ($motivo === 'COMPRA') {
                        $ordenCompra = OrdenCompra::query()
                            ->lockForUpdate()
                            ->findOrFail($datos['orden_compra_id']);

                        if (! in_array($ordenCompra->estado, ['APROBADA', 'PARCIALMENTE_RECIBIDA'], true)) {
                            throw ValidationException::withMessages([
                                'orden_compra_id' =>
                                'La orden de compra ya no está disponible para recepción.',
                            ]);
                        }

                        if (! empty($datos['factura_proveedor_id'])) {
                            $facturaProveedor = FacturaProveedor::query()
                                ->with('detalles')
                                ->whereKey($datos['factura_proveedor_id'])
                                ->where('orden_compra_id', $ordenCompra->id)
                                ->where('estado', '!=', 'ANULADA')
                                ->lockForUpdate()
                                ->firstOrFail();
                        }
                    } elseif (in_array($motivo, ['DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL'], true)) {
                        $notaSalida = NotaSalida::query()
                            ->lockForUpdate()
                            ->findOrFail($datos['nota_salida_id']);

                        if ($notaSalida->estado !== 'CONFIRMADA') {
                            throw ValidationException::withMessages([
                                'nota_salida_id' =>
                                'Solo se pueden retornar productos de una Nota de Salida confirmada.',
                            ]);
                        }
                    } elseif ($motivo === 'REPOSICION_PRESTAMO') {
                        $proforma = Proforma::query()
                            ->lockForUpdate()
                            ->findOrFail($datos['proforma_id']);
                    }

                    $detallesActivos = collect($datos['detalles'] ?? [])
                        ->filter(fn(array $item): bool => round((float) ($item['cantidad'] ?? 0), 3) > 0)
                        ->values();

                    if ($detallesActivos->isEmpty()) {
                        throw ValidationException::withMessages([
                            'detalles' => 'Ingresa una cantidad mayor que cero en al menos un producto.',
                        ]);
                    }

                    $nota = NotaIngreso::create([
                        'orden_compra_id' => $ordenCompra?->id,
                        'factura_proveedor_id' => $motivo === 'COMPRA'
                            ? ($datos['factura_proveedor_id'] ?? null)
                            : null,
                        'motivo_ingreso' => $motivo,
                        'nota_salida_id' => $notaSalida?->id,
                        'proforma_id' => $proforma?->id,
                        'codigo' => $codigo,
                        'fecha_ingreso' => $datos['fecha_ingreso'],
                        'numero_guia_remision' => $datos['numero_guia_remision'] ?? null,
                        'observacion' => $datos['observacion'] ?? null,
                        'estado' => 'CONFIRMADA',
                        'registrado_por' => $usuario->id,
                        'confirmado_por' => $usuario->id,
                        'confirmado_en' => now(),
                    ]);

                    foreach ($detallesActivos as $item) {
                        $cantidad = round((float) $item['cantidad'], 3);

                        if ($motivo === 'COMPRA') {
                            $this->registrarCompra(
                                $nota,
                                $ordenCompra,
                                $facturaProveedor,
                                $item,
                                $cantidad,
                                $usuario
                            );
                        } elseif (in_array($motivo, ['DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL'], true)) {
                            $this->registrarRetornoSalida(
                                $nota,
                                $notaSalida,
                                $motivo,
                                $item,
                                $cantidad,
                                $usuario
                            );
                        } elseif ($motivo === 'REPOSICION_PRESTAMO') {
                            $this->registrarReposicionPrestamo(
                                $nota,
                                $proforma,
                                $item,
                                $cantidad,
                                $usuario
                            );
                        }
                    }

                    if ($ordenCompra) {
                        $ordenCompra->load('detalles');
                        $completa = $ordenCompra->detalles->every(
                            fn($detalle) =>
                            (float) $detalle->cantidad_recibida
                                >= (float) $detalle->cantidad_ordenada
                        );

                        $ordenCompra->update([
                            'estado' => $completa ? 'RECIBIDA' : 'PARCIALMENTE_RECIBIDA',
                        ]);
                    }

                    return $nota->load([
                        'ordenCompra.proveedor',
                        'facturaProveedor',
                        'notaSalidaOrigen',
                        'proforma.cliente',
                        'registrador',
                        'confirmador',
                        'detalles.producto',
                        'detalles.repisa',
                        'detalles.notaSalidaDetalle',
                        'detalles.proformaDetalle',
                    ]);
                });
            }
        );
    }

    private function registrarCompra(
        NotaIngreso $nota,
        OrdenCompra $ordenCompra,
        ?FacturaProveedor $facturaProveedor,
        array $item,
        float $cantidad,
        User $usuario
    ): void {
        $ordenDetalle = OrdenCompraDetalle::query()
            ->with([
                'ordenCompra.detalles',
                'solicitudCompraDetalle.cotizacionDetalle.cotizacion',
            ])
            ->whereKey($item['orden_compra_detalle_id'])
            ->where('orden_compra_id', $ordenCompra->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $ordenDetalle->producto_id !== (int) $item['producto_id']) {
            throw ValidationException::withMessages([
                'producto_id' => 'El producto no coincide con la orden de compra.',
            ]);
        }

        $costoUnitario = $ordenDetalle->costoUnitarioInventarioSoles();
        if ($facturaProveedor) {
            $lineasFactura = $facturaProveedor->detalles
                ->where('orden_compra_detalle_id', $ordenDetalle->id);
            $cantidadFactura = (float) $lineasFactura->sum('cantidad');
            if ($cantidadFactura <= 0) {
                throw ValidationException::withMessages([
                    'factura_proveedor_id' => 'La factura no contiene el producto que intentas recibir.',
                ]);
            }

            $yaRecibidoConFactura = (float) NotaIngresoDetalle::query()
                ->where('orden_compra_detalle_id', $ordenDetalle->id)
                ->whereHas('notaIngreso', fn($query) => $query
                    ->where('factura_proveedor_id', $facturaProveedor->id)
                    ->where('estado', 'CONFIRMADA'))
                ->sum('cantidad');
            $pendienteFactura = max(0, round($cantidadFactura - $yaRecibidoConFactura, 3));
            if ($cantidad > $pendienteFactura + 0.0001) {
                throw ValidationException::withMessages([
                    'cantidad' => "La cantidad supera el saldo de {$pendienteFactura} respaldado por la factura.",
                ]);
            }

            $costoUnitario = round(
                ((float) $lineasFactura->sum('total') / $cantidadFactura)
                    * $facturaProveedor->factorSoles(),
                4
            );
        }
        if ($costoUnitario <= 0) {
            throw ValidationException::withMessages([
                'costo_unitario' => 'No se pudo determinar el costo en soles de este producto. Revisa la moneda y el tipo de cambio de la OC.',
            ]);
        }

        $pendiente = round(
            (float) $ordenDetalle->cantidad_ordenada - (float) $ordenDetalle->cantidad_recibida,
            3
        );
        if ($cantidad > $pendiente) {
            throw ValidationException::withMessages([
                'cantidad' => "La cantidad recibida supera el pendiente de {$pendiente}.",
            ]);
        }

        $detalle = $nota->detalles()->create([
            'orden_compra_detalle_id' => $ordenDetalle->id,
            'producto_id' => $item['producto_id'],
            'repisa_id' => $item['repisa_id'],
            'cantidad' => $cantidad,
            'costo_unitario' => $costoUnitario,
            'subtotal' => round($cantidad * $costoUnitario, 4),
            'lote' => $item['lote'] ?? null,
            'fecha_vencimiento' => $item['fecha_vencimiento'] ?? null,
            'observacion' => $item['observacion'] ?? null,
        ]);

        $this->incrementarInventario(
            $nota,
            $detalle,
            $cantidad,
            $costoUnitario,
            'COMPRA',
            $usuario
        );

        $ordenDetalle->update([
            'cantidad_recibida' => round((float) $ordenDetalle->cantidad_recibida + $cantidad, 3),
        ]);
    }

    private function registrarRetornoSalida(
        NotaIngreso $nota,
        NotaSalida $notaSalida,
        string $motivo,
        array $item,
        float $cantidad,
        User $usuario
    ): void {
        $tratamientoEsperado = $motivo === 'DEVOLUCION_HERRAMIENTA'
            ? 'USO_TEMPORAL'
            : 'CONSUMO';

        $salidaDetalle = NotaSalidaDetalle::query()
            ->whereKey($item['nota_salida_detalle_id'])
            ->where('nota_salida_id', $notaSalida->id)
            ->where('producto_id', $item['producto_id'])
            ->where('tratamiento', $tratamientoEsperado)
            ->lockForUpdate()
            ->firstOrFail();

        $yaRetornado = (float) NotaIngresoDetalle::query()
            ->where('nota_salida_detalle_id', $salidaDetalle->id)
            ->whereHas('notaIngreso', fn($query) => $query
                ->where('estado', 'CONFIRMADA')
                ->where('id', '!=', $nota->id))
            ->sum('cantidad');

        $pendiente = max(0, round((float) $salidaDetalle->cantidad - $yaRetornado, 3));
        if ($cantidad > $pendiente + 0.0001) {
            throw ValidationException::withMessages([
                'cantidad' => "La devolución supera el pendiente de {$pendiente}.",
            ]);
        }

        $costoUnitario = round((float) $salidaDetalle->costo_unitario_promedio, 4);
        $detalle = $nota->detalles()->create([
            'nota_salida_detalle_id' => $salidaDetalle->id,
            'producto_id' => $item['producto_id'],
            'repisa_id' => $item['repisa_id'],
            'cantidad' => $cantidad,
            'costo_unitario' => $costoUnitario,
            'subtotal' => round($cantidad * $costoUnitario, 4),
            'observacion' => $item['observacion'] ?? null,
        ]);

        $this->incrementarInventario(
            $nota,
            $detalle,
            $cantidad,
            $costoUnitario,
            $motivo,
            $usuario
        );
    }

    private function registrarReposicionPrestamo(
        NotaIngreso $nota,
        Proforma $proforma,
        array $item,
        float $cantidad,
        User $usuario
    ): void {
        $proformaDetalle = ProformaDetalle::query()
            ->whereKey($item['proforma_detalle_id'])
            ->where('proforma_id', $proforma->id)
            ->where('producto_id', $item['producto_id'])
            ->where('tratamiento', 'PRESTAMO')
            ->lockForUpdate()
            ->firstOrFail();

        $prestadoFisicamente = (float) $proformaDetalle->notasSalidaDetalles()
            ->where('tratamiento', 'PRESTAMO_EXTERNO')
            ->whereHas('notaSalida', fn($query) => $query->where('estado', 'CONFIRMADA'))
            ->sum('cantidad');
        $yaRepuesto = (float) $proformaDetalle->reposiciones()->sum('cantidad');
        $pendiente = max(0, round($prestadoFisicamente - $yaRepuesto, 3));

        if ($cantidad > $pendiente + 0.0001 || $pendiente <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => "La reposición supera el pendiente físico de {$pendiente}.",
            ]);
        }

        // Una reposición en especie no es una compra ni debe generar una
        // ganancia/pérdida artificial de inventario. Reingresa al valor con
        // el que el préstamo salió físicamente del Almacén.
        $costoUnitario = $this->costoPrestamoOriginal($proformaDetalle);

        $detalle = $nota->detalles()->create([
            'proforma_detalle_id' => $proformaDetalle->id,
            'producto_id' => $item['producto_id'],
            'repisa_id' => $item['repisa_id'],
            'cantidad' => $cantidad,
            'costo_unitario' => $costoUnitario,
            'subtotal' => round($cantidad * $costoUnitario, 4),
            'observacion' => $item['observacion'] ?? null,
        ]);

        $this->incrementarInventario(
            $nota,
            $detalle,
            $cantidad,
            $costoUnitario,
            'REPOSICION_PRESTAMO',
            $usuario
        );

        $proformaDetalle->reposiciones()->create([
            'nota_ingreso_id' => $nota->id,
            'nota_ingreso_detalle_id' => $detalle->id,
            'cantidad' => $cantidad,
            'observacion' => $item['observacion'] ?? null,
            'registrado_por' => $usuario->id,
            'registrado_en' => now(),
        ]);
    }

    private function costoPrestamoOriginal(ProformaDetalle $detalle): float
    {
        $totales = $detalle->notasSalidaDetalles()
            ->where('tratamiento', 'PRESTAMO_EXTERNO')
            ->whereHas('notaSalida', fn($query) => $query->where('estado', 'CONFIRMADA'))
            ->selectRaw('COALESCE(SUM(cantidad), 0) as cantidad_total')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as valor_total')
            ->first();

        $cantidad = (float) ($totales->cantidad_total ?? 0);
        if ($cantidad <= 0) {
            return 0.0;
        }

        return round((float) $totales->valor_total / $cantidad, 4);
    }

    private function incrementarInventario(
        NotaIngreso $nota,
        NotaIngresoDetalle $detalle,
        float $cantidad,
        float $costoUnitario,
        string $motivo,
        User $usuario
    ): void {
        $inventario = Inventario::query()
            ->where('producto_id', $detalle->producto_id)
            ->where('repisa_id', $detalle->repisa_id)
            ->lockForUpdate()
            ->first();

        if (! $inventario) {
            $inventario = Inventario::create([
                'producto_id' => $detalle->producto_id,
                'repisa_id' => $detalle->repisa_id,
                'stock_actual' => 0,
                'stock_minimo' => 0,
                'stock_maximo' => null,
                'costo_promedio_soles' => $costoUnitario,
            ]);
        }

        $stockAnterior = round((float) $inventario->stock_actual, 3);
        $costoAnterior = round((float) $inventario->costo_promedio_soles, 4);
        $stockPosterior = round($stockAnterior + $cantidad, 3);
        $costoNuevo = $stockPosterior > 0
            ? round(
                (($stockAnterior * $costoAnterior) + ($cantidad * $costoUnitario)) / $stockPosterior,
                4
            )
            : 0;

        $inventario->update([
            'stock_actual' => $stockPosterior,
            'costo_promedio_soles' => $costoNuevo,
        ]);

        MovimientoInventario::create([
            'inventario_id' => $inventario->id,
            'producto_id' => $detalle->producto_id,
            'repisa_id' => $detalle->repisa_id,
            'tipo_movimiento' => 'ENTRADA',
            'motivo' => $motivo,
            'origen_tipo' => 'NOTA_INGRESO',
            'origen_id' => $nota->id,
            'origen_detalle_id' => $detalle->id,
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_posterior' => $stockPosterior,
            'costo_unitario' => $costoUnitario,
            'costo_promedio_anterior' => $costoAnterior,
            'costo_promedio_nuevo' => $costoNuevo,
            'fecha_movimiento' => now(),
            'observacion' => $detalle->observacion,
            'registrado_por' => $usuario->id,
        ]);

        app(EvaluarAlertasStockService::class)
            ->evaluarInventario($inventario->refresh(), $usuario);
    }
}
