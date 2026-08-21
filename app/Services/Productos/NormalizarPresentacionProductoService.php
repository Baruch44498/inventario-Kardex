<?php

namespace App\Services\Productos;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class NormalizarPresentacionProductoService
{
    /**
     * Convierte cantidades y precios informados por presentación a la unidad
     * base del producto. El resto del sistema continúa trabajando únicamente
     * con unidades base, evitando stocks paralelos o conversiones ambiguas.
     *
     * @param  array<int, array<string, mixed>>  $detalles
     * @return array<int, array<string, mixed>>
     */
    public function normalizarLineasCotizacion(array $detalles): array
    {
        $productoIds = collect($detalles)->pluck('producto_id')->filter()->unique();
        $presentacionIds = collect($detalles)
            ->pluck('producto_presentacion_id')
            ->filter()
            ->unique();

        /** @var Collection<int, Producto> $productos */
        $productos = Producto::query()
            ->with('unidadMedida')
            ->whereIn('id', $productoIds)
            ->get()
            ->keyBy('id');

        /** @var Collection<int, ProductoPresentacion> $presentaciones */
        $presentaciones = ProductoPresentacion::query()
            ->whereIn('id', $presentacionIds)
            ->where('estado', true)
            ->get()
            ->keyBy('id');

        return collect($detalles)
            ->map(function (array $detalle, int $indice) use ($productos, $presentaciones): array {
                $producto = $productos->get((int) ($detalle['producto_id'] ?? 0));
                if (! $producto) {
                    return $detalle;
                }

                $presentacionId = (int) ($detalle['producto_presentacion_id'] ?? 0);
                $presentacion = $presentacionId > 0
                    ? $presentaciones->get($presentacionId)
                    : null;

                if ($presentacionId > 0 && (
                    ! $presentacion
                    || (int) $presentacion->producto_id !== (int) $producto->id
                )) {
                    throw ValidationException::withMessages([
                        "detalles.{$indice}.producto_presentacion_id" =>
                            'La presentación seleccionada no pertenece a este producto o está inactiva.',
                    ]);
                }

                $factor = $presentacion
                    ? round((float) $presentacion->factor_conversion, 3)
                    : 1.0;
                $cantidadPresentacion = round((float) $detalle['cantidad'], 3);
                $precioPresentacion = round((float) $detalle['precio_unitario'], 4);
                $cantidadBase = round($cantidadPresentacion * $factor, 3);

                if ($factor <= 0 || $cantidadBase <= 0) {
                    throw ValidationException::withMessages([
                        "detalles.{$indice}.cantidad" =>
                            'La presentación debe producir una cantidad base mayor que cero.',
                    ]);
                }

                if (! $producto->permite_fraccionamiento
                    && abs($cantidadBase - round($cantidadBase)) > 0.0001) {
                    throw ValidationException::withMessages([
                        "detalles.{$indice}.cantidad" =>
                            'Este producto no admite cantidades fraccionarias en su unidad base.',
                    ]);
                }

                $detalle['producto_presentacion_id'] = $presentacion?->id;
                $detalle['presentacion_nombre'] = $presentacion?->nombre;
                $detalle['cantidad_presentacion'] = $presentacion
                    ? $cantidadPresentacion
                    : null;
                $detalle['factor_conversion'] = $factor;
                $detalle['precio_presentacion'] = $presentacion
                    ? $precioPresentacion
                    : null;
                $detalle['cantidad'] = $cantidadBase;
                $detalle['precio_unitario'] = round($precioPresentacion / $factor, 4);

                if (($detalle['descuento_tipo'] ?? null) === 'MONTO'
                    && is_numeric($detalle['descuento_valor'] ?? null)) {
                    $detalle['descuento_valor'] = round(
                        (float) $detalle['descuento_valor'] / $factor,
                        4
                    );
                }

                return $detalle;
            })
            ->values()
            ->all();
    }
}
