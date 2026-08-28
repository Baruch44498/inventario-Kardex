<?php

namespace App\Services\Ventas;

use App\Models\CotizacionPresupuesto;
use App\Models\Producto;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ExtractorPlantillaCosteoExcel
{
    public function extraer(string $rutaAbsoluta): array
    {
        if (! class_exists(IOFactory::class)) {
            throw new RuntimeException('Falta PhpSpreadsheet. Ejecuta composer install antes de importar el Excel.');
        }

        $lector = IOFactory::createReaderForFile($rutaAbsoluta);
        $lector->setReadDataOnly(false);
        $libro = $lector->load($rutaAbsoluta);
        $hoja = $libro->getActiveSheet();
        $ultimaFila = min($hoja->getHighestDataRow(), 1000);
        $filaCabecera = $this->buscarCabecera($hoja, $ultimaFila);

        if ($filaCabecera === null) {
            throw new RuntimeException('No se encontró la cabecera CANT. / DESCRIPCION DEL PRODUCTO del formato de costos.');
        }

        $grupo = null;
        $tipoCambioAnterior = 0.0;
        $partidas = [];
        $advertencias = [];

        for ($fila = $filaCabecera + 1; $fila <= $ultimaFila; $fila++) {
            $codigo = $this->texto($this->valor($hoja->getCell("A{$fila}")));
            $cantidad = $this->numero($this->valor($hoja->getCell("B{$fila}")));
            $descripcion = $this->texto($this->valor($hoja->getCell("C{$fila}")));
            $unidadOriginal = $this->texto($this->valor($hoja->getCell("D{$fila}")));
            $costoUnitario = $this->numero($this->valor($hoja->getCell("F{$fila}")));
            $costoTotal = $this->numero($this->valor($hoja->getCell("G{$fila}")));
            $margen = $this->numero($this->valor($hoja->getCell("H{$fila}")));
            $tipoCambio = $this->numero($this->valor($hoja->getCell("N{$fila}")));

            if ($tipoCambio > 0) {
                $tipoCambioAnterior = $tipoCambio;
            }

            if ($this->esTituloGrupo($cantidad, $costoUnitario, $costoTotal, $descripcion)) {
                $grupo = mb_substr($descripcion, 0, 150);
                continue;
            }

            if ($cantidad <= 0 || ($costoUnitario <= 0 && $costoTotal <= 0)) {
                continue;
            }

            if ($costoUnitario <= 0 && $costoTotal > 0) {
                $costoUnitario = $costoTotal / $cantidad;
            }
            if ($tipoCambio <= 0) {
                $tipoCambio = $tipoCambioAnterior;
            }
            if ($tipoCambio <= 0) {
                $tipoCambio = 1;
                $advertencias[] = "Fila {$fila}: no se encontró tipo de cambio; revisa el valor antes de usar la plantilla.";
            }

            $tipoCosto = $this->tipoCosto($grupo, $unidadOriginal);
            $producto = $tipoCosto === 'MATERIAL'
                ? $this->buscarProducto($codigo)
                : null;
            if ($producto && ! $producto->cantidadAdmitida($cantidad)) {
                $advertencias[] = "Fila {$fila}: el producto {$producto->codigo} no admite la cantidad fraccionaria {$cantidad}. Revisa su configuración.";
                $producto = null;
            }
            $descripcionValida = ! $this->esErrorFormula($descripcion);
            if ($producto) {
                $descripcion = $producto->descripcion;
            } elseif (! $descripcionValida) {
                $descripcion = $codigo !== ''
                    ? "Producto código {$codigo}"
                    : "Partida de la fila {$fila}";
            }

            $unidad = $this->unidadPara($tipoCosto, $unidadOriginal, $producto);
            $pendiente = $tipoCosto === 'MATERIAL' && ! $producto;
            if ($pendiente) {
                $referencia = $codigo !== '' ? "código {$codigo}" : $descripcion;
                $advertencias[] = "Fila {$fila}: vincula {$referencia} con un producto del almacén.";
            }

            $partidas[] = [
                'producto_id' => $producto?->id,
                'fila_excel' => $fila,
                'grupo_costo' => $grupo,
                'codigo_referencia' => $codigo !== '' ? mb_substr($codigo, 0, 80) : null,
                'descripcion' => mb_substr($descripcion, 0, 300),
                'cantidad' => round($cantidad, 3),
                'unidad_original' => $unidadOriginal !== '' ? mb_substr($unidadOriginal, 0, 50) : null,
                'tipo_costo' => $tipoCosto,
                'unidad' => $unidad,
                'moneda' => 'PEN',
                'tipo_cambio' => round($tipoCambio, 6),
                'costo_unitario' => round($costoUnitario, 4),
                'margen_porcentaje' => round($margen > 0 && $margen <= 1 ? $margen * 100 : $margen, 4),
                'carga_social_porcentaje' => 0,
                'igv_modo' => $tipoCosto === 'MANO_OBRA' ? 'NO_APLICA' : 'INCLUIDO',
                'igv_porcentaje' => 18,
                'igv_venta_porcentaje' => 18,
                'estado_vinculacion' => $pendiente ? 'PENDIENTE' : ($producto ? 'VINCULADA' : 'NO_APLICA'),
                'omitida' => false,
                'observacion' => "Importado de la fila {$fila} del Excel.",
                'orden_secuencia' => count($partidas) + 1,
            ];
        }

        if ($partidas === []) {
            throw new RuntimeException('El Excel no contiene partidas reconocibles con cantidad y costo.');
        }

        return [
            'hoja' => $hoja->getTitle(),
            'partidas' => $partidas,
            'advertencias' => array_values(array_unique($advertencias)),
        ];
    }

    private function buscarCabecera($hoja, int $ultimaFila): ?int
    {
        for ($fila = 1; $fila <= min($ultimaFila, 30); $fila++) {
            $cantidad = $this->normalizar($this->texto($this->valor($hoja->getCell("B{$fila}"))));
            $descripcion = $this->normalizar($this->texto($this->valor($hoja->getCell("C{$fila}"))));
            if (str_contains($cantidad, 'CANT') && str_contains($descripcion, 'DESCRIPCION')) {
                return $fila;
            }
        }

        return null;
    }

    private function valor(Cell $celda): mixed
    {
        if ($celda->isFormula()) {
            $cache = $celda->getOldCalculatedValue();
            if ($cache !== null && ! $this->esErrorFormula((string) $cache)) {
                return $cache;
            }

            try {
                return $celda->getCalculatedValue();
            } catch (\Throwable) {
                return null;
            }
        }

        return $celda->getValue();
    }

    private function buscarProducto(string $codigo): ?Producto
    {
        if ($codigo === '' || in_array($this->normalizar($codigo), ['IGV', 'SIN IGV'], true)) {
            return null;
        }

        return Producto::query()
            ->with('unidadMedida')
            ->where('estado', true)
            ->whereRaw('UPPER(TRIM(codigo)) = ?', [mb_strtoupper(trim($codigo))])
            ->first();
    }

    private function tipoCosto(?string $grupo, string $unidadOriginal): string
    {
        $texto = $this->normalizar(($grupo ?? '') . ' ' . $unidadOriginal);
        if (str_contains($texto, 'PAGO DE PERSONAL') || str_contains($texto, 'PLAME') || str_contains($texto, 'AFP')) {
            return 'MANO_OBRA';
        }
        if (str_contains($texto, 'SERVICIO') || str_contains($texto, 'ALQUILER')) {
            return 'SERVICIO_TERCERO';
        }

        return 'MATERIAL';
    }

    private function unidadPara(string $tipo, string $original, ?Producto $producto): string
    {
        if ($tipo === 'MATERIAL' && $producto) {
            return CotizacionPresupuesto::unidadDeProducto($producto) ?? 'UNIDAD';
        }

        $unidad = $this->normalizar($original);
        $mapeo = [
            'HORA' => 'HORA', 'HORAS' => 'HORA', 'HR' => 'HORA',
            'DIA' => 'DIA', 'DIAS' => 'DIA', 'JORNADA' => 'DIA',
            'SERVICIO' => 'SERVICIO', 'GLOBAL' => 'GLOBAL', 'GLB' => 'GLOBAL',
        ];

        if ($tipo === 'MANO_OBRA') {
            return in_array($mapeo[$unidad] ?? null, ['HORA', 'DIA'], true)
                ? $mapeo[$unidad]
                : 'DIA';
        }
        if ($tipo === 'SERVICIO_TERCERO') {
            return in_array($mapeo[$unidad] ?? null, ['HORA', 'DIA', 'SERVICIO', 'GLOBAL'], true)
                ? $mapeo[$unidad]
                : 'SERVICIO';
        }

        return 'UNIDAD';
    }

    private function esTituloGrupo(float $cantidad, float $unitario, float $total, string $descripcion): bool
    {
        if ($cantidad > 0 || $unitario > 0 || $total > 0 || $descripcion === '' || $this->esErrorFormula($descripcion)) {
            return false;
        }

        $texto = $this->normalizar($descripcion);
        return ! str_starts_with($texto, 'TOTAL')
            && $texto !== 'COSTOS'
            && ! str_starts_with($texto, 'RENTA');
    }

    private function texto(mixed $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) ($valor ?? '')) ?? '');
    }

    private function numero(mixed $valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    private function esErrorFormula(string $valor): bool
    {
        return str_starts_with(trim($valor), '#');
    }

    private function normalizar(string $valor): string
    {
        $valor = mb_strtoupper(trim($valor));
        return strtr($valor, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);
    }
}
