<?php

namespace App\Services\Compras\Importacion;

use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\RequisicionDetalle;
use Illuminate\Support\Str;

class InterpretarCotizacionImportada
{
    private const ALIASES = [
        'codigo' => ['codigo', 'cod', 'sku', 'item', 'codigo producto'],
        'descripcion' => ['descripcion', 'producto', 'detalle', 'articulo', 'concepto', 'description'],
        'cantidad' => ['cantidad', 'cant', 'qty', 'quantity'],
        'unidad' => ['unidad', 'und', 'u m', 'um', 'unidad medida'],
        'precio_unitario' => ['precio unitario', 'precio', 'p u', 'pu', 'unit price', 'valor unitario'],
        'descuento' => ['descuento', 'dscto', 'dto', 'discount'],
        'igv' => ['igv', 'impuesto', 'tax'],
        'total' => ['total', 'importe', 'valor total', 'amount'],
    ];

    public function interpretar(array $extraido, Requisicion $requisicion): array
    {
        $requisicion->loadMissing(['detalles.producto.unidadMedida']);
        $texto = (string) ($extraido['texto'] ?? '');
        $advertencias = [];
        $cabecera = $this->extraerCabecera($texto);

        $lineas = ($extraido['tipo'] ?? null) === 'EXCEL'
            ? $this->lineasExcel($extraido['filas'] ?? [], $advertencias)
            : $this->lineasPdfGuiadasPorRequerimiento($texto, $requisicion, $advertencias);

        $igvGlobal = $cabecera['igv_modo_sugerido'] ?? 'AGREGAR';
        if ($igvGlobal !== 'AGREGAR') {
            $lineas = collect($lineas)->map(function (array $linea) use ($igvGlobal): array {
                if (($linea['igv_modo'] ?? 'AGREGAR') === 'AGREGAR') {
                    $linea['igv_modo'] = $igvGlobal;
                }
                return $linea;
            })->all();
        }

        $lineas = $this->vincularConRequerimiento($lineas, $requisicion, $advertencias);

        if ($lineas === []) {
            $advertencias[] = 'No se detectaron líneas con suficiente seguridad. Se precargaron los productos del requerimiento para completar precios manualmente.';
            $lineas = $requisicion->detalles->map(fn (RequisicionDetalle $detalle): array => [
                'requisicion_detalle_id' => $detalle->id,
                'producto_id' => $detalle->producto_id,
                'codigo_documento' => $detalle->producto?->codigo,
                'descripcion_documento' => $detalle->producto?->descripcion,
                'cantidad' => (float) $detalle->cantidad_solicitada,
                'precio_unitario' => null,
                'descuento_modo' => 'SIN_DESCUENTO',
                'descuento_tipo' => null,
                'descuento_valor' => null,
                'igv_modo' => $cabecera['igv_modo_sugerido'] ?? 'AGREGAR',
                'marca_ofertada' => null,
                'observacion' => 'Completar precio: línea no detectada automáticamente.',
                'coincidencia' => 'REQUERIMIENTO',
            ])->values()->all();
        }

        $proveedor = $this->detectarProveedor($texto);
        if ($proveedor) {
            $cabecera['proveedor_id_detectado'] = $proveedor->id;
            $cabecera['proveedor_detectado'] = $proveedor->nombreVisible();
        }

        return [
            'cabecera' => $cabecera,
            'detalles' => $lineas,
            'advertencias' => array_values(array_unique($advertencias)),
            'texto_fuente' => Str::limit($texto, 20000, ''),
        ];
    }

    private function extraerCabecera(string $texto): array
    {
        $normal = preg_replace('/\s+/u', ' ', $texto) ?: $texto;
        $lineas = preg_split('/\R/u', $texto) ?: [];
        $ruc = null;
        if (preg_match('/\b(?:RUC\s*[:#-]?\s*)?(\d{11})\b/ui', $normal, $m)) {
            $ruc = $m[1];
        }

        $numero = null;
        foreach ($lineas as $linea) {
            if (preg_match('/(?:cotizaci[oó]n|quotation|quote)\s*(?:n[°ºo.]*)?\s*[:#-]?\s*([A-Z0-9\-\.\/]+)/ui', $linea, $m)) {
                $numero = trim($m[1]);
                break;
            }
        }

        $fecha = null;
        foreach ($lineas as $linea) {
            if (! preg_match('/fecha|date/ui', $linea)) {
                continue;
            }
            if (preg_match('/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/', $linea, $m)) {
                $fecha = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
                break;
            }
            if (preg_match('/(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/', $linea, $m)) {
                $fecha = sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
                break;
            }
        }

        $moneda = preg_match('/\bUSD\b|US\$|d[oó]lar(?:es)?/ui', $normal) ? 'USD' : 'PEN';
        if (preg_match('/sin\s+igv|no\s+aplica\s+igv/ui', $normal)) {
            $igv = 'NO_APLICA';
        } elseif (preg_match('/igv\s+(?:incluido|incl\.)|incluye\s+igv/ui', $normal)) {
            $igv = 'INCLUIDO';
        } else {
            $igv = 'AGREGAR';
        }

        return [
            'ruc_detectado' => $ruc,
            'numero_documento' => $numero,
            'fecha_cotizacion' => $fecha ?: now()->toDateString(),
            'moneda' => $moneda,
            'igv_modo_sugerido' => $igv,
            'condiciones_pago' => $this->extraerValorEtiquetado($lineas, ['condiciones de pago', 'forma de pago', 'payment']),
            'condiciones_entrega' => $this->extraerValorEtiquetado($lineas, ['condiciones de entrega', 'plazo de entrega', 'delivery']),
        ];
    }

    private function lineasExcel(array $filas, array &$advertencias): array
    {
        $cabeceraIndice = null;
        $mapa = [];

        foreach (array_slice($filas, 0, 30, true) as $indice => $fila) {
            $candidato = [];
            foreach ($fila as $columna => $valor) {
                $normal = $this->normalizar((string) $valor);
                foreach (self::ALIASES as $campo => $aliases) {
                    if ($normal !== '' && in_array($normal, $aliases, true)) {
                        $candidato[$campo] = $columna;
                    }
                }
            }
            if (isset($candidato['descripcion']) && (isset($candidato['cantidad']) || isset($candidato['precio_unitario']))) {
                $cabeceraIndice = $indice;
                $mapa = $candidato;
                break;
            }
        }

        if ($cabeceraIndice === null) {
            $advertencias[] = 'No se reconocieron encabezados de tabla en el Excel. Revisa el documento o completa las líneas desde la vista previa.';
            return [];
        }

        $resultado = [];
        foreach (array_slice($filas, $cabeceraIndice + 1, 250, true) as $fila) {
            $descripcion = trim((string) ($fila[$mapa['descripcion']] ?? ''));
            $codigo = trim((string) ($fila[$mapa['codigo'] ?? -1] ?? ''));
            $cantidad = $this->numero($fila[$mapa['cantidad'] ?? -1] ?? null);
            $precio = $this->numero($fila[$mapa['precio_unitario'] ?? -1] ?? null);

            if ($descripcion === '' && $codigo === '') {
                continue;
            }
            if ($cantidad === null && $precio === null) {
                continue;
            }

            $descuentoTexto = trim((string) ($fila[$mapa['descuento'] ?? -1] ?? ''));
            [$descuentoModo, $descuentoTipo, $descuentoValor] = $this->descuento($descuentoTexto);
            $igvTexto = trim((string) ($fila[$mapa['igv'] ?? -1] ?? ''));

            $resultado[] = [
                'codigo_documento' => $codigo ?: null,
                'descripcion_documento' => $descripcion ?: $codigo,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento_modo' => $descuentoModo,
                'descuento_tipo' => $descuentoTipo,
                'descuento_valor' => $descuentoValor,
                'igv_modo' => $this->igvDesdeTexto($igvTexto),
                'marca_ofertada' => null,
                'observacion' => null,
            ];
        }

        return $resultado;
    }

    private function lineasPdfGuiadasPorRequerimiento(string $texto, Requisicion $requisicion, array &$advertencias): array
    {
        $lineasTexto = collect(preg_split('/\R/u', $texto) ?: [])
            ->map(fn (string $linea): string => trim(preg_replace('/\s+/u', ' ', $linea) ?: $linea))
            ->filter()
            ->values();
        $resultado = [];

        foreach ($requisicion->detalles as $detalle) {
            $producto = $detalle->producto;
            if (! $producto) {
                continue;
            }

            $codigo = trim((string) $producto->codigo);
            $tokens = collect(preg_split('/\s+/u', $this->normalizar($producto->descripcion)) ?: [])
                ->filter(fn (string $token): bool => mb_strlen($token) >= 4)
                ->take(5)
                ->values();

            $mejor = $lineasTexto->first(function (string $linea) use ($codigo, $tokens): bool {
                if ($codigo !== '' && str_contains(mb_strtolower($linea), mb_strtolower($codigo))) {
                    return true;
                }
                $normal = $this->normalizar($linea);
                $coinciden = $tokens->filter(fn (string $token): bool => str_contains($normal, $token))->count();
                return $tokens->count() >= 2 && $coinciden >= min(2, $tokens->count());
            });

            if (! $mejor) {
                continue;
            }

            $numeros = $this->numerosLinea($mejor);
            $cantidad = (float) $detalle->cantidad_solicitada;
            $precio = null;
            if (count($numeros) >= 2) {
                $total = $numeros[count($numeros) - 1];
                $posiblePrecio = $numeros[count($numeros) - 2];
                if ($posiblePrecio > 0 && $total >= $posiblePrecio) {
                    $precio = $posiblePrecio;
                }
            }

            $resultado[] = [
                'codigo_documento' => $codigo,
                'descripcion_documento' => $producto->descripcion,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento_modo' => 'SIN_DESCUENTO',
                'descuento_tipo' => null,
                'descuento_valor' => null,
                'igv_modo' => $this->igvDesdeTexto($mejor),
                'marca_ofertada' => null,
                'observacion' => $precio === null ? 'Revisar precio: no se identificó con seguridad en el PDF.' : null,
                'requisicion_detalle_id' => $detalle->id,
                'producto_id' => $producto->id,
                'coincidencia' => 'REQUERIMIENTO',
            ];
        }

        if ($resultado === []) {
            $advertencias[] = 'El PDF tiene texto digital, pero no se pudieron relacionar líneas con los productos del requerimiento.';
        }

        return $resultado;
    }

    private function vincularConRequerimiento(array $lineas, Requisicion $requisicion, array &$advertencias): array
    {
        $detalles = $requisicion->detalles;

        return collect($lineas)->map(function (array $linea) use ($detalles, &$advertencias): array {
            if (! empty($linea['requisicion_detalle_id']) && ! empty($linea['producto_id'])) {
                return $linea;
            }

            $codigo = $this->normalizar((string) ($linea['codigo_documento'] ?? ''));
            $descripcion = $this->normalizar((string) ($linea['descripcion_documento'] ?? ''));

            $exacta = $detalles->first(function (RequisicionDetalle $detalle) use ($codigo, $descripcion): bool {
                $producto = $detalle->producto;
                if (! $producto) {
                    return false;
                }
                if ($codigo !== '' && $codigo === $this->normalizar($producto->codigo)) {
                    return true;
                }
                return $descripcion !== '' && $descripcion === $this->normalizar($producto->descripcion);
            });

            if ($exacta) {
                $linea['requisicion_detalle_id'] = $exacta->id;
                $linea['producto_id'] = $exacta->producto_id;
                $linea['coincidencia'] = 'EXACTA';
                $linea['cantidad'] ??= (float) $exacta->cantidad_solicitada;
                return $linea;
            }

            $mejor = null;
            $mejorPuntaje = 0.0;
            foreach ($detalles as $detalle) {
                if (! $detalle->producto || $descripcion === '') {
                    continue;
                }
                similar_text($descripcion, $this->normalizar($detalle->producto->descripcion), $puntaje);
                if ($puntaje > $mejorPuntaje) {
                    $mejorPuntaje = $puntaje;
                    $mejor = $detalle;
                }
            }

            if ($mejor && $mejorPuntaje >= 82) {
                $linea['requisicion_detalle_id'] = $mejor->id;
                $linea['producto_id'] = $mejor->producto_id;
                $linea['coincidencia'] = 'SUGERIDA';
                $linea['cantidad'] ??= (float) $mejor->cantidad_solicitada;
                $advertencias[] = sprintf(
                    'Revisa la coincidencia sugerida para “%s” → %s.',
                    $linea['descripcion_documento'] ?? 'línea importada',
                    $mejor->producto?->codigo ?? 'producto'
                );
                return $linea;
            }

            $linea['requisicion_detalle_id'] = null;
            $linea['producto_id'] = null;
            $linea['coincidencia'] = 'SIN_COINCIDENCIA';
            $advertencias[] = sprintf(
                'No se identificó con seguridad el producto “%s”. Debes seleccionarlo antes de confirmar.',
                $linea['descripcion_documento'] ?? $linea['codigo_documento'] ?? 'sin descripción'
            );
            return $linea;
        })->values()->all();
    }

    private function detectarProveedor(string $texto): ?Proveedor
    {
        if (preg_match('/\b(?:RUC\s*[:#-]?\s*)?(\d{11})\b/ui', $texto, $m)) {
            return Proveedor::query()->where('ruc', $m[1])->first();
        }

        return null;
    }

    private function normalizar(string $valor): string
    {
        $valor = Str::ascii(mb_strtolower(trim($valor)));
        $valor = preg_replace('/[^a-z0-9]+/', ' ', $valor) ?: '';
        return trim(preg_replace('/\s+/', ' ', $valor) ?: $valor);
    }

    private function numero(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        $texto = preg_replace('/[^0-9,\.\-]/', '', (string) $valor) ?: '';
        if ($texto === '') {
            return null;
        }
        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = strrpos($texto, ',') > strrpos($texto, '.')
                ? str_replace(['.', ','], ['', '.'], $texto)
                : str_replace(',', '', $texto);
        } elseif (str_contains($texto, ',')) {
            $partes = explode(',', $texto);
            $texto = count($partes) === 2 && strlen(end($partes)) <= 4
                ? str_replace(',', '.', $texto)
                : str_replace(',', '', $texto);
        }
        return is_numeric($texto) ? (float) $texto : null;
    }

    private function numerosLinea(string $linea): array
    {
        preg_match_all('/(?<![A-Za-z])[-+]?\d[\d.,]*/u', $linea, $m);
        return collect($m[0] ?? [])->map(fn ($n) => $this->numero($n))->filter(fn ($n) => $n !== null)->values()->all();
    }

    private function descuento(string $texto): array
    {
        if ($texto === '') {
            return ['SIN_DESCUENTO', null, null];
        }
        $valor = $this->numero($texto);
        if ($valor === null || $valor <= 0) {
            return ['SIN_DESCUENTO', null, null];
        }
        return ['APLICAR', str_contains($texto, '%') ? 'PORCENTAJE' : 'MONTO', $valor];
    }

    private function igvDesdeTexto(string $texto): string
    {
        if (preg_match('/sin\s+igv|no\s+aplica/ui', $texto)) {
            return 'NO_APLICA';
        }
        if (preg_match('/incluido|incluye/ui', $texto) && preg_match('/igv|18\s*%/ui', $texto)) {
            return 'INCLUIDO';
        }
        return 'AGREGAR';
    }

    private function extraerValorEtiquetado(array $lineas, array $etiquetas): ?string
    {
        foreach ($lineas as $linea) {
            $normal = $this->normalizar((string) $linea);
            foreach ($etiquetas as $etiqueta) {
                if (! str_contains($normal, $this->normalizar($etiqueta))) {
                    continue;
                }
                $partes = preg_split('/[:\-]/u', (string) $linea, 2);
                $valor = trim((string) ($partes[1] ?? ''));
                return $valor !== '' ? Str::limit($valor, 500, '') : null;
            }
        }
        return null;
    }
}
