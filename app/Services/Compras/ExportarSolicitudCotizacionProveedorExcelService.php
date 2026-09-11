<?php

namespace App\Services\Compras;

use App\Models\Proveedor;
use App\Models\Requisicion;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ExportarSolicitudCotizacionProveedorExcelService
{
    /**
     * @param  Collection<int, \App\Models\RequisicionDetalle>  $detalles
     */
    public function libro(
        Requisicion $requerimiento,
        Proveedor $proveedor,
        Collection $detalles
    ): Spreadsheet {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Solicitud de cotización');

        $hoja->mergeCells('A1:D1');
        $hoja->setCellValueExplicit('A1', mb_strtoupper($proveedor->nombreVisible()), DataType::TYPE_STRING);
        $hoja->mergeCells('A2:B2');
        $hoja->setCellValueExplicit(
            'A2',
            'RUC '.$proveedor->ruc.' · Requerimiento '.$requerimiento->codigo,
            DataType::TYPE_STRING
        );
        $hoja->setCellValue('C2', 'MONEDA');
        $hoja->setCellValue('D2', 'PEN');
        $hoja->mergeCells('A3:D3');
        $hoja->setCellValue(
            'A3',
            'Proveedor: complete la moneda y únicamente la columna PRECIO UNIT + IGV. El precio unitario incluye IGV. No modifique códigos ni cantidades.'
        );

        $validacionMoneda = $hoja->getCell('D2')->getDataValidation();
        $validacionMoneda->setType('list');
        $validacionMoneda->setErrorStyle('stop');
        $validacionMoneda->setAllowBlank(false);
        $validacionMoneda->setShowDropDown(true);
        $validacionMoneda->setShowErrorMessage(true);
        $validacionMoneda->setErrorTitle('Moneda no válida');
        $validacionMoneda->setError('Selecciona PEN o USD.');
        $validacionMoneda->setFormula1('"PEN,USD"');

        foreach (['A5' => 'CODIGO', 'B5' => 'CANT.', 'C5' => 'DESCRIPCION DEL PRODUCTO', 'D5' => 'PRECIO UNIT + IGV'] as $celda => $valor) {
            $hoja->setCellValue($celda, $valor);
        }

        $fila = 6;
        foreach ($detalles as $detalle) {
            $producto = $detalle->producto;
            $unidad = $producto?->unidadMedida?->abreviatura;

            $hoja->setCellValueExplicit('A'.$fila, (string) ($producto?->codigo ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValue('B'.$fila, (float) $detalle->cantidad_solicitada);
            $hoja->setCellValueExplicit(
                'C'.$fila,
                trim((string) ($producto?->descripcion ?? '').($unidad ? ' · '.$unidad : '')),
                DataType::TYPE_STRING
            );
            $hoja->setCellValueExplicit('D'.$fila, '', DataType::TYPE_STRING);
            $fila++;
        }

        $ultimaFila = max(6, $fila - 1);
        $hoja->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['argb' => 'FF102A43']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF83E4EA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getStyle('A2:D2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF344054']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF2F4F7']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getStyle('A3:D3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF475467']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF7E6']],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getStyle('A5:D5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getStyle('A5:D'.$ultimaFila)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('FF8A94A6');
        $hoja->getStyle('B6:B'.$ultimaFila)->getNumberFormat()->setFormatCode('0.00');
        $hoja->getStyle('D6:D'.$ultimaFila)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF4CC']],
            'numberFormat' => ['formatCode' => '#,##0.00'],
        ]);
        $hoja->getStyle('A6:D'.$ultimaFila)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getStyle('C6:C'.$ultimaFila)->getAlignment()->setWrapText(true);

        $hoja->getColumnDimension('A')->setWidth(16);
        $hoja->getColumnDimension('B')->setWidth(12);
        $hoja->getColumnDimension('C')->setWidth(68);
        $hoja->getColumnDimension('D')->setWidth(22);
        $hoja->getRowDimension(1)->setRowHeight(30);
        $hoja->getRowDimension(3)->setRowHeight(28);
        $hoja->freezePane('A6');
        $hoja->setAutoFilter('A5:D'.$ultimaFila);
        $hoja->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $hoja->getPageMargins()
            ->setTop(0.35)
            ->setRight(0.35)
            ->setBottom(0.35)
            ->setLeft(0.35);
        $hoja->getHeaderFooter()->setOddFooter('&L'.$requerimiento->codigo.'&RPágina &P de &N');
        $hoja->setShowGridlines(false);

        return $libro;
    }
}
