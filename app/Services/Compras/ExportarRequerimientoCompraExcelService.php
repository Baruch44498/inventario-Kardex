<?php

namespace App\Services\Compras;

use App\Models\Requisicion;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportarRequerimientoCompraExcelService
{
    public function libro(Requisicion $requerimiento): Spreadsheet
    {
        $requerimiento->loadMissing([
            'ordenOperacion.cliente',
            'solicitante',
            'detalles.producto.unidadMedida',
        ]);

        $libro = new Spreadsheet();
        $libro->getProperties()
            ->setCreator('HIDROIL S.A.C.')
            ->setTitle('Requerimiento de compra '.$requerimiento->codigo)
            ->setSubject('Solicitud administrativa de productos para abastecimiento');

        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Requerimiento');
        $hoja->setShowGridlines(false);

        $hoja->mergeCells('A1:B3');
        $hoja->mergeCells('C1:E2');
        $hoja->mergeCells('C3:E3');
        $hoja->mergeCells('D4:E4');

        $hoja->setCellValue('C1', 'REQUERIMIENTO DE COMPRA');
        $hoja->setCellValue('C3', $requerimiento->codigo);
        $hoja->setCellValue('A4', 'FECHA:');
        if ($requerimiento->fecha_solicitud) {
            $hoja->setCellValue('B4', Date::PHPToExcel($requerimiento->fecha_solicitud));
        }
        $hoja->setCellValue('C4', 'CLIENTE:');
        $hoja->setCellValue('D4', $this->clienteVisible($requerimiento));

        foreach (['A5' => 'ITEM', 'B5' => 'CÓDIGO', 'C5' => 'DESCRIPCIÓN DEL PRODUCTO', 'D5' => 'UM', 'E5' => 'PEDIDO'] as $celda => $titulo) {
            $hoja->setCellValue($celda, $titulo);
        }

        $fila = 6;
        foreach ($requerimiento->detalles as $indice => $detalle) {
            $producto = $detalle->producto;
            $unidad = $producto?->unidadMedida;

            $hoja->setCellValue('A'.$fila, $indice + 1);
            $hoja->setCellValueExplicit('B'.$fila, (string) ($producto?->codigo ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit('C'.$fila, (string) ($producto?->descripcion ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit(
                'D'.$fila,
                mb_strtoupper((string) ($unidad?->nombre ?: $unidad?->codigo ?: '')),
                DataType::TYPE_STRING
            );
            $hoja->setCellValue('E'.$fila, (float) $detalle->cantidad_solicitada);
            $fila++;
        }

        $ultimaFila = max(6, $fila - 1);
        if ($requerimiento->detalles->isEmpty()) {
            $hoja->mergeCells('A6:E6');
            $hoja->setCellValue('A6', 'Sin productos registrados.');
        }

        $this->aplicarLogo($hoja);
        $this->aplicarFormato($hoja, $ultimaFila, $requerimiento);

        return $libro;
    }

    private function clienteVisible(Requisicion $requerimiento): string
    {
        $cliente = $requerimiento->ordenOperacion?->cliente;

        if ($cliente) {
            return mb_strtoupper($cliente->nombreVisible());
        }

        return $requerimiento->origen === 'REPOSICION'
            ? 'USO INTERNO / REPOSICIÓN'
            : 'NO REGISTRADO';
    }

    private function aplicarLogo(Worksheet $hoja): void
    {
        $rutaLogo = public_path('images/logo-hidroil.png');
        if (! is_file($rutaLogo)) {
            return;
        }

        $logo = new Drawing();
        $logo->setName('Logo HIDROIL');
        $logo->setDescription('HIDROIL S.A.C.');
        $logo->setPath($rutaLogo);
        $logo->setHeight(72);
        $logo->setCoordinates('A1');
        $logo->setOffsetX(7);
        $logo->setOffsetY(5);
        $logo->setWorksheet($hoja);
    }

    private function aplicarFormato(Worksheet $hoja, int $ultimaFila, Requisicion $requerimiento): void
    {
        $azul = 'FF435F8E';
        $azulClaro = 'FFD9E2F3';
        $grisClaro = 'FFF2F4F7';
        $borde = 'FF7D8899';

        $hoja->getStyle('A1:E'.$ultimaFila)->getFont()->setName('Arial')->setSize(10);
        $hoja->getStyle('C1:E2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 24, 'color' => ['argb' => 'FF1F2937']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $hoja->getStyle('C3:E3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => $azul]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $hoja->getStyle('A4:E4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF27364A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $grisClaro]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $borde]],
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $borde]],
            ],
        ]);
        $hoja->getStyle('A5:E5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $azul]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']],
            ],
        ]);
        $hoja->getStyle('A6:E'.$ultimaFila)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $borde]],
            ],
        ]);

        for ($fila = 6; $fila <= $ultimaFila; $fila++) {
            if (($fila - 6) % 2 === 0) {
                $hoja->getStyle('A'.$fila.':E'.$fila)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($azulClaro);
            }
        }

        $hoja->getStyle('A6:B'.$ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('C6:C'.$ultimaFila)->getAlignment()->setWrapText(true);
        $hoja->getStyle('D6:E'.$ultimaFila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('E6:E'.$ultimaFila)->getNumberFormat()->setFormatCode('0.###');
        $hoja->getStyle('B4')->getNumberFormat()->setFormatCode('dd/mm/yyyy');

        $hoja->getColumnDimension('A')->setWidth(9);
        $hoja->getColumnDimension('B')->setWidth(16);
        $hoja->getColumnDimension('C')->setWidth(70);
        $hoja->getColumnDimension('D')->setWidth(16);
        $hoja->getColumnDimension('E')->setWidth(14);
        $hoja->getRowDimension(1)->setRowHeight(34);
        $hoja->getRowDimension(2)->setRowHeight(30);
        $hoja->getRowDimension(3)->setRowHeight(25);
        $hoja->getRowDimension(4)->setRowHeight(23);
        $hoja->getRowDimension(5)->setRowHeight(26);
        for ($fila = 6; $fila <= $ultimaFila; $fila++) {
            $hoja->getRowDimension($fila)->setRowHeight(24);
        }

        $hoja->freezePane('A6');
        $hoja->setAutoFilter('A5:E'.$ultimaFila);
        $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(5, 5);
        $hoja->getPageMargins()
            ->setTop(0.35)
            ->setRight(0.35)
            ->setBottom(0.45)
            ->setLeft(0.35);
        $hoja->getHeaderFooter()->setOddFooter(
            '&L'.$requerimiento->codigo.'&CSolicitado por: '.($requerimiento->solicitante?->nombreVisible() ?? 'No registrado').'&RPágina &P de &N'
        );
        $hoja->getPageSetup()->setPrintArea('A1:E'.$ultimaFila);
    }
}
