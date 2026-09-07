<?php

namespace App\Services\Ordenes;

use App\Models\CostoDirectoOrden;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HojaExcelGastoFinalService
{
    public function agregar(Spreadsheet $libro, array $reporte): void
    {
        $hoja = $libro->createSheet(0)->setTitle('Gasto real');
        $this->titulo($hoja, 1, 'GASTO REAL DE EJECUCIÓN', '086F98');
        $this->texto($hoja, 'A2', $reporte['orden'].' · Consulta '.$reporte['generado_en']);
        $hoja->mergeCells('A2:H2');
        $estados = array_column($reporte['ordenes'], 'estado');
        $cerradas = $estados !== [] && count(array_filter($estados, fn ($e) => $e === 'CERRADA')) === count($estados);
        $estado = in_array('ANULADA', $estados, true)
            ? 'ORDEN ANULADA · consulta histórica'
            : ($cerradas ? 'ORDEN Y OS CERRADAS · gasto registrado a la fecha' : 'PROVISIONAL · hay órdenes pendientes de cierre');
        $this->texto($hoja, 'A3', $estado);
        $hoja->mergeCells('A3:H3');
        foreach ([5 => 'Materiales consumidos', 6 => 'Personal, servicios y otros costos registrados', 7 => 'TOTAL REAL REGISTRADO'] as $fila => $nombre) {
            $this->texto($hoja, 'A'.$fila, $nombre);
            $hoja->mergeCells('A'.$fila.':E'.$fila);
        }
        $this->texto($hoja, 'A9', 'PEN · Consumo = salidas − retornos reutilizables. Los malogrados ya están incluidos en el gasto.');
        $hoja->mergeCells('A9:H9');
        foreach (['A' => 'Código', 'B' => 'Descripción', 'C' => 'Unidad', 'D' => 'Cantidad real',
            'E' => 'Costo unitario real PEN', 'F' => 'Costo total real PEN', 'G' => 'Malogrado (incluido)', 'H' => 'Documento / referencia'] as $col => $nombre) {
            $this->texto($hoja, $col.'11', $nombre);
        }
        $fila = 12;
        $subtotalesMateriales = [];
        $subtotalesOtros = [];
        $materiales = collect($reporte['materiales'])->filter(fn ($m) =>
            (float) $m['salida'] !== 0.0 || (float) $m['retorno'] !== 0.0 || (float) $m['costo_real'] !== 0.0
        );
        foreach ($materiales->groupBy('area_clave') as $grupo) {
            $this->titulo($hoja, $fila++, $grupo->first()['orden'].' · '.$grupo->first()['area'], 'E7F2F8');
            $inicio = $fila;
            foreach ($grupo as $material) {
                $this->texto($hoja, 'A'.$fila, (string) $material['codigo']);
                $this->texto($hoja, 'B'.$fila, (string) $material['producto']);
                $this->texto($hoja, 'C'.$fila, (string) $material['unidad']);
                $this->numero($hoja, 'D'.$fila, (float) $material['real']);
                $this->numero($hoja, 'F'.$fila, (float) $material['costo_real']);
                $hoja->setCellValueExplicit('E'.$fila, '=IF(D'.$fila.'=0,"N/D",F'.$fila.'/D'.$fila.')', DataType::TYPE_FORMULA);
                $this->numero($hoja, 'G'.$fila, (float) $material['malogrado']);
                $this->texto($hoja, 'H'.$fila, 'Detalle en Movimientos');
                $hoja->getRowDimension($fila)->setOutlineLevel(1)->setRowHeight(36);
                $fila++;
            }
            $subtotalesMateriales[] = $this->subtotal($hoja, $fila, $inicio, 'Subtotal de materiales del área');
            $fila += 2;
        }
        foreach (collect($reporte['otros_reales'])->groupBy(fn ($c) => $c['orden'].'|'.$c['tipo']) as $grupo) {
            $primero = $grupo->first();
            $tipo = CostoDirectoOrden::TIPOS[$primero['tipo']] ?? $primero['tipo'];
            $this->titulo($hoja, $fila++, $primero['orden'].' · '.$tipo.' · sin área registrada', 'E5F3EC');
            $inicio = $fila;
            foreach ($grupo as $costo) {
                $this->texto($hoja, 'B'.$fila, (string) $costo['descripcion']);
                $this->texto($hoja, 'C'.$fila, (string) ($costo['unidad'] ?? 'N/D'));
                if (isset($costo['cantidad'])) {
                    $this->numero($hoja, 'D'.$fila, (float) $costo['cantidad']);
                    $hoja->setCellValueExplicit('E'.$fila, '=IF(D'.$fila.'=0,"N/D",F'.$fila.'/D'.$fila.')', DataType::TYPE_FORMULA);
                } else {
                    $this->texto($hoja, 'D'.$fila, 'N/D');
                    $this->texto($hoja, 'E'.$fila, 'N/D');
                }
                $this->numero($hoja, 'F'.$fila, (float) $costo['importe']);
                $this->texto($hoja, 'H'.$fila, trim(($costo['documento'] ?? '').' · '.($costo['fecha'] ?? ''), ' ·'));
                $hoja->getRowDimension($fila)->setOutlineLevel(1)->setRowHeight(36);
                $fila++;
            }
            $subtotalesOtros[] = $this->subtotal($hoja, $fila, $inicio, 'Subtotal de '.$tipo);
            $fila += 2;
        }
        foreach ([5 => $subtotalesMateriales, 6 => $subtotalesOtros] as $r => $referencias) {
            $hoja->setCellValueExplicit('F'.$r, $referencias === [] ? '=0' : '=SUM('.implode(',', $referencias).')', DataType::TYPE_FORMULA);
        }
        $hoja->setCellValueExplicit('F7', '=SUM(F5:F6)', DataType::TYPE_FORMULA);
        if ($subtotalesMateriales === [] && $subtotalesOtros === []) {
            $this->texto($hoja, 'A'.$fila, 'Sin movimientos de consumo ni costos reales registrados.');
            $hoja->mergeCells('A'.$fila.':H'.$fila);
            $fila += 2;
        }
        foreach ([
            'Incluye las OS internas no anuladas. Cada gasto se cuenta una sola vez.',
            'El costo unitario es el costo real neto dividido por la cantidad real neta; no es el precio actual de compra.',
            'Solo incluye gastos registrados. Personal, servicios y otros costos pendientes no se sustituyen por estimados.',
            'El estado cerrado no certifica que todos los gastos estén registrados. Esta consulta no reemplaza ni modifica el cierre histórico.',
            'Las demás hojas conservan la comparación con lo presupuestado y los documentos de origen.',
        ] as $nota) {
            $this->texto($hoja, 'A'.$fila, $nota);
            $hoja->mergeCells('A'.$fila.':H'.$fila);
            $hoja->getRowDimension($fila++)->setRowHeight(30);
        }
        $hoja->getStyle('A1:H'.$fila)->getAlignment()->setWrapText(true)->setVertical('center');
        $hoja->getStyle('D5:G'.$fila)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);0.00');
        $hoja->getStyle('A11:H11')->getFont()->setBold(true);
        $hoja->getStyle('A11:H11')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE7F2F8');
        $hoja->getStyle('A5:F7')->getFont()->setBold(true);
        $hoja->getStyle('A7:F7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5F3EC');
        $hoja->getStyle('F7')->getFont()->setSize(15);
        $hoja->getRowDimension(1)->setRowHeight(30);
        $hoja->getRowDimension(3)->setRowHeight(27);
        $hoja->getRowDimension(9)->setRowHeight(30);
        $hoja->getRowDimension(11)->setRowHeight(40);
        foreach (['A' => 16, 'B' => 48, 'C' => 13, 'D' => 16, 'E' => 19, 'F' => 21, 'G' => 17, 'H' => 29] as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
        $hoja->freezePane('D12');
        $hoja->setShowGridlines(false);
        $hoja->setShowSummaryBelow(true);
        $hoja->getPageSetup()->setOrientation('landscape')->setPaperSize(9)->setFitToWidth(1)->setFitToHeight(0);
        $hoja->getPageSetup()->setPrintArea('A1:H'.($fila - 1))->setRowsToRepeatAtTopByStartAndEnd(1, 3);
        $hoja->getHeaderFooter()->setOddFooter('&L'.$reporte['generado_en'].'&RPágina &P de &N');
        $libro->setActiveSheetIndex(0);
    }

    private function texto(Worksheet $hoja, string $celda, string $valor): void
    {
        $hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
    }

    private function numero(Worksheet $hoja, string $celda, float $valor): void
    {
        $hoja->setCellValueExplicit($celda, $valor, DataType::TYPE_NUMERIC);
    }

    private function titulo(Worksheet $hoja, int $fila, string $titulo, string $color): void
    {
        $this->texto($hoja, 'A'.$fila, $titulo);
        $hoja->mergeCells('A'.$fila.':H'.$fila);
        $hoja->getStyle('A'.$fila.':H'.$fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$color);
        $hoja->getStyle('A'.$fila.':H'.$fila)->getFont()->setBold(true);
        if ($fila === 1) {
            $hoja->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');
        }
        $hoja->getRowDimension($fila)->setRowHeight(36);
    }

    private function subtotal(Worksheet $hoja, int $fila, int $inicio, string $titulo): string
    {
        $this->texto($hoja, 'B'.$fila, $titulo);
        $hoja->mergeCells('B'.$fila.':E'.$fila);
        $hoja->setCellValueExplicit('F'.$fila, '=SUM(F'.$inicio.':F'.($fila - 1).')', DataType::TYPE_FORMULA);
        $hoja->getStyle('B'.$fila.':F'.$fila)->getFont()->setBold(true);
        $hoja->getRowDimension($fila)->setRowHeight(27);
        return 'F'.$fila;
    }
}
