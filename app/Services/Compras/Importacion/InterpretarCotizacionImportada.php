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

    public function interpretar(array $extraido, ?Requisicion $requisicion): array
    {
        $requisicion?->loadMissing(['detalles.producto.unidadMedida']);
        $texto = (string) ($extraido['texto'] ?? '');
        $advertencias = [];
        $cabecera = $this->extraerCabecera($texto);

        if (($extraido['tipo'] ?? null) === 'EXCEL') {
            $lineas = $this->lineasExcel($extraido['filas'] ?? [], $advertencias);
        } else {
            // Primero se extrae lo que realmente figura en el PDF. Vincular las
            // líneas al requerimiento es un paso posterior: un documento con
            // productos distintos no debe quedar vacío ni fingir que cotizó los
            // productos solicitados.
            $lineas = $this->lineasPdf($texto);

            // Compatibilidad con formatos simples ya soportados, por ejemplo
            // "COD DESCRIPCION 10 UND 35.00 350.00".
            if ($lineas === [] && $requisicion) {
                $lineas = $this->lineasPdfGuiadasPorRequerimiento($texto, $requisicion, $advertencias);
            }

            if (! $requisicion && $lineas !== []) {
                $advertencias[] = 'Se extrajeron las líneas del PDF. Selecciona cada producto del catálogo antes de registrar la cotización.';
            }
        }

        $igvGlobal = $cabecera['igv_modo_sugerido'] ?? null;
        if ($igvGlobal) {
            $lineas = collect($lineas)->map(function (array $linea) use ($igvGlobal): array {
                if (empty($linea['igv_modo'])) {
                    $linea['igv_modo'] = $igvGlobal;
                }
                return $linea;
            })->all();
        }

        [$lineas, $conciliacion] = $this->conciliarImportes(
            $lineas,
            $cabecera['importes_documento'] ?? [],
            $advertencias
        );
        $cabecera['conciliacion'] = $conciliacion;

        if (! empty($conciliacion['igv_modo_detectado'])) {
            $cabecera['igv_modo_sugerido'] = $conciliacion['igv_modo_detectado'];
        }

        $lineas = $requisicion
            ? $this->vincularConRequerimiento($lineas, $requisicion, $advertencias)
            : collect($lineas)->map(function (array $linea) use (&$advertencias): array {
                $linea['requisicion_detalle_id'] = null;
                $linea['producto_id'] = null;
                $linea['coincidencia'] = 'SIN_COINCIDENCIA';
                $advertencias[] = sprintf(
                    'Selecciona el producto del catálogo para “%s” antes de registrar.',
                    $linea['descripcion_documento'] ?? $linea['codigo_documento'] ?? 'línea importada'
                );
                return $linea;
            })->values()->all();

        if ($lineas === []) {
            $advertencias[] = 'No se detectaron líneas con suficiente seguridad. No se asumirá que el proveedor cotizó todos los productos; agrega únicamente las líneas que aparecen en el documento.';
        }

        $proveedor = $this->detectarProveedor($cabecera['rucs_detectados'], $advertencias);
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
        preg_match_all('/\b(?:RUC\s*[:#-]?\s*)?(\d{11})\b/ui', $normal, $coincidenciasRuc);
        $rucs = collect($coincidenciasRuc[1] ?? [])->unique()->values()->all();

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

        if ($fecha === null && preg_match(
            '/\b(\d{1,2})\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\s+de\s+(\d{4})\b/ui',
            $normal,
            $m
        )) {
            $meses = [
                'enero' => 1,
                'febrero' => 2,
                'marzo' => 3,
                'abril' => 4,
                'mayo' => 5,
                'junio' => 6,
                'julio' => 7,
                'agosto' => 8,
                'septiembre' => 9,
                'setiembre' => 9,
                'octubre' => 10,
                'noviembre' => 11,
                'diciembre' => 12,
            ];
            $fecha = sprintf('%04d-%02d-%02d', (int) $m[3], $meses[mb_strtolower($m[2])], (int) $m[1]);
        }

        // Priorizar el valor etiquetado de la cotización. Buscar "dólares" en
        // todo el documento confunde las cuentas bancarias del proveedor con
        // la moneda real de la oferta.
        if (preg_match('/(?:valor\s+expresado\s+en|moneda)\s*:?\s*(?:s\/\.?\s*)?(soles?|pen)\b/ui', $normal)) {
            $moneda = 'PEN';
        } elseif (preg_match('/(?:valor\s+expresado\s+en|moneda)\s*:?\s*(?:us\$|\$)?\s*(usd|d[oó]lares?)\b/ui', $normal)) {
            $moneda = 'USD';
        } else {
            $moneda = preg_match('/\bUSD\b|US\$/ui', $normal) ? 'USD' : 'PEN';
        }

        if (preg_match('/sin\s+igv|no\s+aplica\s+igv/ui', $normal)) {
            $igv = 'NO_APLICA';
        } elseif (preg_match('/igv\s+(?:incluido|incl\.)|incluye\s+igv/ui', $normal)) {
            $igv = 'INCLUIDO';
        } elseif (preg_match(
            '/(?:m[aá]s|agregar|adicionar)\s+igv|igv\s+no\s+incluido|subtotal.+igv|valor\s+venta\s+neto.{0,180}i\.?\s*g\.?\s*v\.?\s*.{0,180}importe\s+total|precio\s+unitario\s+neto.{0,180}igv.{0,180}precio\s+unitario\s+total/ui',
            $normal
        )) {
            $igv = 'AGREGAR';
        } else {
            $igv = null;
        }

        return [
            'ruc_detectado' => $rucs[0] ?? null,
            'rucs_detectados' => $rucs,
            'numero_documento' => $numero,
            'fecha_cotizacion' => $fecha ?: now()->toDateString(),
            'moneda' => $moneda,
            'igv_modo_sugerido' => $igv,
            'importes_documento' => $this->extraerImportesDocumento($texto),
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
                'igv_modo' => $this->igvDesdeTexto($igvTexto, true),
                'marca_ofertada' => null,
                'observacion' => null,
            ];
        }

        return $resultado;
    }

    /**
     * Extrae filas frecuentes de PDFs digitales sin exigir una coincidencia
     * previa con el catálogo. Admite columnas en una sola línea y el orden
     * vertical que producen algunos generadores de reportes.
     */
    private function lineasPdf(string $texto): array
    {
        $lineas = collect(preg_split('/\R/u', $texto) ?: [])
            ->map(fn(string $linea): string => trim(preg_replace('/\s+/u', ' ', $linea) ?: $linea))
            ->filter()
            ->values();
        $resultado = [];

        foreach ($lineas as $linea) {
            if (! preg_match('/^(?:\d{1,3}\s+)?([A-Z0-9][A-Z0-9._\/-]{2,})\s+(.+)$/ui', $linea, $m)) {
                continue;
            }

            $codigo = trim($m[1]);
            $resto = trim($m[2]);

            if (preg_match(
                '/^(.+?)\s+(\d[\d.,]*)\s+(?:UND?|UNIDAD(?:ES)?|PZA(?:S)?|PCS?|KG|GL|LT|MTS?|METROS?)\s+(\d[\d.,]*)(?:\s+(\d[\d.,]*))?$/ui',
                $resto,
                $fila
            )) {
                $resultado[] = $this->crearLineaPdf(
                    $codigo,
                    trim($fila[1]),
                    $this->numero($fila[2]),
                    $this->numero($fila[3]),
                    null
                );
                continue;
            }

            if (preg_match(
                '/^(.+?)\s+(\d[\d.,]*)\s+(\d[\d.,]*)\s+(\d[\d.,]*)\s+(\d[\d.,]*)\s+(\d[\d.,]*)$/u',
                $resto,
                $fila
            )) {
                $neto = $this->numero($fila[3]);
                $igvUnitario = $this->numero($fila[4]);
                $totalUnitario = $this->numero($fila[5]);

                if ($this->sumaAproximada($neto, $igvUnitario, $totalUnitario)) {
                    $resultado[] = $this->crearLineaPdf(
                        $codigo,
                        trim($fila[1]),
                        $this->numero($fila[2]),
                        $totalUnitario,
                        'INCLUIDO',
                        $neto,
                        $igvUnitario,
                        $totalUnitario,
                        $this->numero($fila[6] ?? null)
                    );
                }
            }
        }

        // Microsoft Reporting Services puede entregar cada celda como un
        // bloque separado: "1 CODIGO", descripción y luego cinco números.
        for ($i = 0; $i < $lineas->count(); $i++) {
            if (! preg_match('/^\d{1,3}\s+([A-Z0-9][A-Z0-9._\/-]{2,})(?:\s+(.+))?$/ui', $lineas[$i], $inicio)) {
                continue;
            }

            $codigo = trim($inicio[1]);
            $descripcion = trim((string) ($inicio[2] ?? ''));
            $numeros = [];

            for ($j = $i + 1; $j < min($lineas->count(), $i + 12); $j++) {
                if (preg_match('/^\d{1,3}\s+[A-Z0-9][A-Z0-9._\/-]{2,}/ui', $lineas[$j])) {
                    break;
                }
                if ($descripcion === '' && ! preg_match('/^[\d.,]+$/', $lineas[$j])) {
                    $descripcion = $lineas[$j];
                    continue;
                }
                if (preg_match('/^[\d.,]+$/', $lineas[$j])) {
                    $numeros[] = $this->numero($lineas[$j]);
                }
                if (count($numeros) >= 5) {
                    break;
                }
            }

            if (
                $descripcion !== ''
                && count($numeros) >= 5
                && $this->sumaAproximada($numeros[1], $numeros[2], $numeros[3])
            ) {
                $resultado[] = $this->crearLineaPdf(
                    $codigo,
                    $descripcion,
                    $numeros[0],
                    $numeros[3],
                    'INCLUIDO',
                    $numeros[1],
                    $numeros[2],
                    $numeros[3],
                    $numeros[4]
                );
            }
        }

        return collect($resultado)
            ->filter(fn(array $linea): bool => $linea['cantidad'] !== null && $linea['precio_unitario'] !== null)
            ->unique(fn(array $linea): string => $this->normalizar((string) $linea['codigo_documento'])
                . '|' . $linea['cantidad'] . '|' . $linea['precio_unitario'])
            ->values()
            ->all();
    }

    private function crearLineaPdf(
        string $codigo,
        string $descripcion,
        ?float $cantidad,
        ?float $precio,
        ?string $igvModo,
        ?float $precioNetoDocumento = null,
        ?float $igvUnitarioDocumento = null,
        ?float $precioTotalDocumento = null,
        ?float $subtotalLineaDocumento = null
    ): array {
        return [
            'codigo_documento' => $codigo,
            'descripcion_documento' => $descripcion,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'descuento_modo' => 'SIN_DESCUENTO',
            'descuento_tipo' => null,
            'descuento_valor' => null,
            'igv_modo' => $igvModo,
            'marca_ofertada' => null,
            'observacion' => null,
            'precio_unitario_neto_documento' => $precioNetoDocumento,
            'igv_unitario_documento' => $igvUnitarioDocumento,
            'precio_unitario_total_documento' => $precioTotalDocumento,
            'subtotal_linea_documento' => $subtotalLineaDocumento,
        ];
    }

    private function sumaAproximada(?float $base, ?float $igv, ?float $total): bool
    {
        return $base !== null
            && $igv !== null
            && $total !== null
            && abs(($base + $igv) - $total) <= 0.08;
    }

    private function lineasPdfGuiadasPorRequerimiento(string $texto, Requisicion $requisicion, array &$advertencias): array
    {
        $lineasTexto = collect(preg_split('/\R/u', $texto) ?: [])
            ->map(fn(string $linea): string => trim(preg_replace('/\s+/u', ' ', $linea) ?: $linea))
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
                ->filter(fn(string $token): bool => mb_strlen($token) >= 4)
                ->take(5)
                ->values();

            $mejor = $lineasTexto->first(function (string $linea) use ($codigo, $tokens): bool {
                if ($codigo !== '' && str_contains(mb_strtolower($linea), mb_strtolower($codigo))) {
                    return true;
                }
                $normal = $this->normalizar($linea);
                $coinciden = $tokens->filter(fn(string $token): bool => str_contains($normal, $token))->count();
                return $tokens->count() >= 2 && $coinciden >= min(2, $tokens->count());
            });

            if (! $mejor) {
                continue;
            }

            [$cantidad, $precio] = $this->cantidadYPrecioPdf($mejor);

            if ($cantidad === null) {
                $advertencias[] = sprintf(
                    'No se identificó con seguridad la cantidad ofrecida para %s. Debes ingresarla según el PDF.',
                    $codigo !== '' ? $codigo : $producto->descripcion
                );
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

    private function detectarProveedor(array $rucs, array &$advertencias): ?Proveedor
    {
        $proveedores = Proveedor::query()
            ->whereIn('ruc', collect($rucs)->filter()->unique()->values()->all())
            ->get();

        if ($proveedores->count() === 1) {
            return $proveedores->first();
        }

        if ($proveedores->count() > 1) {
            $advertencias[] = 'El documento contiene más de un RUC registrado como proveedor. Selecciona manualmente el emisor correcto.';
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

    private function igvDesdeTexto(string $texto, bool $columnaIgv = false): ?string
    {
        if (preg_match('/sin\s+igv|no\s+aplica/ui', $texto)) {
            return 'NO_APLICA';
        }
        if (
            preg_match('/incluido|incluye/ui', $texto)
            && ($columnaIgv || preg_match('/igv|18\s*%/ui', $texto))
        ) {
            return 'INCLUIDO';
        }
        if (preg_match('/(?:m[aá]s|agregar|adicionar)\s+igv|igv\s+no\s+incluido/ui', $texto)) {
            return 'AGREGAR';
        }

        return null;
    }

    private function cantidadYPrecioPdf(string $linea): array
    {
        if (preg_match(
            '/(?<![\d.,])(\d[\d.,]*)\s*(?:UND?|UNIDAD(?:ES)?|PZA(?:S)?|PCS?|KG|GL|LT|MTS?|METROS?)\b.*?(\d[\d.,]*)\s+(\d[\d.,]*)\s*$/ui',
            $linea,
            $coincidencia
        )) {
            return [
                $this->numero($coincidencia[1]),
                $this->numero($coincidencia[2]),
            ];
        }

        return [null, null];
    }

    /**
     * Compara los escenarios de IGV contra el total que el proveedor declaró.
     * Solo precarga automáticamente un escenario cuando coincide a nivel de
     * moneda (dos decimales). Nunca crea descuentos para forzar la igualdad.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function conciliarImportes(
        array $lineas,
        array $importesDocumento,
        array &$advertencias
    ): array {
        $totalDocumento = $this->numero($importesDocumento['total'] ?? null);

        if ($totalDocumento === null || $lineas === []) {
            return [$lineas, [
                'estado' => 'SIN_TOTAL',
                'subtotal_documento' => $importesDocumento['subtotal'] ?? null,
                'igv_documento' => $importesDocumento['igv'] ?? null,
                'total_documento' => $totalDocumento,
                'total_calculado' => null,
                'diferencia' => null,
                'interpretacion' => null,
                'igv_modo_detectado' => null,
                'tolerancia' => 0.01,
            ]];
        }

        $escenarios = [];
        $agregarEscenario = function (string $clave, string $interpretacion, array $candidato, int $prioridad) use (&$escenarios, $totalDocumento, $importesDocumento): void {
            $totales = $this->calcularTotalesLineas($candidato);
            if ($totales === null) {
                return;
            }

            $desviaciones = [
                abs(round($totales['total'], 2) - round($totalDocumento, 2)),
            ];
            if (is_numeric($importesDocumento['subtotal'] ?? null)) {
                $desviaciones[] = abs(
                    round($totales['subtotal'], 2)
                        - round((float) $importesDocumento['subtotal'], 2)
                );
            }
            if (is_numeric($importesDocumento['igv'] ?? null)) {
                $desviaciones[] = abs(
                    round($totales['igv'], 2)
                        - round((float) $importesDocumento['igv'], 2)
                );
            }

            $escenarios[] = [
                'clave' => $clave,
                'interpretacion' => $interpretacion,
                'lineas' => $candidato,
                'totales' => $totales,
                'diferencia' => $desviaciones[0],
                'puntaje' => array_sum($desviaciones),
                'coincide' => collect($desviaciones)->every(
                    fn(float $diferencia): bool => $diferencia <= 0.01
                ),
                'prioridad' => $prioridad,
            ];
        };

        $agregarEscenario('ACTUAL', 'Tratamiento detectado en el documento', $lineas, 0);

        $conPrecioFinal = collect($lineas)->every(
            fn(array $linea): bool => is_numeric($linea['precio_unitario_total_documento'] ?? null)
        );
        if ($conPrecioFinal) {
            $agregarEscenario(
                'TOTAL_INCLUIDO',
                'Precios finales con IGV incluido',
                collect($lineas)->map(function (array $linea): array {
                    $linea['precio_unitario'] = (float) $linea['precio_unitario_total_documento'];
                    $linea['igv_modo'] = 'INCLUIDO';
                    return $linea;
                })->all(),
                1
            );
        }

        $conPrecioNeto = collect($lineas)->every(
            fn(array $linea): bool => is_numeric($linea['precio_unitario_neto_documento'] ?? null)
        );
        if ($conPrecioNeto) {
            $agregarEscenario(
                'NETO_MAS_IGV',
                'Precios netos más IGV',
                collect($lineas)->map(function (array $linea): array {
                    $linea['precio_unitario'] = (float) $linea['precio_unitario_neto_documento'];
                    $linea['igv_modo'] = 'AGREGAR';
                    return $linea;
                })->all(),
                2
            );
        }

        foreach (['INCLUIDO', 'AGREGAR', 'NO_APLICA'] as $indice => $modo) {
            $agregarEscenario(
                'PRECIO_' . $modo,
                match ($modo) {
                    'INCLUIDO' => 'Precios con IGV incluido',
                    'AGREGAR' => 'Precios netos más IGV',
                    default => 'Operación sin IGV',
                },
                collect($lineas)->map(function (array $linea) use ($modo): array {
                    $linea['igv_modo'] = $modo;
                    return $linea;
                })->all(),
                10 + $indice
            );
        }

        $mejor = collect($escenarios)
            ->sortBy(fn(array $escenario): string => sprintf(
                '%020.8f-%03d',
                $escenario['puntaje'],
                $escenario['prioridad']
            ))
            ->first();

        if (! $mejor) {
            return [$lineas, [
                'estado' => 'DIFERENCIA',
                'subtotal_documento' => $importesDocumento['subtotal'] ?? null,
                'igv_documento' => $importesDocumento['igv'] ?? null,
                'total_documento' => round($totalDocumento, 2),
                'total_calculado' => null,
                'diferencia' => null,
                'interpretacion' => null,
                'igv_modo_detectado' => null,
                'tolerancia' => 0.01,
            ]];
        }

        $coincide = $mejor['coincide'];
        $lineasResultado = $coincide ? $mejor['lineas'] : $lineas;
        $totalesResultado = $coincide
            ? $mejor['totales']
            : ($this->calcularTotalesLineas($lineas) ?? $mejor['totales']);
        $diferencia = abs(
            round($totalesResultado['total'], 2) - round($totalDocumento, 2)
        );
        $modosDetectados = collect($lineasResultado)
            ->pluck('igv_modo')
            ->filter()
            ->unique()
            ->values();
        $modoDetectado = $coincide && $modosDetectados->count() === 1
            ? $modosDetectados->first()
            : null;
        $interpretacion = match ($modoDetectado) {
            'INCLUIDO' => 'Precios finales con IGV incluido',
            'AGREGAR' => 'Precios netos más IGV',
            'NO_APLICA' => 'Operación sin IGV',
            default => $mejor['interpretacion'],
        };

        if (! $coincide) {
            $advertencias[] = sprintf(
                'El documento declara %0.2f, pero la mejor interpretación calcula %0.2f. Diferencia: %0.2f. Revisa IGV, precios o descuentos antes de registrar.',
                round($totalDocumento, 2),
                round($totalesResultado['total'], 2),
                round($diferencia, 2)
            );
        }

        return [$lineasResultado, [
            'estado' => $coincide ? 'COINCIDE' : 'DIFERENCIA',
            'subtotal_documento' => $importesDocumento['subtotal'] ?? null,
            'igv_documento' => $importesDocumento['igv'] ?? null,
            'total_documento' => round($totalDocumento, 2),
            'subtotal_calculado' => round($totalesResultado['subtotal'], 2),
            'igv_calculado' => round($totalesResultado['igv'], 2),
            'total_calculado' => round($totalesResultado['total'], 2),
            'diferencia' => round($diferencia, 2),
            'interpretacion' => $interpretacion,
            'igv_modo_detectado' => $modoDetectado,
            'tolerancia' => 0.01,
        ]];
    }

    private function calcularTotalesLineas(array $lineas): ?array
    {
        $subtotal = 0.0;
        $igv = 0.0;

        foreach ($lineas as $linea) {
            if (
                ! is_numeric($linea['cantidad'] ?? null)
                || ! is_numeric($linea['precio_unitario'] ?? null)
                || empty($linea['igv_modo'])
            ) {
                return null;
            }

            $cantidad = round((float) $linea['cantidad'], 3);
            $precio = round((float) $linea['precio_unitario'], 4);
            $modoDescuento = $linea['descuento_modo'] ?? 'SIN_DESCUENTO';

            if ($modoDescuento === 'APLICAR') {
                $valor = round((float) ($linea['descuento_valor'] ?? 0), 4);
                $precio -= ($linea['descuento_tipo'] ?? null) === 'PORCENTAJE'
                    ? $precio * ($valor / 100)
                    : $valor;
                $precio = round(max(0, $precio), 4);
            }

            [$baseUnitaria, $igvUnitario] = match ($linea['igv_modo']) {
                'INCLUIDO' => [
                    round($precio / 1.18, 4),
                    round($precio - round($precio / 1.18, 4), 4),
                ],
                'AGREGAR' => [$precio, round($precio * 0.18, 4)],
                'NO_APLICA' => [$precio, 0.0],
                default => [null, null],
            };

            if ($baseUnitaria === null || $igvUnitario === null) {
                return null;
            }

            $subtotal += round($cantidad * $baseUnitaria, 4);
            $igv += round($cantidad * $igvUnitario, 4);
        }

        $subtotal = round($subtotal, 4);
        $igv = round($igv, 4);

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => round($subtotal + $igv, 4),
        ];
    }

    private function extraerImportesDocumento(string $texto): array
    {
        return [
            'subtotal' => $this->extraerImportePorPatrones($texto, [
                'valor\s+venta\s+neto',
                'subtotal(?:\s+sin\s+igv)?',
                'base\s+imponible',
            ]),
            'igv' => $this->extraerImportePorPatrones($texto, [
                'i\.?\s*g\.?\s*v\.?(?:\s*18\s*%)?',
                'impuesto(?:\s+igv)?',
            ]),
            'total' => $this->extraerImportePorPatrones($texto, [
                'importe\s+total',
                'total\s+(?:a\s+)?pagar',
                'total\s+cotizaci[oó]n',
                'total\s+general',
            ]),
        ];
    }

    private function extraerImportePorPatrones(string $texto, array $patrones): ?float
    {
        foreach ($patrones as $patron) {
            if (preg_match(
                '/(?:' . $patron . ')\s*:?\s*(?:S\s*\/\.?|US\s*\$|USD|PEN|\$)?\s*(-?\d[\d.,]*)/ui',
                $texto,
                $coincidencia
            )) {
                return $this->numero($coincidencia[1]);
            }
        }

        return null;
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
