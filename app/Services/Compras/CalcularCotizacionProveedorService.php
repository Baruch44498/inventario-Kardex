<?php

namespace App\Services\Compras;

use InvalidArgumentException;

class CalcularCotizacionProveedorService
{
    public const IGV_PORCENTAJE = 18.0;

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, float>}
     */
    public function calcular(
        array $detalles,
        string $descuentoGlobalModo = 'SIN_DESCUENTO',
        ?string $descuentoGlobalTipo = null,
        float $descuentoGlobalValor = 0.0
    ): array {
        $lineas = [];
        $subtotal = 0.0;
        $impuestoAntesDescuentoGlobal = 0.0;

        foreach ($detalles as $detalle) {
            $cantidad = round((float) $detalle['cantidad'], 3);
            $precio = round((float) $detalle['precio_unitario'], 4);
            $descuentoModo = $detalle['descuento_modo'] ?? 'SIN_DESCUENTO';
            $descuentoTipo = $detalle['descuento_tipo'] ?? null;
            $descuentoValor = round((float) ($detalle['descuento_valor'] ?? 0), 4);
            $igvModo = $detalle['igv_modo'] ?? 'AGREGAR';

            $precioDespuesDescuento = $this->precioDespuesDescuento(
                $precio,
                $descuentoModo,
                $descuentoTipo,
                $descuentoValor
            );

            [$baseUnitaria, $igvUnitario, $totalUnitario] = match ($igvModo) {
                'INCLUIDO' => $this->separarIgvIncluido($precioDespuesDescuento),
                'AGREGAR' => [
                    $precioDespuesDescuento,
                    round($precioDespuesDescuento * (self::IGV_PORCENTAJE / 100), 4),
                    round($precioDespuesDescuento * (1 + self::IGV_PORCENTAJE / 100), 4),
                ],
                'NO_APLICA' => [$precioDespuesDescuento, 0.0, $precioDespuesDescuento],
                default => throw new InvalidArgumentException('Modo de IGV no reconocido.'),
            };

            $baseLinea = round($cantidad * $baseUnitaria, 4);
            $igvLinea = round($cantidad * $igvUnitario, 4);
            $totalLinea = round($cantidad * $totalUnitario, 4);

            $subtotal += $baseLinea;
            $impuestoAntesDescuentoGlobal += $igvLinea;

            $lineas[] = [
                'requisicion_detalle_id' => $detalle['requisicion_detalle_id'] ?? null,
                'tipo_vinculacion' => $detalle['tipo_vinculacion'] ?? null,
                'vinculacion_origen' => $detalle['vinculacion_origen'] ?? null,
                'codigo_documento' => $detalle['codigo_documento'] ?? null,
                'descripcion_documento' => $detalle['descripcion_documento'] ?? null,
                'producto_id' => $detalle['producto_id'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                // Compatibilidad con los módulos de compra existentes. Solo se
                // informa un porcentaje cuando realmente debe aplicarse.
                'descuento_porcentaje' => $descuentoModo === 'APLICAR'
                    && $descuentoTipo === 'PORCENTAJE'
                    ? $descuentoValor
                    : 0,
                'descuento_modo' => $descuentoModo,
                'descuento_tipo' => $descuentoTipo,
                'descuento_valor' => $descuentoValor ?: null,
                'igv_modo' => $igvModo,
                'igv_porcentaje' => $igvModo === 'NO_APLICA'
                    ? 0
                    : self::IGV_PORCENTAJE,
                'subtotal' => $baseLinea,
                'impuesto' => $igvLinea,
                'total' => $totalLinea,
                'marca_ofertada' => $detalle['marca_ofertada'] ?? null,
                'observacion' => $detalle['observacion'] ?? null,
            ];
        }

        $subtotal = round($subtotal, 4);
        $impuestoAntesDescuentoGlobal = round($impuestoAntesDescuentoGlobal, 4);
        [$descuentoGlobalMonto, $factorDescuentoGlobal] = $this->descuentoGlobal(
            $subtotal,
            $descuentoGlobalModo,
            $descuentoGlobalTipo,
            $descuentoGlobalValor
        );

        // El descuento general reduce proporcionalmente las bases gravadas y,
        // por tanto, también el IGV. Si el descuento ya estaba incluido, no se
        // vuelve a aplicar.
        $impuesto = round(
            $impuestoAntesDescuentoGlobal * (1 - $factorDescuentoGlobal),
            4
        );
        $baseNeta = round($subtotal - $descuentoGlobalMonto, 4);
        $totalCalculado = round($baseNeta + $impuesto, 4);

        return [
            $lineas,
            [
                'subtotal' => $subtotal,
                'descuento_global_monto' => $descuentoGlobalMonto,
                'impuesto' => $impuesto,
                'total' => $totalCalculado,
                'total_calculado' => $totalCalculado,
                'ajuste_redondeo' => 0.0,
            ],
        ];
    }

    private function precioDespuesDescuento(
        float $precio,
        string $modo,
        ?string $tipo,
        float $valor
    ): float {
        if ($modo !== 'APLICAR') {
            return $precio;
        }

        $descuento = match ($tipo) {
            'PORCENTAJE' => $precio * ($valor / 100),
            'MONTO' => $valor,
            default => throw new InvalidArgumentException('Tipo de descuento no reconocido.'),
        };

        if ($descuento > $precio) {
            throw new InvalidArgumentException(
                'El descuento por unidad no puede superar el precio unitario.'
            );
        }

        return round($precio - $descuento, 4);
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function separarIgvIncluido(float $precioFinal): array
    {
        $base = round($precioFinal / (1 + self::IGV_PORCENTAJE / 100), 4);
        $igv = round($precioFinal - $base, 4);

        return [$base, $igv, $precioFinal];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function descuentoGlobal(
        float $subtotal,
        string $modo,
        ?string $tipo,
        float $valor
    ): array {
        if ($modo !== 'APLICAR' || $subtotal <= 0) {
            return [0.0, 0.0];
        }

        $monto = match ($tipo) {
            'PORCENTAJE' => $subtotal * ($valor / 100),
            'MONTO' => $valor,
            default => throw new InvalidArgumentException(
                'Tipo de descuento general no reconocido.'
            ),
        };

        if ($monto > $subtotal) {
            throw new InvalidArgumentException(
                'El descuento general no puede superar el subtotal.'
            );
        }

        $monto = round($monto, 4);

        return [$monto, $subtotal > 0 ? $monto / $subtotal : 0.0];
    }
}
