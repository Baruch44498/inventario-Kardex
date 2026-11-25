<?php

namespace App\Services\Ordenes;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExportarGastoRealOrdenService
{
    /** La vista y el archivo muestran exactamente la misma instantánea calculada. */
    public function secciones(array $reporte): array
    {
        $t = $reporte['totales'];
        return [
            ['titulo' => 'Resumen', 'columnas' => ['concepto' => 'Concepto', 'importe' => 'Importe PEN'], 'filas' => [
                ['concepto' => 'Materiales estimados de la orden consultada (desglose)', 'importe' => $t['materiales_estimados']],
                ['concepto' => 'Materiales reales registrados', 'importe' => $t['materiales_reales']],
                ['concepto' => 'Otros costos estimados', 'importe' => $t['otros_estimados']],
                ['concepto' => 'Otros costos reales registrados', 'importe' => $t['otros_reales']],
                ['concepto' => 'Costo total estimado', 'importe' => $t['costo_estimado']],
                ['concepto' => 'Costo total real registrado', 'importe' => $t['costo_real']],
                ['concepto' => 'Diferencia: real menos estimado', 'importe' => $t['diferencia']],
                ['concepto' => 'Venta neta cotizada (no implica cobro)', 'importe' => $t['ingreso']],
                ['concepto' => 'Utilidad estimada', 'importe' => $t['utilidad_estimada']],
                ['concepto' => 'Utilidad sobre gasto registrado, provisional', 'importe' => $t['utilidad_registrada']],
            ]],
            ['titulo' => 'Áreas', 'columnas' => [
                'orden' => 'Orden', 'area' => 'Área',
                'estimado' => 'Material estimado PEN', 'real' => 'Material real PEN',
                'diferencia' => 'Diferencia material PEN',
                'otros_estimados' => 'Otros estimados PEN', 'otros_reales' => 'Otros reales PEN',
                'total_estimado' => 'Total estimado PEN', 'total_real' => 'Total real PEN',
                'diferencia_total' => 'Diferencia total PEN',
                'criterio' => 'Criterio del estimado',
            ], 'filas' => $reporte['areas']],
            ['titulo' => 'Materiales', 'columnas' => [
                'orden' => 'Orden',
                'area' => 'Área',
                'codigo' => 'Código',
                'producto' => 'Producto',
                'unidad' => 'Unidad',
                'estimado' => 'Cantidad estimada',
                'salida' => 'Salida bruta',
                'retorno' => 'Retorno reutilizable',
                'malogrado' => 'Malogrado (incluido)',
                'real' => 'Consumo real',
                'diferencia' => 'Diferencia cantidad',
                'costo_estimado' => 'Estimado PEN',
                'costo_salida' => 'Salidas PEN',
                'costo_retorno' => 'Retornos PEN',
                'costo_malogrado' => 'Malogrados PEN (informativo)',
                'costo_real' => 'Real PEN',
                'diferencia_costo' => 'Diferencia PEN',
            ], 'filas' => $reporte['materiales']],
            ['titulo' => 'Otros estimados', 'columnas' => ['orden' => 'Orden', 'area' => 'Área presupuestada', 'tipo' => 'Tipo', 'descripcion' => 'Descripción', 'ejecucion' => 'Ejecución servicio', 'importe' => 'Estimado PEN'], 'filas' => $reporte['otros_estimados']],
            ['titulo' => 'Otros reales', 'columnas' => ['orden' => 'Orden', 'area' => 'Asignación', 'tipo' => 'Tipo', 'descripcion' => 'Descripción', 'documento' => 'Documento', 'fecha' => 'Fecha', 'importe' => 'Real registrado PEN'], 'filas' => $reporte['otros_reales']],
            ['titulo' => 'OS internas por área', 'columnas' => [
                'orden' => 'OS interna', 'area' => 'Área de origen en la orden principal',
                'servicio' => 'Servicio cotizado', 'estimado' => 'Servicio estimado PEN',
                'materiales_reales' => 'Material real OS PEN', 'otros_reales' => 'Otros gastos OS PEN',
                'total_real' => 'Gasto real OS PEN', 'diferencia' => 'Diferencia PEN',
            ], 'filas' => $reporte['servicios_internos']],
            ['titulo' => 'Órdenes', 'columnas' => ['codigo' => 'Orden', 'estado' => 'Estado', 'materiales' => 'Material real PEN', 'directos' => 'Costo directo PEN', 'cierre_guardado' => 'Cierre propio guardado PEN'], 'filas' => $reporte['ordenes']],
            ['titulo' => 'Movimientos', 'columnas' => ['orden' => 'Orden', 'area' => 'Área', 'codigo' => 'Código producto', 'producto' => 'Producto', 'documento' => 'Nota', 'origen' => 'Salida origen', 'fecha' => 'Fecha', 'tipo' => 'Movimiento', 'cantidad' => 'Cantidad', 'importe' => 'Importe histórico PEN'], 'filas' => $reporte['movimientos']],
        ];
    }

    public function libro(array $reporte): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('HIDROIL')->setTitle('Gasto real ' . $reporte['orden']);
        foreach ($this->secciones($reporte) as $indice => $seccion) {
            $hoja = $indice === 0 ? $libro->getActiveSheet() : $libro->createSheet();
            $hoja->setTitle($seccion['titulo']);
            $ultimaColumna = Coordinate::stringFromColumnIndex(count($seccion['columnas']));
            $hoja->setCellValueExplicit('A1', $reporte['orden'] . ' — ' . $seccion['titulo'], DataType::TYPE_STRING);
            $hoja->mergeCells('A1:' . $ultimaColumna . '1');
            $hoja->setCellValueExplicit('A2', 'PEN · Consulta ' . $reporte['generado_en'] . ' · Incluye OS internas no anuladas', DataType::TYPE_STRING);
            $hoja->mergeCells('A2:' . $ultimaColumna . '2');
            foreach (array_values($seccion['columnas']) as $col => $titulo) {
                $hoja->setCellValueExplicit(Coordinate::stringFromColumnIndex($col + 1) . '4', $titulo, DataType::TYPE_STRING);
            }
            foreach ($seccion['filas'] as $indiceFila => $fila) {
                foreach (array_keys($seccion['columnas']) as $col => $clave) {
                    $celda = Coordinate::stringFromColumnIndex($col + 1) . ($indiceFila + 5);
                    $valor = $fila[$clave] ?? null;
                    // No interpretar códigos, descripciones o documentos como fórmulas.
                    $esNumero = is_float($valor) || is_int($valor);
                    $hoja->setCellValueExplicit($celda, $valor ?? 'N/D', $esNumero ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    if ($esNumero) {
                        $hoja->getStyle($celda)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);0.00');
                    }
                }
            }
            $ultimaFila = max(4, count($seccion['filas']) + 4);
            $hoja->getStyle('A1:' . $ultimaColumna . '1')->getFont()->setBold(true)->setSize(15);
            $hoja->getStyle('A4:' . $ultimaColumna . '4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $hoja->getStyle('A4:' . $ultimaColumna . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF086F98');
            $hoja->getStyle('A4:' . $ultimaColumna . $ultimaFila)->getAlignment()->setWrapText(true);
            $hoja->getRowDimension(4)->setRowHeight(42);
            foreach (array_keys($seccion['columnas']) as $col => $clave) {
                $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($col + 1))
                    ->setWidth($clave === 'criterio' ? 62 : (in_array($clave, ['area', 'producto', 'descripcion', 'concepto'], true) ? 42 : 21));
            }
            $hoja->freezePane('C5');
            $hoja->setAutoFilter('A4:' . $ultimaColumna . $ultimaFila);
            $hoja->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        }
        $notas = $libro->createSheet()->setTitle('Criterios');
        $criterios = array_merge($reporte['avisos'], [
            'Consumo real = salidas confirmadas de consumo − retornos reutilizables confirmados vinculados a esas salidas.',
            'Diferencia = real − estimado. Un valor positivo significa mayor consumo o costo registrado.',
            'N/D = no disponible; no equivale a cero. En órdenes antiguas puede faltar la valorización estimada por área.',
            'Los costos se agrupan por orden y área vinculada; la diferencia total compara importes, no presume que partidas individuales correspondan entre sí. Los gastos sin área permanecen separados.',
            'La venta neta de la orden principal se cuenta una sola vez. Las OS internas no heredan otra venta.',
            'OS internas por área de origen compara el servicio presupuestado con el gasto de su OS. Es un desglose: sus importes ya figuran en los totales generales y no se suman nuevamente.',
            'Cierre propio guardado corresponde al cierre histórico individual, no al total consolidado actual. Este reporte no modifica cierres.',
            'Archivo de consulta: conserva valores numéricos históricos con precisión interna y muestra dos decimales. No es una plantilla de importación.',
        ]);
        foreach ($criterios as $i => $criterio) {
            $notas->setCellValueExplicit('A' . ($i + 1), $criterio, DataType::TYPE_STRING);
            $notas->getRowDimension($i + 1)->setRowHeight(45);
        }
        $notas->getColumnDimension('A')->setWidth(120);
        $notas->getStyle('A1:A' . count($criterios))->getAlignment()->setWrapText(true);
        (new HojaExcelGastoFinalService())->agregar($libro, $reporte);
        return $libro;
    }
}
