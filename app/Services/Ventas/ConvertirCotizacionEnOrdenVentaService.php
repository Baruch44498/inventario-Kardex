<?php

namespace App\Services\Ventas;

use App\Models\ClienteDireccion;
use App\Models\CotizacionCliente;
use App\Models\OrdenOperacion;
use App\Models\TipoOrden;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConvertirCotizacionEnOrdenVentaService
{
    public function convertir(
        CotizacionCliente $cotizacionCliente,
        string $fechaApertura,
        User $usuario
    ): OrdenOperacion {
        return DB::transaction(function () use ($cotizacionCliente, $fechaApertura, $usuario): OrdenOperacion {
            $cotizacion = CotizacionCliente::query()
                ->with(['detalles', 'clienteDireccion'])
                ->lockForUpdate()
                ->findOrFail($cotizacionCliente->id);

            abort_if(
                $cotizacion->proforma_id !== null,
                422,
                'Solo una cotización de venta directa puede generar una OV.'
            );
            abort_unless(
                $cotizacion->puedeConvertirseEnOrden(),
                422,
                'La cotización debe estar cerrada y no haber generado otra orden.'
            );
            abort_if(
                $cotizacion->detalles->isEmpty()
                    || $cotizacion->detalles->contains(
                        fn($detalle): bool => (float) $detalle->cantidad <= 0
                            || (float) $detalle->precio_unitario <= 0
                    ),
                422,
                'Completa los productos, cantidades y precios antes de generar la OV.'
            );
            abort_if(
                $cotizacion->detalles->contains('origen_costeo', true)
                    || $cotizacion->componentes()->exists()
                    || $cotizacion->presupuestos()->exists(),
                422,
                'La cotización histórica con costeo avanzado no puede generar una OV en la versión reducida.'
            );

            $tipoVenta = TipoOrden::query()
                ->where('codigo', 'OV')
                ->where('estado', true)
                ->lockForUpdate()
                ->first();

            abort_unless($tipoVenta, 422, 'No existe un tipo Orden de Venta activo.');

            if ($cotizacion->cliente_direccion_id) {
                abort_unless(
                    ClienteDireccion::query()
                        ->whereKey($cotizacion->cliente_direccion_id)
                        ->where('cliente_id', $cotizacion->cliente_id)
                        ->where('estado', true)
                        ->exists(),
                    422,
                    'La dirección de entrega ya no está disponible para el cliente.'
                );
            }

            $anio = (int) date('Y', strtotime($fechaApertura));
            $ultimo = OrdenOperacion::query()
                ->where('tipo_orden_id', $tipoVenta->id)
                ->where('anio', $anio)
                ->lockForUpdate()
                ->max('numero_correlativo');
            $correlativo = ((int) $ultimo) + 1;
            $descripcion = trim((string) $cotizacion->descripcion_trabajo);

            $orden = OrdenOperacion::query()->create([
                'tipo_orden_id' => $tipoVenta->id,
                'cotizacion_cliente_id' => $cotizacion->id,
                'cliente_id' => $cotizacion->cliente_id,
                'cliente_direccion_id' => $cotizacion->cliente_direccion_id,
                'vehiculo_id' => null,
                'codigo_orden' => sprintf('OV-%03d-%02d', $correlativo, $anio % 100),
                'numero_correlativo' => $correlativo,
                'anio' => $anio,
                'fecha_apertura' => $fechaApertura,
                'descripcion' => $descripcion !== ''
                    ? $descripcion
                    : "Venta según {$cotizacion->codigo}",
                'estado' => 'ABIERTA',
                'creado_por' => $usuario->id,
            ]);

            $cotizacion->update([
                'estado' => 'CONVERTIDA_EN_ORDEN',
                'orden_operacion_id' => $orden->id,
                'tipo_orden_id' => $tipoVenta->id,
                'vehiculo_id' => null,
                'cerrado_por' => $cotizacion->cerrado_por ?: $usuario->id,
                'cerrado_en' => $cotizacion->cerrado_en ?: now(),
            ]);

            return $orden->fresh(['tipoOrden', 'cliente', 'cotizacionCliente']);
        });
    }
}
