<?php

namespace App\Services\Ventas;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FormatoCotizacionExcel
{
    public const MARCA = 'HIDROIL_COSTEO_V1';
    public const CAMPOS = ['tipo_costo', 'unidad', 'moneda', 'tipo_cambio', 'costo_unitario', 'margen_porcentaje',
        'carga_social_porcentaje', 'igv_modo', 'igv_porcentaje', 'igv_venta_porcentaje', 'observacion'];

    public static function firma(Worksheet $hoja, int $fila): string
    {
        $valores = [];
        foreach (['A', 'B', 'C', 'D', 'F', 'G', 'H', 'N'] as $col) {
            $celda = $hoja->getCell($col.$fila);
            $valor = $celda->isFormula() ? ($celda->getOldCalculatedValue() ?? $celda->getCalculatedValue()) : $celda->getValue();
            $valores[] = in_array($col, ['B', 'F', 'G', 'H', 'N'], true) && is_numeric($valor)
                ? sprintf('%.8F', (float) $valor) : trim((string) $valor);
        }
        return hash('sha256', json_encode($valores, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** Datos auxiliares para recuperar moneda, cargas y modo IGV originales sin perder precisión. */
    public static function recuperar(Worksheet $hoja, int $fila): ?array
    {
        if ($hoja->getCell('V1')->getValue() !== self::MARCA) {
            return null;
        }
        $texto = $hoja->getCell('V'.$fila)->getValue();
        if (! is_string($texto) || strlen($texto) > 16000) {
            return null;
        }
        $datos = json_decode($texto, true);
        if (! is_array($datos) || ($datos['firma'] ?? null) !== self::firma($hoja, $fila)) {
            return null;
        }
        if (! is_array($datos['titulos'] ?? null) || count($datos['titulos']) > 12) {
            return null;
        }
        foreach ($datos['titulos'] as $referencia => $titulo) {
            if (! preg_match('/^C[1-9][0-9]{0,4}$/', (string) $referencia)
                || $hoja->getCell($referencia)->getValue() !== $titulo) {
                return null;
            }
        }
        return validator($datos, [
            'tipo_costo' => 'required|in:MATERIAL,MANO_OBRA,SERVICIO_TERCERO,TRANSPORTE,VIATICOS,OTRO',
            'unidad' => 'required|string|max:20', 'moneda' => 'required|in:PEN,USD',
            'tipo_cambio' => 'required|numeric|gte:0.1|max:100', 'costo_unitario' => 'required|numeric|gte:0|max:999999.9999',
            'margen_porcentaje' => 'required|numeric|gte:0|max:999.9999', 'carga_social_porcentaje' => 'required|numeric|gte:0|max:999.9999',
            'igv_modo' => 'required|in:AGREGAR,INCLUIDO,NO_APLICA',
            'igv_porcentaje' => ['required', 'numeric', function ($atributo, $valor, $fallar): void {
                if (! in_array((float) $valor, [0.0, 18.0], true)) {
                    $fallar('El porcentaje de IGV no corresponde a las reglas del sistema.');
                }
            }],
            'igv_venta_porcentaje' => 'required|numeric|between:18,18', 'observacion' => 'nullable|string|max:500',
            'ruta_areas' => 'required|array|min:1|max:12', 'ruta_areas.*' => 'required|string|max:150',
        ])->validate();
    }
}
