<?php

namespace App\Services\Ventas;

use InvalidArgumentException;

class CalcularProformaService
{
    public const IGV_PORCENTAJE = 18.0;

    /**
     * Calcula una proforma sin consultar ni modificar inventario.
     *
     * Si precio_unitario está presente, representa el precio negociado y tiene
     * prioridad sobre el precio sugerido. Si no está presente, se calcula desde
     * costo_referencia y margen_sugerido.
     */
    public function calcular(
        array $detalles,
        bool $permitirPrecioPendiente = false
    ): array {
        if ($detalles === []) {
            throw new InvalidArgumentException(
                'La proforma debe contener al menos un producto.'
            );
        }

        $lineas = [];
        $subtotal = 0.0;
        $impuesto = 0.0;
        $total = 0.0;

        foreach ($detalles as $detalle) {
            $cantidad = $this->numeroPositivo(
                $detalle['cantidad'] ?? null,
                'La cantidad debe ser mayor que cero.'
            );
            $margen = $this->numeroNoNegativo(
                $detalle['margen_sugerido'] ?? 0,
                'El margen sugerido no puede ser negativo.'
            );
            $costo = $this->numeroOpcionalNoNegativo(
                $detalle['costo_referencia'] ?? null,
                'El costo de referencia no puede ser negativo.'
            );
            $precioSugerido = $costo === null
                ? null
                : $this->precioSugerido($costo, $margen);

            $tienePrecioNegociado = array_key_exists(
                'precio_unitario',
                $detalle
            ) && $detalle['precio_unitario'] !== null
                && $detalle['precio_unitario'] !== '';

            if (
                ! $tienePrecioNegociado
                && $precioSugerido === null
                && ! $permitirPrecioPendiente
            ) {
                throw new InvalidArgumentException(
                    'Cada producto necesita un precio negociado o un costo de referencia.'
                );
            }

            $precio = $tienePrecioNegociado
                ? $this->numeroPositivo(
                    $detalle['precio_unitario'],
                    'El precio unitario debe ser mayor que cero.'
                )
                : ($precioSugerido ?? 0.0);

            if ($precio <= 0 && ! $permitirPrecioPendiente) {
                throw new InvalidArgumentException(
                    'El precio unitario debe ser mayor que cero.'
                );
            }

            $igvModo = strtoupper((string) ($detalle['igv_modo'] ?? 'AGREGAR'));

            [$baseUnitaria, $igvUnitario, $totalUnitario] = match ($igvModo) {
                'INCLUIDO' => $this->separarIgvIncluido($precio),
                'AGREGAR' => [
                    round($precio, 4),
                    round($precio * (self::IGV_PORCENTAJE / 100), 4),
                    round($precio * (1 + self::IGV_PORCENTAJE / 100), 4),
                ],
                'NO_APLICA' => [round($precio, 4), 0.0, round($precio, 4)],
                default => throw new InvalidArgumentException(
                    'Modo de IGV no reconocido.'
                ),
            };

            $subtotalLinea = round($cantidad * $baseUnitaria, 4);
            $impuestoLinea = round($cantidad * $igvUnitario, 4);
            $totalLinea = round($cantidad * $totalUnitario, 4);

            $subtotal += $subtotalLinea;
            $impuesto += $impuestoLinea;
            $total += $totalLinea;

            $lineas[] = [
                ...$detalle,
                'cantidad' => $cantidad,
                'costo_referencia' => $costo,
                'margen_sugerido' => $margen,
                'precio_sugerido' => $precioSugerido,
                'precio_unitario' => round($precio, 4),
                'precio_ajustado' => $precioSugerido !== null
                    && abs($precio - $precioSugerido) > 0.0001,
                'igv_modo' => $igvModo,
                'igv_porcentaje' => $igvModo === 'NO_APLICA'
                    ? 0.0
                    : self::IGV_PORCENTAJE,
                'subtotal' => $subtotalLinea,
                'impuesto' => $impuestoLinea,
                'total' => $totalLinea,
            ];
        }

        return [
            'detalles' => $lineas,
            'totales' => [
                'subtotal' => round($subtotal, 4),
                'impuesto' => round($impuesto, 4),
                'total' => round($total, 4),
            ],
        ];
    }

    public function precioSugerido(
        float $costoReferencia,
        float $margenPorcentaje
    ): float {
        if ($costoReferencia < 0 || $margenPorcentaje < 0) {
            throw new InvalidArgumentException(
                'El costo y el margen no pueden ser negativos.'
            );
        }

        return round(
            $costoReferencia * (1 + ($margenPorcentaje / 100)),
            4
        );
    }

    private function separarIgvIncluido(float $precioFinal): array
    {
        $base = round(
            $precioFinal / (1 + self::IGV_PORCENTAJE / 100),
            4
        );
        $igv = round($precioFinal - $base, 4);

        return [$base, $igv, round($precioFinal, 4)];
    }

    private function numeroPositivo(mixed $valor, string $mensaje): float
    {
        $numero = $this->numero($valor, $mensaje);

        if ($numero <= 0) {
            throw new InvalidArgumentException($mensaje);
        }

        return $numero;
    }

    private function numeroNoNegativo(mixed $valor, string $mensaje): float
    {
        $numero = $this->numero($valor, $mensaje);

        if ($numero < 0) {
            throw new InvalidArgumentException($mensaje);
        }

        return $numero;
    }

    private function numeroOpcionalNoNegativo(
        mixed $valor,
        string $mensaje
    ): ?float {
        if ($valor === null || $valor === '') {
            return null;
        }

        return $this->numeroNoNegativo($valor, $mensaje);
    }

    private function numero(mixed $valor, string $mensaje): float
    {
        if (! is_numeric($valor)) {
            throw new InvalidArgumentException($mensaje);
        }

        return (float) $valor;
    }
}
