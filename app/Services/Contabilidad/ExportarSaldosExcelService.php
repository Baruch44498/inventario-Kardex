<?php

namespace App\Services\Contabilidad;

use App\Models\CotizacionCliente;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarSaldosExcelService
{
    // PhpSpreadsheet conserva las celdas en memoria mientras construye el libro.
    private const MAX_FILAS = 5000;

    /** @param Builder<CotizacionCliente> $consulta @param array<string, string> $filtros */
    public function cuentasCobrar(Builder $consulta, array $filtros, string $usuario): StreamedResponse
    {
        abort_if(
            (clone $consulta)->count() > self::MAX_FILAS,
            422,
            'El Excel admite hasta '.number_format(self::MAX_FILAS).' documentos. Aplica filtros o descarga el CSV de saldos.'
        );

        $nombre = 'CUENTAS_POR_COBRAR_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($consulta, $filtros, $usuario): void {
            $libro = $this->libro($consulta, $filtros, $usuario);
            try {
                IOFactory::createWriter($libro, 'Xlsx')->save('php://output');
            } finally {
                $libro->disconnectWorksheets();
            }
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @param Builder<CotizacionCliente> $consulta @param array<string, string> $filtros */
    private function libro(Builder $consulta, array $filtros, string $usuario): Spreadsheet
    {
        $libro = new Spreadsheet();
        $libro->getProperties()->setCreator('HIDROIL')->setTitle('Cuentas por cobrar');
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Cuentas por cobrar');
        $hoja->setShowGridlines(false);
        $this->encabezado($hoja, 'CUENTAS POR COBRAR', $this->metadatos($filtros, $usuario), [
            'Cotización', 'Cliente', 'Documento del cliente', 'Origen', 'Orden o Proforma',
            'Emisión', 'Días desde emisión', 'Condiciones de pago', 'Moneda',
            'Total', 'Cobrado', 'Saldo', 'Estado', 'Último cobro',
        ], 'N');

        $consulta->withMax('cobrosVigentes', 'fecha_cobro');
        $fila = 5;
        $monedas = [];
        foreach ($consulta->lazy(500) as $cotizacion) {
            $monedas[$cotizacion->moneda] = true;
            $cobrado = $cotizacion->montoCobrado();
            $saldo = $cotizacion->saldoPorCobrar();

            foreach ([
                'A' => $cotizacion->codigo,
                'B' => $cotizacion->cliente_nombre,
                'C' => $cotizacion->cliente_documento,
                'D' => $cotizacion->proforma_id ? 'Venta directa' : 'Orden',
                'E' => $cotizacion->proforma_id
                    ? ($cotizacion->proforma?->codigo ?: 'No disponible')
                    : ($cotizacion->ordenOperacion?->codigo_orden ?: 'No disponible'),
                'H' => $cotizacion->condiciones_pago ?: 'No especificadas',
                'I' => $cotizacion->moneda,
                'M' => $saldo === 0.0 ? 'Cobrada' : ($cobrado > 0 ? 'Cobro parcial' : 'Pendiente'),
            ] as $columna => $texto) {
                $hoja->setCellValueExplicit($columna.$fila, (string) $texto, DataType::TYPE_STRING);
            }

            if ($cotizacion->fecha_emision) {
                $hoja->setCellValueExplicit('F'.$fila, Date::PHPToExcel($cotizacion->fecha_emision), DataType::TYPE_NUMERIC);
                $dias = max(0, (int) $cotizacion->fecha_emision->copy()->startOfDay()->diffInDays(today(), false));
                $hoja->setCellValueExplicit('G'.$fila, $dias, DataType::TYPE_NUMERIC);
            }

            foreach (['J' => (float) $cotizacion->total, 'K' => $cobrado, 'L' => $saldo] as $columna => $importe) {
                $hoja->setCellValueExplicit($columna.$fila, $importe, DataType::TYPE_NUMERIC);
            }

            $ultimoCobro = $cotizacion->getAttribute('cobros_vigentes_max_fecha_cobro');
            if ($ultimoCobro) {
                $hoja->setCellValueExplicit('N'.$fila, Date::PHPToExcel(\Illuminate\Support\Carbon::parse($ultimoCobro)), DataType::TYPE_NUMERIC);
            }

            $fila++;
        }

        $ultimaFila = max(4, $fila - 1);
        if ($fila > 5) {
            $hoja->getStyle('F5:F'.$ultimaFila)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $hoja->getStyle('N5:N'.$ultimaFila)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $hoja->getStyle('J5:L'.$ultimaFila)->getNumberFormat()->setFormatCode('#,##0.00');
            $hoja->getStyle('A5:N'.$ultimaFila)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $hoja->getStyle('B5:C'.$ultimaFila)->getAlignment()->setWrapText(true);
            $hoja->getStyle('H5:H'.$ultimaFila)->getAlignment()->setWrapText(true);
        }
        $hoja->setAutoFilter('A4:N'.$ultimaFila);
        $hoja->freezePane('A5');

        $anchos = [
            'A' => 24, 'B' => 36, 'C' => 23, 'D' => 17, 'E' => 24,
            'F' => 17, 'G' => 21, 'H' => 40, 'I' => 12, 'J' => 19,
            'K' => 19, 'L' => 19, 'M' => 18, 'N' => 17,
        ];
        foreach ($anchos as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        $resumen = $libro->createSheet();
        $resumen->setTitle('Resumen');
        $resumen->setShowGridlines(false);
        $this->encabezado($resumen, 'RESUMEN POR MONEDA', $this->metadatos($filtros, $usuario), [
            'Moneda', 'N.º de documentos', 'Total', 'Cobrado', 'Por cobrar',
        ], 'E');
        $filaResumen = 5;
        foreach (['PEN', 'USD'] as $moneda) {
            if (! isset($monedas[$moneda])) {
                continue;
            }

            $resumen->setCellValueExplicit('A'.$filaResumen, $moneda, DataType::TYPE_STRING);
            $rangoMoneda = "'Cuentas por cobrar'!".'$I$5:$I$'.$ultimaFila;
            $criterio = '$A'.$filaResumen;
            $resumen->setCellValueExplicit('B'.$filaResumen, "=COUNTIFS({$rangoMoneda},{$criterio})", DataType::TYPE_FORMULA);
            foreach (['C' => 'J', 'D' => 'K', 'E' => 'L'] as $destino => $origen) {
                $rangoImporte = "'Cuentas por cobrar'!".'$'.$origen.'$5:$'.$origen.'$'.$ultimaFila;
                $resumen->setCellValueExplicit(
                    $destino.$filaResumen,
                    "=SUMIFS({$rangoImporte},{$rangoMoneda},{$criterio})",
                    DataType::TYPE_FORMULA
                );
            }
            $filaResumen++;
        }

        if ($filaResumen > 5) {
            $resumen->getStyle('C5:E'.($filaResumen - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }
        foreach (['A' => 17, 'B' => 25, 'C' => 23, 'D' => 23, 'E' => 23] as $columna => $ancho) {
            $resumen->getColumnDimension($columna)->setWidth($ancho);
        }
        $resumen->freezePane('A5');

        return $libro;
    }

    /** @param list<string> $columnas */
    private function encabezado(Worksheet $hoja, string $titulo, string $detalle, array $columnas, string $ultimaColumna): void
    {
        $hoja->setCellValueExplicit('A1', $titulo, DataType::TYPE_STRING);
        $hoja->setCellValueExplicit('A2', $detalle, DataType::TYPE_STRING);
        $hoja->mergeCells('A1:'.$ultimaColumna.'1');
        $hoja->mergeCells('A2:'.$ultimaColumna.'2');
        $hoja->getStyle('A1:'.$ultimaColumna.'1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF15324A');
        $hoja->getStyle('A2:'.$ultimaColumna.'2')->getFont()->setSize(10)->getColor()->setARGB('FF526170');
        $hoja->getStyle('A2:'.$ultimaColumna.'2')->getAlignment()->setWrapText(true);
        $hoja->getRowDimension(2)->setRowHeight(32);

        foreach ($columnas as $indice => $nombre) {
            $hoja->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indice + 1).'4', $nombre, DataType::TYPE_STRING);
        }
        $hoja->getStyle('A4:'.$ultimaColumna.'4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $hoja->getStyle('A4:'.$ultimaColumna.'4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF15324A');
        $hoja->getStyle('A4:'.$ultimaColumna.'4')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(4)->setRowHeight(32);
    }

    /** @param array<string, string> $filtros */
    private function metadatos(array $filtros, string $usuario): string
    {
        $etiquetas = [
            'q' => 'Buscar', 'origen' => 'Origen', 'estado' => 'Estado',
            'moneda' => 'Moneda', 'orden' => 'Orden',
        ];
        $valores = [
            'VENTA_DIRECTA' => 'Venta directa', 'ORDEN' => 'Orden',
            'PENDIENTE' => 'Pendiente', 'PARCIAL' => 'Cobro parcial', 'COBRADA' => 'Cobrada',
            'emision_desc' => 'Emisión reciente', 'emision_asc' => 'Emisión antigua', 'total_desc' => 'Mayor total',
        ];
        $aplicados = [];
        foreach ($etiquetas as $clave => $etiqueta) {
            if (filled($filtros[$clave] ?? null)) {
                $valor = trim($filtros[$clave]);
                $aplicados[] = $etiqueta.': '.($valores[$valor] ?? $valor);
            }
        }

        return 'Generado el '.now()->format('d/m/Y H:i').' por '.$usuario
            .' · Filtros: '.($aplicados ? implode(' · ', $aplicados) : 'Todos');
    }
}
