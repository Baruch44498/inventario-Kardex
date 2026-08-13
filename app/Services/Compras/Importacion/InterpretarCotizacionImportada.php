<?php

namespace App\Services\Compras\Importacion;

use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Services\Compras\ResolverVinculacionProductoCotizado;
use Illuminate\Support\Str;

class InterpretarCotizacionImportada
{
    private const ALIASES = [
        'codigo' => ['codigo', 'cod', 'sku', 'item', 'codigo producto'],
        'descripcion' => ['descripcion', 'producto', 'detalle', 'articulo', 'concepto', 'description'],
        'descripcion_solicitada' => ['producto solicitado', 'descripcion solicitada', 'item solicitado'],
        'alternativa_ofrecida' => ['alternativa ofrecida', 'producto ofrecido', 'sustituto ofrecido'],
        'cantidad' => ['cantidad', 'cant', 'qty', 'quantity'],
        'unidad' => ['unidad', 'und', 'u m', 'um', 'unidad medida'],
        'precio_unitario' => ['precio unitario', 'precio', 'p u', 'pu', 'unit price', 'valor unitario'],
        'descuento' => ['descuento', 'dscto', 'dto', 'discount'],
        'igv' => ['igv', 'impuesto', 'tax'],
        'total' => ['total', 'importe', 'valor total', 'amount'],
    ];

    public function __construct(
        private ResolverVinculacionProductoCotizado $vinculador
    ) {}

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
            $lineas = $this->lineasPdf($texto, $requisicion);

            // Compatibilidad con formatos simples ya soportados, por ejemplo
            // "COD DESCRIPCION 10 UND 35.00 350.00".
            if ($lineas === [] && $requisicion) {
                $lineas = $this->lineasPdfGuiadasPorRequerimiento($texto, $requisicion, $advertencias);
            }
        }

        $lineas = $this->identificarRelacionSolicitadoAlternativa(
            $lineas,
            $texto,
            $requisicion
        );

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

        $lineas = $this->vincularProductos($lineas, $requisicion, $advertencias);

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
        foreach ($lineas as $indice => $linea) {
            $linea = trim((string) $linea);

            if (preg_match('/(?:cotizaci[oó]n|quotation|quote)\s*(?:n[°ºo.]*)?\s*[:#-]?\s*$/ui', $linea)) {
                $siguiente = collect(array_slice($lineas, $indice + 1, 4))
                    ->map(fn($valor): string => trim((string) $valor))
                    ->first(fn(string $valor): bool => preg_match('/^[A-Z0-9*][A-Z0-9*\-\.\/]{1,59}$/ui', $valor) === 1);

                if ($siguiente) {
                    $numero = $siguiente;
                    break;
                }

                continue;
            }

            if (preg_match('/(?:cotizaci[oó]n|quotation|quote)\s*(?:n[°ºo.]*)?\s*[:#-]?\s*([A-Z0-9*\-\.\/]{2,60})\s*$/ui', $linea, $m)) {
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
            '/(?:m[aá]s|agregar|adicionar)\s+igv|igv\s+no\s+incluido|no\s+incluyen\s+igv|subtotal.+igv|valor\s+venta\s+neto.{0,180}i\.?\s*g\.?\s*v\.?\s*.{0,180}importe\s+total|precio\s+unitario\s+neto.{0,180}igv.{0,180}precio\s+unitario\s+total/ui',
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
            if (
                (isset($candidato['descripcion']) || isset($candidato['descripcion_solicitada']))
                && (isset($candidato['cantidad']) || isset($candidato['precio_unitario']))
            ) {
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
            $descripcionSolicitada = trim((string) ($fila[$mapa['descripcion_solicitada'] ?? -1] ?? ''));
            $alternativaOfrecida = trim((string) ($fila[$mapa['alternativa_ofrecida'] ?? -1] ?? ''));
            $descripcion = trim((string) ($fila[$mapa['descripcion'] ?? -1] ?? ''));
            if ($descripcion === '') {
                $descripcion = $this->alternativaEsProductoDistinto($alternativaOfrecida)
                    ? $alternativaOfrecida
                    : $descripcionSolicitada;
            }
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
                'descripcion_solicitada_documento' => $descripcionSolicitada ?: null,
                'alternativa_ofrecida_documento' => $alternativaOfrecida ?: null,
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
    private function lineasPdf(string $texto, ?Requisicion $requisicion = null): array
    {
        $lineas = collect(preg_split('/\R/u', $texto) ?: [])
            ->map(fn(string $linea): string => trim(preg_replace('/\s+/u', ' ', $linea) ?: $linea))
            ->filter()
            ->values();
        $resultado = [];

        foreach ($lineas as $indice => $linea) {
            // Formatos tipo Crystal Reports:
            // ITEM CANT. UDM CODIGO PRODUCTO V.UNIT V.TOTAL
            if (preg_match(
                '/^\d{1,3}\s+(\d[\d.,]*)\s+(UND?|UNIDAD(?:ES)?|PZA(?:S)?|PCS?|KG|GL|LT|MTS?|METROS?)\s+([A-Z0-9][A-Z0-9._\/-]{2,})\s+(.+?)\s+(\d[\d.,]*)\s+(\d[\d.,]*)$/ui',
                $linea,
                $filaCantidadPrimero
            )) {
                $descripcion = $this->completarDescripcionMultilineaPdf(
                    $lineas,
                    (int) $indice,
                    trim($filaCantidadPrimero[4])
                );
                $resultado[] = $this->crearLineaPdf(
                    trim($filaCantidadPrimero[3]),
                    $descripcion,
                    $this->numero($filaCantidadPrimero[1]),
                    $this->numero($filaCantidadPrimero[5]),
                    null,
                    $this->numero($filaCantidadPrimero[5]),
                    null,
                    null,
                    $this->numero($filaCantidadPrimero[6])
                );
                continue;
            }

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

        $resultado = array_merge(
            $resultado,
            $this->lineasPdfCrystalVertical($lineas),
            $this->lineasPdfSolicitadoAlternativaVertical($lineas, $requisicion)
        );

        return collect($resultado)
            ->filter(fn(array $linea): bool => $linea['cantidad'] !== null && $linea['precio_unitario'] !== null)
            ->unique(fn(array $linea): string => $this->normalizar((string) $linea['codigo_documento'])
                . '|' . $linea['cantidad'] . '|' . $linea['precio_unitario'])
            ->values()
            ->all();
    }

    /**
     * Algunos reportes Crystal entregan cada celda en orden vertical:
     * ITEM, cantidad, unidad, código; después descripción y precios.
     */
    private function lineasPdfCrystalVertical(\Illuminate\Support\Collection $lineas): array
    {
        $indiceCodigo = $lineas->search(
            fn(string $linea): bool => $this->normalizar($linea) === 'codigo'
        );
        $indiceProducto = $lineas->search(
            fn(string $linea): bool => $this->normalizar($linea) === 'producto'
        );

        if ($indiceCodigo === false || $indiceProducto === false || $indiceProducto <= $indiceCodigo) {
            return [];
        }

        $celdas = $lineas->slice($indiceCodigo + 1, $indiceProducto - $indiceCodigo - 1)->values();
        if (
            $celdas->count() < 4
            || ! preg_match('/^\d{1,3}$/', (string) $celdas[0])
            || $this->numero($celdas[1]) === null
            || ! preg_match('/^(?:UND?|UNIDAD(?:ES)?|PZA(?:S)?|PCS?|KG|GL|LT|MTS?|METROS?)$/ui', (string) $celdas[2])
            || ! preg_match('/^[A-Z0-9][A-Z0-9._\/-]{2,}$/ui', (string) $celdas[3])
        ) {
            return [];
        }

        $indicePrecio = $lineas->search(function (string $linea, int $indice) use ($indiceProducto): bool {
            return $indice > $indiceProducto
                && $this->numero($linea) !== null
                && preg_match('/^-?\d[\d.,]*$/', trim($linea)) === 1;
        });
        if ($indicePrecio === false) {
            return [];
        }

        $inicioDescripcion = $indiceProducto + 1;
        while (
            $inicioDescripcion < $indicePrecio
            && preg_match('/^v\s*\.?\s*unit(?:ario)?$/ui', $this->normalizar((string) $lineas[$inicioDescripcion]))
        ) {
            $inicioDescripcion++;
        }

        $descripcion = $lineas
            ->slice($inicioDescripcion, $indicePrecio - $inicioDescripcion)
            ->implode(' ');
        $precio = $this->numero($lineas[$indicePrecio]);
        $indiceTotal = $lineas->search(
            fn(string $linea, int $indice): bool => $indice > $indicePrecio
                && in_array($this->normalizar($linea), ['v total', 'valor total'], true)
        );
        $subtotalLinea = null;

        if ($indiceTotal !== false) {
            $subtotalLinea = $lineas
                ->slice($indiceTotal + 1, 4)
                ->map(fn(string $linea): ?float => preg_match('/^-?\d[\d.,]*$/', trim($linea))
                    ? $this->numero($linea)
                    : null)
                ->first(fn(?float $valor): bool => $valor !== null);
        }

        if (trim($descripcion) === '' || $precio === null) {
            return [];
        }

        return [$this->crearLineaPdf(
            trim((string) $celdas[3]),
            trim(preg_replace('/\s+/u', ' ', $descripcion) ?: $descripcion),
            $this->numero($celdas[1]),
            $precio,
            null,
            $precio,
            null,
            null,
            $subtotalLinea
        )];
    }

    /**
     * Lee tablas donde "Producto solicitado" y "Alternativa ofrecida" llegan
     * como celdas verticales. Requiere cinco números consecutivos: cantidad,
     * neto, IGV, total unitario y subtotal de línea.
     */
    private function lineasPdfSolicitadoAlternativaVertical(
        \Illuminate\Support\Collection $lineas,
        ?Requisicion $requisicion
    ): array {
        $tieneSolicitado = $lineas->contains(
            fn(string $linea): bool => $this->normalizar($linea) === 'producto solicitado'
        );
        $tieneAlternativa = $lineas->contains(
            fn(string $linea): bool => $this->normalizar($linea) === 'alternativa ofrecida'
        );

        if (! $tieneSolicitado || ! $tieneAlternativa) {
            return [];
        }

        $resultado = [];
        for ($i = 0; $i < $lineas->count() - 7; $i++) {
            if (
                ! preg_match('/^\d{1,3}$/', (string) $lineas[$i])
                || ! preg_match('/^[A-Z0-9][A-Z0-9._\/-]{2,}$/ui', (string) $lineas[$i + 1])
            ) {
                continue;
            }

            $codigo = trim((string) $lineas[$i + 1]);
            $inicioAlternativa = null;
            $inicioNumeros = null;

            for ($j = $i + 2; $j < min($lineas->count() - 4, $i + 18); $j++) {
                if (
                    $inicioAlternativa === null
                    && preg_match('/^(?:NO\s+APLICA|N\s*\/\s*A|ALTERNATIVA|SUSTITUTO|EQUIVALENTE)\b/ui', (string) $lineas[$j])
                ) {
                    $inicioAlternativa = $j;
                }

                $cincoNumeros = true;
                for ($k = 0; $k < 5; $k++) {
                    if (preg_match('/^-?\d[\d.,]*$/', trim((string) $lineas[$j + $k])) !== 1) {
                        $cincoNumeros = false;
                        break;
                    }
                }

                if ($cincoNumeros) {
                    $inicioNumeros = $j;
                    break;
                }
            }

            if ($inicioNumeros === null) {
                continue;
            }

            $inicioAlternativa ??= $this->indiceInicioAlternativaSegunRequisicion(
                $lineas,
                $i + 2,
                $inicioNumeros,
                $requisicion
            );

            if ($inicioAlternativa === null) {
                continue;
            }

            $descripcionSolicitada = $lineas
                ->slice($i + 2, $inicioAlternativa - ($i + 2))
                ->implode(' ');
            $alternativaOfrecida = $lineas
                ->slice($inicioAlternativa, $inicioNumeros - $inicioAlternativa)
                ->implode(' ');
            $numeros = collect(range(0, 4))
                ->map(fn(int $desplazamiento): ?float => $this->numero($lineas[$inicioNumeros + $desplazamiento]))
                ->all();

            if (trim($descripcionSolicitada) === '' || in_array(null, $numeros, true)) {
                continue;
            }

            $linea = $this->crearLineaPdf(
                $codigo,
                trim(preg_replace('/\s+/u', ' ', $descripcionSolicitada) ?: $descripcionSolicitada),
                $numeros[0],
                $numeros[3],
                'INCLUIDO',
                $numeros[1],
                $numeros[2],
                $numeros[3],
                $numeros[4]
            );
            $linea['descripcion_solicitada_documento'] = $linea['descripcion_documento'];
            $linea['alternativa_ofrecida_documento'] = trim(
                preg_replace('/\s+/u', ' ', $alternativaOfrecida) ?: $alternativaOfrecida
            );
            $resultado[] = $linea;
            $i = $inicioNumeros + 4;
        }

        return $resultado;
    }

    private function indiceInicioAlternativaSegunRequisicion(
        \Illuminate\Support\Collection $lineas,
        int $inicioDescripcion,
        int $finDescripcion,
        ?Requisicion $requisicion
    ): ?int {
        if (! $requisicion) {
            return null;
        }

        $acumulada = '';
        for ($i = $inicioDescripcion; $i < $finDescripcion; $i++) {
            $acumulada = trim($acumulada . ' ' . (string) $lineas[$i]);
            $normalAcumulada = $this->normalizar($acumulada);

            foreach ($requisicion->detalles as $detalle) {
                if (
                    $detalle->producto
                    && $normalAcumulada === $this->normalizar($detalle->producto->descripcion)
                ) {
                    return $i + 1;
                }
            }
        }

        return null;
    }

    private function completarDescripcionMultilineaPdf(
        \Illuminate\Support\Collection $lineas,
        int $indiceInicial,
        string $descripcion
    ): string {
        for ($i = $indiceInicial + 1; $i < min($lineas->count(), $indiceInicial + 6); $i++) {
            $candidata = trim((string) $lineas[$i]);

            if (
                $candidata === ''
                || preg_match('/^\d{1,3}\s+\d[\d.,]*\s+(?:UND?|PZA|PCS?|KG|GL|LT|MTS?)/ui', $candidata)
                || preg_match('/^(?:VALOR\s+DE\s+VENTA|SUBTOTAL|I\.?G\.?V\.?|IMPORTE\s+TOTAL|FORMA\s+DE\s+PAGO|CONDICIONES)/ui', $candidata)
            ) {
                break;
            }

            $descripcion .= ' ' . $candidata;
        }

        return trim(preg_replace('/\s+/u', ' ', $descripcion) ?: $descripcion);
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

    /**
     * Conserva por separado qué pidió el requerimiento y qué ofreció el
     * proveedor. Algunos PDFs imprimen ambas descripciones en la misma celda;
     * si "NO APLICA" indica que no existe sustituto, la línea continúa siendo
     * el producto solicitado.
     */
    private function identificarRelacionSolicitadoAlternativa(
        array $lineas,
        string $texto,
        ?Requisicion $requisicion
    ): array {
        $tieneColumnasRelacion = preg_match(
            '/producto\s+solicitado.{0,160}alternativa\s+ofrecida|alternativa\s+ofrecida.{0,160}producto\s+solicitado/uis',
            $texto
        ) === 1;

        if (! $tieneColumnasRelacion && ! collect($lineas)->contains(
            fn(array $linea): bool => ! empty($linea['descripcion_solicitada_documento'])
                || ! empty($linea['alternativa_ofrecida_documento'])
        )) {
            return $lineas;
        }

        return collect($lineas)->map(function (array $linea) use ($requisicion): array {
            $descripcion = trim((string) ($linea['descripcion_documento'] ?? ''));
            $solicitada = trim((string) ($linea['descripcion_solicitada_documento'] ?? ''));
            $alternativa = trim((string) ($linea['alternativa_ofrecida_documento'] ?? ''));

            if ($solicitada === '' && $descripcion !== '') {
                [$solicitadaDetectada, $alternativaDetectada] = $this->separarSolicitadoYAlternativa(
                    $descripcion,
                    $requisicion
                );
                $solicitada = $solicitadaDetectada;
                $alternativa = $alternativa !== '' ? $alternativa : $alternativaDetectada;
            }

            if ($solicitada === '') {
                return $linea;
            }

            $solicitado = $this->vinculador->resolver(
                $linea['codigo_documento'] ?? null,
                $solicitada,
                $requisicion
            );
            if (
                ($solicitado['coincidencia'] ?? null) === 'EXACTA'
                && ! empty($solicitado['producto']['descripcion'])
            ) {
                // Los saltos de línea de ciertos reportes pueden partir la
                // descripción solicitada. El SKU exacto permite restaurar la
                // evidencia completa sin inventar una alternativa.
                $solicitada = $solicitado['producto']['descripcion'];
            }
            $alternativaDistinta = $this->alternativaEsProductoDistinto($alternativa);

            $linea['descripcion_solicitada_documento'] = $solicitada;
            $linea['alternativa_ofrecida_documento'] = $alternativa ?: null;
            $linea['tipo_relacion_documento'] = $alternativaDistinta
                ? 'ALTERNATIVA'
                : 'SOLICITADO';
            $linea['requisicion_detalle_solicitado_documento'] = $solicitado['requisicion_detalle_id'] ?? null;
            $linea['producto_solicitado_documento'] = $solicitado['producto_id'] ?? null;

            if ($alternativaDistinta) {
                $linea['descripcion_documento'] = $alternativa;
            } else {
                $linea['descripcion_documento'] = $solicitada;

                if (($solicitado['coincidencia'] ?? null) === 'EXACTA') {
                    $linea['requisicion_detalle_id'] = $solicitado['requisicion_detalle_id'];
                    $linea['producto_id'] = $solicitado['producto_id'];
                    $linea['coincidencia'] = 'EXACTA';
                }
            }

            return $linea;
        })->values()->all();
    }

    /** @return array{0: string, 1: string} */
    private function separarSolicitadoYAlternativa(
        string $descripcion,
        ?Requisicion $requisicion
    ): array {
        if (preg_match(
            '/^(.+?)\s+((?:NO\s+APLICA|ALTERNATIVA(?:\s+OFRECIDA)?|SUSTITUTO|EQUIVALENTE)\b.*)$/ui',
            $descripcion,
            $partes
        )) {
            return [trim($partes[1]), trim($partes[2])];
        }

        if (! $requisicion) {
            return [$descripcion, ''];
        }

        $normalDocumento = $this->normalizar($descripcion);
        foreach ($requisicion->detalles as $detalle) {
            if (! $detalle->producto) {
                continue;
            }

            $descripcionProducto = trim((string) $detalle->producto->descripcion);
            $normalProducto = $this->normalizar($descripcionProducto);
            if (
                $normalProducto === ''
                || ! str_starts_with($normalDocumento, $normalProducto . ' ')
            ) {
                continue;
            }

            $patron = '/^' . preg_replace('/\s+/u', '\\s+', preg_quote($descripcionProducto, '/')) . '\s*/ui';
            $alternativa = preg_replace($patron, '', $descripcion, 1, $reemplazos);

            if ($reemplazos === 1) {
                return [$descripcionProducto, trim((string) $alternativa)];
            }

            $palabras = preg_split('/\s+/u', $descripcion) ?: [];
            $cantidadPalabrasSolicitadas = count(explode(' ', $normalProducto));

            return [
                $descripcionProducto,
                trim(implode(' ', array_slice($palabras, $cantidadPalabrasSolicitadas))),
            ];
        }

        return [$descripcion, ''];
    }

    private function alternativaEsProductoDistinto(string $alternativa): bool
    {
        $normal = $this->normalizar($alternativa);

        if ($normal === '') {
            return false;
        }

        return ! preg_match(
            '/^(?:no\s+aplica|n\s*a)(?:\b|\s)|exactamente\s+el\s+producto\s+solicitado|mismo\s+producto\s+solicitado/ui',
            $normal
        );
    }

    private function vincularProductos(
        array $lineas,
        ?Requisicion $requisicion,
        array &$advertencias
    ): array {
        return collect($lineas)->map(function (array $linea) use ($requisicion, &$advertencias): array {
            if (! empty($linea['requisicion_detalle_id']) && ! empty($linea['producto_id'])) {
                $linea['tipo_vinculacion'] = ($linea['tipo_relacion_documento'] ?? null) === 'ALTERNATIVA'
                    ? 'ALTERNATIVA'
                    : 'SOLICITADO';
                $linea['vinculacion_origen'] = 'AUTOMATICA';
                $linea['candidatos_vinculacion'] = [];
                return $linea;
            }

            $resultado = $this->vinculador->resolver(
                $linea['codigo_documento'] ?? null,
                $linea['descripcion_documento'] ?? null,
                $requisicion
            );

            $esAlternativa = ($linea['tipo_relacion_documento'] ?? null) === 'ALTERNATIVA';
            $linea['requisicion_detalle_id'] = $esAlternativa
                ? ($linea['requisicion_detalle_solicitado_documento'] ?? null)
                : $resultado['requisicion_detalle_id'];
            $linea['producto_id'] = $resultado['producto_id'];
            $linea['tipo_vinculacion'] = $esAlternativa
                ? 'ALTERNATIVA'
                : $resultado['tipo_vinculacion'];
            $linea['vinculacion_origen'] = $resultado['vinculacion_origen'];
            $linea['coincidencia'] = $resultado['coincidencia'];
            $linea['candidatos_vinculacion'] = $resultado['candidatos'];

            if ($resultado['coincidencia'] === 'SUGERIDA') {
                $advertencias[] = sprintf(
                    'Hay posibles coincidencias para “%s”, pero ninguna se seleccionó automáticamente. Logística debe confirmar una.',
                    $linea['descripcion_documento'] ?? $linea['codigo_documento'] ?? 'línea importada'
                );
            } elseif ($resultado['coincidencia'] === 'SIN_COINCIDENCIA') {
                $advertencias[] = sprintf(
                    'No se identificó con seguridad el producto “%s”. Selecciona uno del catálogo o registra un producto nuevo.',
                    $linea['descripcion_documento'] ?? $linea['codigo_documento'] ?? 'sin descripción'
                );
            }

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
                'desviaciones' => $desviaciones,
                'puntaje' => array_sum($desviaciones),
                'coincide' => $desviaciones[0] < 0.005
                    && collect(array_slice($desviaciones, 1))->every(
                        fn(float $diferencia): bool => $diferencia <= 0.0100001
                    ),
                'conciliable' => collect($desviaciones)->every(
                    fn(float $diferencia): bool => $diferencia <= 0.0500001
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

        $escenariosOrdenados = collect($escenarios);
        $ordenPorPuntaje = fn(array $escenario): string => sprintf(
            '%020.8f-%03d',
            $escenario['puntaje'],
            $escenario['prioridad']
        );
        $ordenPorEvidencia = fn(array $escenario): string => sprintf(
            '%03d-%020.8f',
            $escenario['prioridad'],
            $escenario['puntaje']
        );

        $cabeceraFiscalCompleta = is_numeric($importesDocumento['subtotal'] ?? null)
            && is_numeric($importesDocumento['igv'] ?? null);
        $netoGlobalExacto = $cabeceraFiscalCompleta && $conPrecioNeto
            ? $escenariosOrdenados
            ->first(fn(array $escenario): bool => $escenario['clave'] === 'NETO_MAS_IGV'
                && $escenario['coincide'])
            : null;

        // Cuando subtotal, IGV y total de cabecera coinciden exactamente con
        // los precios netos, el "Precio Unitario Total" es solo una referencia
        // visual redondeada. Se conserva el neto y se agrega el IGV para evitar
        // acumular el redondeo por cada unidad (por ejemplo 26 x 22.90).
        // Si esa comprobación completa no existe, el total unitario explícito
        // mantiene la prioridad y continúa aplicando la regla de ajuste de
        // máximo cinco céntimos.
        $mejor = $netoGlobalExacto
            ?? ($conPrecioFinal
                ? $escenariosOrdenados->firstWhere('clave', 'ACTUAL')
                : null)
            ?? $escenariosOrdenados
            ->filter(fn(array $escenario): bool => $escenario['coincide'])
            ->sortBy($ordenPorPuntaje)
            ->first()
            ?? $escenariosOrdenados
            ->filter(fn(array $escenario): bool => $escenario['conciliable'])
            ->sortBy($ordenPorEvidencia)
            ->first()
            ?? $escenariosOrdenados->sortBy($ordenPorPuntaje)->first();

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
        $conciliable = $mejor['conciliable'];
        $usaMejorInterpretacion = $coincide || $conciliable;
        $lineasResultado = $usaMejorInterpretacion ? $mejor['lineas'] : $lineas;
        $totalesResultado = $usaMejorInterpretacion
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
        $modoDetectado = $usaMejorInterpretacion && $modosDetectados->count() === 1
            ? $modosDetectados->first()
            : null;
        $interpretacion = match ($modoDetectado) {
            'INCLUIDO' => 'Precios finales con IGV incluido',
            'AGREGAR' => 'Precios netos más IGV',
            'NO_APLICA' => 'Operación sin IGV',
            default => $mejor['interpretacion'],
        };

        if ($conciliable && ! $coincide) {
            $ajustePropuesto = round($totalDocumento - $totalesResultado['total'], 2);
            $advertencias[] = sprintf(
                'Se detectó una diferencia de redondeo de %s %0.2f. El cálculo de las líneas se conserva y el ajuste de cabecera deberá confirmarse antes de registrar.',
                $ajustePropuesto >= 0 ? '+' : '-',
                abs($ajustePropuesto)
            );
        } elseif (! $coincide) {
            $advertencias[] = sprintf(
                'El documento declara %0.2f, pero la mejor interpretación calcula %0.2f. Diferencia: %0.2f. Revisa IGV, precios o descuentos antes de registrar.',
                round($totalDocumento, 2),
                round($totalesResultado['total'], 2),
                round($diferencia, 2)
            );
        }

        $estado = $coincide
            ? 'COINCIDE'
            : ($conciliable ? 'AJUSTE_REDONDEO' : 'DIFERENCIA');
        $ajustePropuesto = $estado === 'AJUSTE_REDONDEO'
            ? round($totalDocumento - $totalesResultado['total'], 2)
            : 0.0;

        return [$lineasResultado, [
            'estado' => $estado,
            'subtotal_documento' => $importesDocumento['subtotal'] ?? null,
            'igv_documento' => $importesDocumento['igv'] ?? null,
            'total_documento' => round($totalDocumento, 2),
            'subtotal_calculado' => round($totalesResultado['subtotal'], 2),
            'igv_calculado' => round($totalesResultado['igv'], 2),
            'total_calculado' => round($totalesResultado['total'], 2),
            'diferencia' => round($diferencia, 2),
            'interpretacion' => $interpretacion,
            'igv_modo_detectado' => $modoDetectado,
            'ajuste_redondeo_propuesto' => $ajustePropuesto,
            'requiere_confirmacion_ajuste' => $estado === 'AJUSTE_REDONDEO',
            'tolerancia' => 0.05,
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
                'valor\s+(?:de\s+)?venta(?:\s+neto)?',
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
