<?php

namespace App\Services\Ventas;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Presentación de la hoja interna; no cambia los valores ni las fórmulas exportadas. */
class EstiloCotizacionCosteoExcel
{
    public static function aplicar(Worksheet $hoja, int $filaFinal): void
    {
        $relleno = static function (string $rango, string $color) use ($hoja): void {
            $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
        };

        // Título y bandas de contexto del libro del ingeniero.
        $relleno('A1:U1', 'FFF0F3F8');
        $hoja->getStyle('A1:U1')->getFont()->setSize(10)->getColor()->setARGB('FF334155');
        $relleno('A2:U2', 'FF7030A0');
        $hoja->getStyle('A2:U2')->getFont()->setSize(16)->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $hoja->getStyle('A2:U2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $relleno('A3:U3', 'FFE8F1F8');
        $hoja->getStyle('A3:U3')->getFont()->setSize(10)->getColor()->setARGB('FF334155');
        $hoja->getRowDimension(1)->setRowHeight(22);
        $hoja->getRowDimension(2)->setRowHeight(36);
        $hoja->getRowDimension(3)->setRowHeight(23);

        // Una banda para identificación, otra para PEN y otra para USD.
        $relleno('A4:E4', 'FF00B050');
        $relleno('F4:G4', 'FFFFFF00');
        $relleno('H4', 'FF00B0F0');
        $relleno('I4:M4', 'FFFFFF00');
        $relleno('N4', 'FF00B0F0');
        $relleno('O4:U4', 'FF92D050');
        $hoja->getStyle('A4:U4')->getFont()->setBold(true)->setSize(10);
        $hoja->getStyle('A4:U4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getRowDimension(4)->setRowHeight(58);

        // Los valores de las partidas ya están calculados; solo se mejora su lectura.
        $areas = [];
        foreach ($hoja->getMergeCells() as $rango) {
            if (preg_match('/^C(\d+):M\1$/', $rango, $coincidencia)) {
                $areas[(int) $coincidencia[1]] = true;
            }
        }
        for ($fila = 5; $fila <= $filaFinal; $fila++) {
            if (isset($areas[$fila])) {
                $esArea = $hoja->getStyle('A'.$fila)->getFill()->getStartColor()->getARGB() === 'FF0070C0';
                $hoja->getRowDimension($fila)->setRowHeight($esArea ? 25 : 21);
                $hoja->getStyle('A'.$fila.':U'.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if ($esArea) {
                    $hoja->getStyle('A'.$fila.':U'.$fila)->getFont()->getColor()->setARGB('FFFFFFFF');
                }
                continue;
            }
            if ($hoja->getCell('V'.$fila)->getValue() !== null) {
                $hoja->getRowDimension($fila)->setRowHeight(25);
                $hoja->getStyle('A'.$fila.':U'.$fila)->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFDDE5EC');
                foreach (['H', 'N'] as $col) {
                    $relleno($col.$fila, 'FF002060');
                    $hoja->getStyle($col.$fila)->getFont()->setBold(true)->getColor()->setARGB('FFFFD3D3');
                }
                continue;
            }
            $titulo = (string) $hoja->getCell('C'.$fila)->getValue();
            if (str_starts_with($titulo, 'TOTAL ')) {
                $relleno('A'.$fila.':U'.$fila, $titulo === 'TOTAL GENERAL' ? 'FFDCECF8' : 'FFE8F1F8');
                $hoja->getStyle('A'.$fila.':U'.$fila)->getFont()->setBold(true);
                $hoja->getRowDimension($fila)->setRowHeight($titulo === 'TOTAL GENERAL' ? 26 : 23);
            } elseif ($titulo === '' && $fila < $filaFinal) {
                $hoja->getRowDimension($fila)->setRowHeight(9);
            }
        }

        $hoja->getStyle('N5:N'.$filaFinal)->getNumberFormat()->setFormatCode('0.00');
        foreach (['F', 'G', 'I', 'J', 'K', 'L', 'M'] as $col) {
            $hoja->getStyle($col.'5:'.$col.$filaFinal)->getNumberFormat()
                ->setFormatCode('"S/ "#,##0.00;[Red]("S/ "#,##0.00);"S/ "0.00');
        }
        foreach (['O', 'P', 'Q', 'R', 'S', 'T', 'U'] as $col) {
            $hoja->getStyle($col.'5:'.$col.$filaFinal)->getNumberFormat()
                ->setFormatCode('"US$ "#,##0.00;[Red]("US$ "#,##0.00);"US$ "0.00');
        }
        $anchos = ['A' => 15, 'B' => 10, 'C' => 58, 'D' => 13, 'E' => 25,
            'F' => 18, 'G' => 17, 'H' => 9, 'I' => 19, 'J' => 19, 'K' => 17, 'L' => 17, 'M' => 17,
            'N' => 9, 'O' => 18, 'P' => 17, 'Q' => 19, 'R' => 19, 'S' => 17, 'T' => 17, 'U' => 17];
        foreach ($anchos as $col => $ancho) {
            $hoja->getColumnDimension($col)->setWidth($ancho);
        }
    }
}
