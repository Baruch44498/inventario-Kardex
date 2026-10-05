<?php

namespace App\Services\Ventas;

use App\Models\CotizacionCliente;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExportarCotizacionClienteExcelService
{
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

        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('HIDROIL')->setTitle('Cotización '.$cotizacion->codigo);
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('COTIZACION CLIENTE');
        $texto = static fn (string $celda, mixed $valor) =>
            $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
        $numero = static fn (string $celda, mixed $valor) =>
            $hoja->setCellValueExplicit($celda, (float) $valor, DataType::TYPE_NUMERIC);

        $texto('A1', 'HIDROIL · COTIZACIÓN '.$cotizacion->codigo);
        $texto('A2', 'Cliente: '.$cotizacion->cliente_nombre);
        $texto('A3', 'Fecha: '.$cotizacion->fecha_emision?->format('d/m/Y')
            .'   Moneda: '.$cotizacion->moneda.'   Versión: '.$cotizacion->version);
        $texto('A4', $cotizacion->descripcion_trabajo ?: 'Propuesta comercial');
        foreach (range(1, 4) as $fila) {
            $hoja->mergeCells('A'.$fila.':H'.$fila);
        }
        $hoja->getStyle('A1:H1')->getFont()->setBold(true)->setSize(16);
        $hoja->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF17375E');
        $hoja->getStyle('A1:H1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $hoja->getRowDimension(1)->setRowHeight(30);
        $hoja->getRowDimension(4)->setRowHeight(30);

        if ($modo === 'precio-unico') {
            $texto('A6', 'CONCEPTO');
            $texto('H6', 'PRECIO FINAL ('.$cotizacion->moneda.')');
            $hoja->mergeCells('A6:G6');
            $texto('A7', $cotizacion->descripcion_trabajo ?: 'Trabajo o servicio cotizado');
            $hoja->mergeCells('A7:G7');
            $numero('H7', round((float) $cotizacion->total, 2));
            $texto('A9', 'Precio final con IGV incluido');
            $hoja->mergeCells('A9:H9');
            $fila = 9;
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
                $hoja->getRowDimension($fila)->setRowHeight(28);
                $fila++;
            }
            $fila++;
        }

        if ($modo === 'detallado') {
            $texto('G'.$fila, 'Subtotal');
            $numero('H'.$fila++, $cotizacion->subtotal);
            $texto('G'.$fila, 'IGV');
            $numero('H'.$fila++, $cotizacion->impuesto);
            $texto('G'.$fila, 'TOTAL '.$cotizacion->moneda);
            $numero('H'.$fila, round((float) $cotizacion->total, 2));
            $hoja->getStyle('G'.$fila.':H'.$fila)->getFont()->setBold(true);
        }
        $hoja->getStyle('A6:H6')->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFDDECF4');
        $hoja->getStyle('A6:H6')->getFont()->setBold(true);
        $hoja->getStyle('D7:H'.$fila)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);0.00');
        if ($modo === 'detallado') {
            $hoja->getStyle('F7:F'.$fila)->getNumberFormat()->setFormatCode('#,##0.0000');
        }
        $hoja->getStyle('A1:H'.($fila + 5))->getAlignment()->setWrapText(true);
        foreach (['A' => 18, 'B' => 36, 'C' => 18, 'D' => 14, 'E' => 14,
            'F' => 24, 'G' => 20, 'H' => 25] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }
        if ($cotizacion->condiciones_pago) {
            $texto('A'.($fila + 2), 'Condiciones de pago: '.$cotizacion->condiciones_pago);
            $hoja->mergeCells('A'.($fila + 2).':H'.($fila + 2));
        }
        if ($cotizacion->condiciones_entrega) {
            $texto('A'.($fila + 3), 'Condiciones de entrega: '.$cotizacion->condiciones_entrega);
            $hoja->mergeCells('A'.($fila + 3).':H'.($fila + 3));
        }
        $hoja->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)
            ->setPrintArea('A1:H'.($fila + 4));
        $hoja->setShowGridlines(false);
        $hoja->freezePane('D7');

        return $libro;
    }
}
