<?php

namespace App\Services\Ventas;

use App\Models\CotizacionCliente;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportarCotizacionClienteExcelService
{
    // Equivalentes ARGB de los tokens de public/css/hidroil/base.css.
    private const NAVY = 'FF15324A';
    private const BLUE = 'FF0A6F9E';
    private const BLUE_SOFT = 'FFE8F4F9';
    private const WHITE = 'FFFFFFFF';
    private const SUBTLE = 'FFF7FAFB';
    private const TEXT = 'FF1F2933';
    private const MUTED = 'FF526170';
    private const BORDER = 'FFDFE6EC';
    private const WARNING = 'FF9A6700';
    private const WARNING_SOFT = 'FFFFF6DF';

    public function libro(CotizacionCliente $cotizacion, string $modo): Spreadsheet
    {
        if (! in_array($modo, ['detallado', 'precio-unico'], true)) {
            throw ValidationException::withMessages(['excel' => 'Modo de exportación no reconocido.']);
        }

        $cotizacion->loadMissing('detalles');
        if ($cotizacion->detalles->isEmpty() || (float) $cotizacion->total <= 0) {
            throw ValidationException::withMessages([
                'excel' => 'Sincroniza y valoriza la cotización antes de generar el Excel para el cliente.',
            ]);
        }

        $cotizacion->loadMissing('tipoOrden');
        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('HIDROIL')->setTitle('Cotización '.$cotizacion->codigo);
        $libro->getDefaultStyle()->getFont()->setName('Aptos')->setSize(10)->getColor()->setARGB(self::TEXT);
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('COTIZACION CLIENTE');
        $texto = static fn (string $celda, mixed $valor) =>
            $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
        $numero = static fn (string $celda, mixed $valor) =>
            $hoja->setCellValueExplicit($celda, (float) $valor, DataType::TYPE_NUMERIC);

        $this->cabecera($hoja, $cotizacion, $texto);

        if ($modo === 'precio-unico') {
            $texto('A6', 'CONCEPTO');
            $texto('H6', 'PRECIO FINAL ('.$cotizacion->moneda.')');
            $hoja->mergeCells('A6:G6');
            $texto('A7', $cotizacion->descripcion_trabajo ?: 'Trabajo o servicio cotizado');
            $hoja->mergeCells('A7:G7');
            $numero('H7', round((float) $cotizacion->total, 2));
            $this->estiloPartida($hoja, 7);
            $hoja->getRowDimension(7)->setRowHeight(45);
            $hoja->getStyle('A7')->getFont()->setBold(true);
            $hoja->getStyle('H7')->getFont()->setBold(true)->setSize(13);
            $texto('A9', (float) $cotizacion->impuesto > 0
                ? 'PRECIO FINAL · IGV INCLUIDO' : 'PRECIO FINAL');
            $hoja->mergeCells('A9:G9');
            $numero('H9', round((float) $cotizacion->total, 2));
            $filaTotal = 9;
        } else {
            foreach (['A' => 'CÓDIGO', 'B' => 'CONCEPTO', 'D' => 'CANTIDAD', 'E' => 'UNIDAD',
                'F' => 'P. UNITARIO SIN IGV', 'G' => 'IGV', 'H' => 'TOTAL'] as $columna => $titulo) {
                $texto($columna.'6', $titulo);
            }
            $hoja->mergeCells('B6:C6');
            $fila = 7;
            foreach ($cotizacion->detalles as $detalle) {
                $texto('A'.$fila, $detalle->codigo_producto);
                $texto('B'.$fila, $detalle->descripcion ?: $detalle->codigo_producto);
                $hoja->mergeCells('B'.$fila.':C'.$fila);
                $numero('D'.$fila, $detalle->cantidad);
                $texto('E'.$fila, $detalle->unidad_medida);
                $numero('F'.$fila, $detalle->precio_unitario);
                $numero('G'.$fila, $detalle->impuesto);
                $numero('H'.$fila, $detalle->total);
                $this->estiloPartida($hoja, $fila);
                $hoja->getRowDimension($fila)->setRowHeight(
                    mb_strlen((string) $detalle->descripcion) > 75 ? 42 : 31
                );
                $fila++;
            }
            $fila++;
            $texto('G'.$fila, 'Subtotal');
            $numero('H'.$fila, $cotizacion->subtotal);
            $hoja->getRowDimension($fila)->setRowHeight(25);
            $fila++;
            $texto('G'.$fila, 'IGV');
            $numero('H'.$fila, $cotizacion->impuesto);
            $hoja->getRowDimension($fila)->setRowHeight(25);
            $fila++;
            $texto('G'.$fila, 'TOTAL '.$cotizacion->moneda);
            $numero('H'.$fila, round((float) $cotizacion->total, 2));
            $filaTotal = $fila;

            $hoja->getStyle('D7:D'.($fila - 4))->getNumberFormat()->setFormatCode('#,##0.##');
            $hoja->getStyle('F7:F'.($fila - 4))->getNumberFormat()->setFormatCode('#,##0.00##');
            $hoja->getStyle('G7:H'.$fila)->getNumberFormat()->setFormatCode('#,##0.00');
            $hoja->getStyle('G'.($fila - 2).':H'.($fila - 1))->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'font' => ['color' => ['argb' => self::MUTED]],
            ]);
        }

        $hoja->getStyle('A6:H6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => self::NAVY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BLUE_SOFT]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BLUE]]],
        ]);
        $hoja->getStyle('D6:H6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $hoja->getStyle('H7:H'.$filaTotal)->getNumberFormat()->setFormatCode('#,##0.00');
        $hoja->getStyle('A'.$filaTotal.':H'.$filaTotal)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => self::NAVY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::BLUE_SOFT]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::BLUE]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getStyle('G'.$filaTotal.':H'.$filaTotal)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $hoja->getRowDimension(6)->setRowHeight(36);
        $hoja->getRowDimension($filaTotal)->setRowHeight(39);

        $ultimaFila = $this->condicionesYPie($hoja, $cotizacion, $filaTotal, $texto);
        $this->configurarHoja($hoja, $cotizacion, $modo, $ultimaFila);

        return $libro;
    }

    private function cabecera(Worksheet $hoja, CotizacionCliente $cotizacion, callable $texto): void
    {
        $hoja->getStyle('A1:H5')->getFont()->setName('Aptos')->setSize(10)->getColor()->setARGB(self::TEXT);
        $hoja->mergeCells('A1:C2');
        $logo = public_path('images/logo-hidroil.png');
        if (is_file($logo)) {
            $dibujo = new Drawing();
            $dibujo->setName('HIDROIL');
            $dibujo->setDescription('Logo HIDROIL');
            $dibujo->setPath($logo);
            $dibujo->setHeight(70);
            $dibujo->setCoordinates('A1');
            $dibujo->setOffsetX(8);
            $dibujo->setOffsetY(2);
            $dibujo->setWorksheet($hoja);
        } else {
            $texto('A1', 'HIDROIL');
            $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(20)->getColor()->setARGB(self::NAVY);
        }

        $hoja->mergeCells('D1:H1');
        $texto('D1', 'DOCUMENTO COMERCIAL · VERSIÓN '.$cotizacion->version);
        $hoja->getStyle('D1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => self::MUTED]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_BOTTOM],
        ]);
        $hoja->mergeCells('D2:H2');
        $texto('D2', 'COTIZACIÓN '.$cotizacion->codigo);
        $hoja->getStyle('D2:H2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 19, 'color' => ['argb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getRowDimension(1)->setRowHeight(39);
        $hoja->getRowDimension(2)->setRowHeight(40);

        $estado = match ($cotizacion->estado) {
            'ABIERTA' => 'BORRADOR',
            'ANULADA' => 'ANULADA',
            'CONVERTIDA_EN_ORDEN' => 'APROBADA',
            default => 'CERRADA',
        };
        $hoja->mergeCells('A3:G3');
        if ($cotizacion->estado === 'ABIERTA') {
            $texto('A3', 'Borrador sujeto a revisión. Los importes aún pueden cambiar.');
        } elseif ($cotizacion->estado === 'ANULADA') {
            $texto('A3', 'Documento anulado. Se conserva como referencia histórica.');
        }
        $texto('H3', $estado);
        $colorEstado = in_array($cotizacion->estado, ['ABIERTA', 'ANULADA'], true)
            ? self::WARNING : self::BLUE;
        $hoja->getStyle('A3:H3')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' =>
                $colorEstado === self::WARNING ? self::WARNING_SOFT : self::SUBTLE]],
            'font' => ['bold' => true, 'size' => 9, 'color' => ['argb' => $colorEstado]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BLUE]]],
        ]);
        $hoja->getStyle('H3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $hoja->getRowDimension(3)->setRowHeight(29);

        foreach ([
            ['A4:B4', 'A4', 'CLIENTE', $cotizacion->cliente_nombre],
            ['C4:D4', 'C4', 'DOCUMENTO', $cotizacion->cliente_documento ?: 'No indicado'],
            ['E4:F4', 'E4', 'FECHA DE EMISIÓN', $cotizacion->fecha_emision?->format('d/m/Y') ?: 'No indicada'],
            ['G4:H4', 'G4', 'MONEDA', $cotizacion->moneda],
        ] as [$rango, $celda, $etiqueta, $valor]) {
            $hoja->mergeCells($rango);
            $texto($celda, $etiqueta."\n".$valor);
            $hoja->getStyle($rango)->applyFromArray([
                'font' => ['size' => 10, 'color' => ['argb' => self::NAVY]],
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER]]],
            ]);
        }
        $hoja->getRowDimension(4)->setRowHeight(48);
        $hoja->mergeCells('A5:G5');
        $texto('A5', 'PROPUESTA · '.($cotizacion->descripcion_trabajo ?: 'Trabajo o servicio cotizado'));
        $texto('H5', $cotizacion->tipoOrden?->codigo ?: '');
        $hoja->getStyle('A5:H5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => self::NAVY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $hoja->getStyle('H5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $hoja->getRowDimension(5)->setRowHeight(36);
    }

    private function estiloPartida(Worksheet $hoja, int $fila): void
    {
        $hoja->getStyle('A'.$fila.':H'.$fila)->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' =>
                $fila % 2 === 0 ? self::SUBTLE : self::WHITE]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER]]],
        ]);
        $hoja->getStyle('B'.$fila.':C'.$fila)->getFont()->setBold(true);
        $hoja->getStyle('D'.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $hoja->getStyle('F'.$fila.':H'.$fila)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function condicionesYPie(Worksheet $hoja, CotizacionCliente $cotizacion, int $filaTotal, callable $texto): int
    {
        $fila = $filaTotal + 2;
        foreach (['Condiciones de pago' => $cotizacion->condiciones_pago,
            'Condiciones de entrega' => $cotizacion->condiciones_entrega] as $titulo => $valor) {
            if (! $valor) {
                continue;
            }
            $hoja->mergeCells('A'.$fila.':H'.$fila);
            $texto('A'.$fila, mb_strtoupper($titulo).' · '.$valor);
            $hoja->getStyle('A'.$fila.':H'.$fila)->applyFromArray([
                'font' => ['size' => 9, 'color' => ['argb' => self::MUTED]],
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $hoja->getRowDimension($fila)->setRowHeight(max(26, min(78, 26 * (int) ceil(mb_strlen($valor) / 100))));
            $fila++;
        }
        $fila++;
        $hoja->mergeCells('A'.$fila.':H'.$fila);
        $texto('A'.$fila, 'HIDROIL · '.$cotizacion->codigo.' · VRS'.$cotizacion->version);
        $hoja->getStyle('A'.$fila.':H'.$fila)->applyFromArray([
            'font' => ['size' => 8, 'color' => ['argb' => self::MUTED]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORDER]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getRowDimension($fila)->setRowHeight(25);

        return $fila;
    }

    private function configurarHoja(Worksheet $hoja, CotizacionCliente $cotizacion, string $modo, int $ultimaFila): void
    {
        $anchos = $modo === 'detallado'
            ? ['A' => 17, 'B' => 26, 'C' => 16, 'D' => 12, 'E' => 13, 'F' => 21, 'G' => 16, 'H' => 23]
            : ['A' => 14, 'B' => 19, 'C' => 12, 'D' => 11, 'E' => 15, 'F' => 15, 'G' => 13, 'H' => 23];
        foreach ($anchos as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }
        $hoja->setShowGridlines(false);
        $hoja->freezePane('A7');
        $hoja->getSheetView()->setZoomScale(90);
        $hoja->getPageSetup()
            ->setOrientation($modo === 'precio-unico' ? PageSetup::ORIENTATION_PORTRAIT : PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setPrintArea('A1:H'.$ultimaFila);
        if ($modo === 'detallado') {
            $hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(6, 6);
        }
        $hoja->getPageMargins()->setTop(0.4)->setRight(0.35)->setBottom(0.5)->setLeft(0.35);
        $hoja->getHeaderFooter()->setOddFooter('&LHIDROIL · '.$cotizacion->codigo.'&RPágina &P de &N');
    }
}
