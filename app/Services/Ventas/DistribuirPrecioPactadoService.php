<?php

namespace App\Services\Ventas;

use Illuminate\Validation\ValidationException;

/** Distribuye solo el ingreso comercial; nunca reescribe el costeo original. */
class DistribuirPrecioPactadoService
{
    public function aplicar(array $resultado, ?string $precioFinalPactado): array
    {
        if ($precioFinalPactado === null) {
            return $resultado;
        }

        $detalles = $resultado['detalles'];
        $centavos = (int) round((float) $precioFinalPactado * 100);
        if ($centavos < count($detalles) || $detalles === []) {
            throw ValidationException::withMessages([
                'precio_final_pactado' => 'El precio final debe permitir al menos un céntimo por concepto.',
            ]);
        }

        // Las líneas originadas en el presupuesto se emiten con IGV agregado.
        // En otros regímenes fiscales no se puede prorratear un total bruto
        // sin conocer previamente su composición impositiva.
        foreach ($detalles as $detalle) {
            if ($detalle['igv_modo'] !== 'AGREGAR') {
                throw ValidationException::withMessages([
                    'precio_final_pactado' => 'Revisa el IGV de los conceptos antes de pactar un precio único.',
                ]);
            }
        }

        $base = array_map(fn (array $detalle): float => max(0, (float) $detalle['total']), $detalles);
        $suma = array_sum($base);
        if ($suma <= 0) {
            throw ValidationException::withMessages([
                'precio_final_pactado' => 'Sin precios estimados no se puede distribuir el precio pactado.',
            ]);
        }

        $restantes = $centavos - count($detalles);
        $asignacion = array_fill(0, count($detalles), 1);
        $residuos = [];
        foreach ($base as $indice => $valor) {
            $exacto = $restantes * $valor / $suma;
            $asignacion[$indice] += (int) floor($exacto);
            $residuos[$indice] = $exacto - floor($exacto);
        }
        arsort($residuos);
        $pendientes = $centavos - array_sum($asignacion);
        foreach (array_keys($residuos) as $indice) {
            if ($pendientes-- <= 0) {
                break;
            }
            $asignacion[$indice]++;
        }

        $subtotal = 0.0;
        $impuesto = 0.0;
        foreach ($detalles as $indice => &$detalle) {
            $total = $asignacion[$indice] / 100;
            $neto = round($total / 1.18, 4);
            $cantidad = (float) $detalle['cantidad'];
            $unitario = round($neto / $cantidad, 4);
            if ($unitario <= 0) {
                throw ValidationException::withMessages([
                    'precio_final_pactado' => 'El precio pactado deja un concepto sin precio unitario representable.',
                ]);
            }
            $detalle['precio_unitario'] = $unitario;
            $detalle['subtotal'] = $neto;
            $detalle['impuesto'] = round($total - $neto, 4);
            $detalle['total'] = $total;
            $subtotal += $neto;
            $impuesto += $detalle['impuesto'];
        }
        unset($detalle);

        return [
            'detalles' => $detalles,
            'totales' => [
                'subtotal' => round($subtotal, 4),
                'impuesto' => round($impuesto, 4),
                'total' => $centavos / 100,
            ],
        ];
    }
}
