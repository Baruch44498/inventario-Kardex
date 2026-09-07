<?php

namespace App\Services\Ventas;

use App\Models\CotizacionCliente;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Validation\ValidationException;

class ExportarCotizacionCosteoExcelService
{
    public function libro(CotizacionCliente $cotizacion): Spreadsheet
    {
        $partidas = $cotizacion->presupuestos()->where('estado', 'VIGENTE')->with('producto')->orderBy('id')->get();
        if ($partidas->isEmpty()) {
            throw ValidationException::withMessages(['excel' => 'Registra al menos una partida antes de descargar la hoja de costos.']);
        }
        $areas = $cotizacion->todasLasAreas()->get()->keyBy('id');
        $grupos = [];
        foreach ($partidas as $partida) {
            $ruta = [];
            $area = $areas->get($partida->cotizacion_area_id);
            $vistos = [];
            while ($area) {
                if (in_array($area->id, $vistos, true) || count($vistos) >= 12) {
                    throw ValidationException::withMessages(['excel' => 'Revisa la jerarquía de las áreas antes de exportar.']);
                }
                $vistos[] = $area->id;
                array_unshift($ruta, $area->nombre);
                $area = $areas->get($area->area_padre_id);
            }
            $ruta = $ruta ?: [$partida->grupo_costo ?: 'GENERAL'];
            $clave = json_encode($ruta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $grupos[$clave] ??= ['ruta' => $ruta, 'partidas' => []];
            $grupos[$clave]['partidas'][] = $partida;
        }
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet()->setTitle('COTIZACIÓN '.$cotizacion->tipoOrden?->codigo);
        $libro->getProperties()->setCreator('HIDROIL')->setTitle('Costeo '.$cotizacion->codigo);
        $texto = fn ($celda, $valor) => $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
        $texto('A1', 'USO INTERNO · '.$cotizacion->codigo.' · '.$cotizacion->cliente_nombre);
        $hoja->mergeCells('A1:U1');
        $texto('A2', $cotizacion->descripcion_trabajo ?: 'Cotización '.$cotizacion->codigo);
        $hoja->mergeCells('A2:U2');
        $texto('A3', 'Costos y venta estimados · '.$cotizacion->tipoOrden?->codigo.' · '.$cotizacion->estado.' · '.($cotizacion->costeo_sincronizado_en ? 'Precio sincronizado' : 'Precio pendiente de sincronizar'));
        $hoja->mergeCells('A3:U3');
        $cabeceras = ['CODIGO', 'CANT.', 'DESCRIPCION DEL PRODUCTO', 'U.M', 'PROVEEDOR',
            'COSTO UNITARIO (SOLES +IGV)', 'COSTO TOTAL SOLES', '%', 'PRECIO DE VENTA UNIT CON PAGOS',
            'PRECIO DE VENTA TOTAL + PAGOS', 'UTILIDAD NETA UNIT SIN IGV', 'UTILIDAD NETA TOTAL SIN IGV',
            'IGV A PAGAR', 'T.C', 'COSTO UNITARIO (DOLARES +IGV)', 'COSTO TOTAL DOLARES',
            'PRECIO DE VENTA UNIT CON PAGOS.', 'PRECIO DE VENTA TOTAL + PAGOS.',
            'UTILIDAD NETA UNIT SIN IGV.', 'UTILIDAD NETA TOTAL SIN IGV.', 'IGV A PAGAR.'];
        foreach ($cabeceras as $col => $titulo) {
            $texto(Coordinate::stringFromColumnIndex($col + 1).'4', $titulo);
        }
        $texto('V1', FormatoCotizacionExcel::MARCA);
        $hoja->getColumnDimension('V')->setVisible(false);
        $fila = 5;
        $rutaAnterior = [];
        $filasTitulos = [];
        $subtotales = [];
        foreach ($grupos as $grupo) {
            foreach ($grupo['ruta'] as $nivel => $nombre) {
                if (array_slice($rutaAnterior, 0, $nivel + 1) === array_slice($grupo['ruta'], 0, $nivel + 1)) {
                    continue;
                }
                $texto('C'.$fila, $nombre);
                $filasTitulos[$nivel] = ['celda' => 'C'.$fila, 'titulo' => $nombre];
                $hoja->mergeCells('C'.$fila.':M'.$fila);
                $hoja->getStyle('A'.$fila.':U'.$fila)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($nivel === 0 ? 'FF0070C0' : 'FF85F0F3');
                $hoja->getStyle('A'.$fila.':U'.$fila)->getFont()->setBold(true);
                if ($nivel === 0) {
                    $hoja->getStyle('C'.$fila)->getFont()->getColor()->setARGB('FFFFFFFF');
                }
                $hoja->getRowDimension($fila++)->setRowHeight(30);
            }
            $rutaAnterior = $grupo['ruta'];
            $filasTitulos = array_slice($filasTitulos, 0, count($grupo['ruta']));
            $inicio = $fila;
            foreach ($grupo['partidas'] as $p) {
                $q = (float) $p->cantidad;
                if ($q <= 0) {
                    throw ValidationException::withMessages(['excel' => 'Hay partidas con cantidad inválida. Revísalas antes de exportar.']);
                }
                $valores = [(string) ($p->producto?->codigo ?? ''), $q, $p->descripcion, $p->unidad, '',
                    (float) $p->costo_total_soles / $q, (float) $p->costo_total_soles, (float) $p->margen_porcentaje / 100,
                    (float) $p->precio_venta_total_soles / $q, (float) $p->precio_venta_total_soles,
                    (float) $p->utilidad_estimada_soles / $q, (float) $p->utilidad_estimada_soles, (float) $p->igv_por_pagar_soles,
                    (float) $p->tipo_cambio, (float) $p->costo_total_dolares / $q, (float) $p->costo_total_dolares,
                    (float) $p->precio_venta_total_dolares / $q, (float) $p->precio_venta_total_dolares,
                    (float) $p->utilidad_estimada_dolares / $q, (float) $p->utilidad_estimada_dolares, (float) $p->igv_por_pagar_dolares];
                foreach ($valores as $col => $valor) {
                    $hoja->setCellValueExplicit(Coordinate::stringFromColumnIndex($col + 1).$fila, $valor,
                        is_float($valor) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
                $datos = $p->only(FormatoCotizacionExcel::CAMPOS);
                $datos['ruta_areas'] = $grupo['ruta'];
                $datos['titulos'] = array_column($filasTitulos, 'titulo', 'celda');
                $datos['firma'] = FormatoCotizacionExcel::firma($hoja, $fila);
                $texto('V'.$fila, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $hoja->getRowDimension($fila)->setOutlineLevel(min(7, count($grupo['ruta'])))->setRowHeight(36);
                $fila++;
            }
            $texto('C'.$fila, 'TOTAL '.$grupo['ruta'][count($grupo['ruta']) - 1]);
            foreach (['G', 'J', 'L', 'M', 'P', 'R', 'T', 'U'] as $col) {
                $hoja->setCellValueExplicit($col.$fila, '=SUM('.$col.$inicio.':'.$col.($fila - 1).')', DataType::TYPE_FORMULA);
            }
            $hoja->getStyle('A'.$fila.':U'.$fila)->getFont()->setBold(true);
            $subtotales[] = $fila;
            $fila += 2;
        }
        $texto('C'.$fila, 'TOTAL GENERAL');
        foreach (['G', 'J', 'L', 'M', 'P', 'R', 'T', 'U'] as $col) {
            $hoja->setCellValueExplicit($col.$fila, '=SUM('.implode(',', array_map(fn ($r) => $col.$r, $subtotales)).')', DataType::TYPE_FORMULA);
        }
        $hoja->getStyle('A4:U4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00B050');
        $hoja->getStyle('A4:U4')->getFont()->setBold(true);
        $hoja->getStyle('A1:U'.$fila)->getAlignment()->setWrapText(true)->setVertical('center');
        $hoja->getStyle('F5:U'.$fila)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);0.00');
        $hoja->getStyle('B5:B'.$fila)->getNumberFormat()->setFormatCode('0.00');
        $hoja->getStyle('H5:H'.$fila)->getNumberFormat()->setFormatCode('0.00%');
        foreach (range(1, 21) as $col) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth($col === 3 ? 65 : ($col === 5 ? 28 : 18));
        }
        $hoja->getRowDimension(4)->setRowHeight(65);
        $hoja->freezePane('F5');
        $hoja->setShowGridlines(false);
        $hoja->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:U'.$fila);
        $hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        return $libro;
    }
}
