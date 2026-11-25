<?php

namespace Tests\Feature;

use App\Services\Ordenes\HojaExcelGastoFinalService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Fase19074ExcelFinalGastoTest extends TestCase
{
    public function test_total_final_suma_areas_y_costos_registrados_sin_sumar_malogrados_o_estimados(): void
    {
        $reporte = $this->reporte();
        $libro = new Spreadsheet();
        (new HojaExcelGastoFinalService())->agregar($libro, $reporte);
        $ruta = tempnam(sys_get_temp_dir(), 'gasto_final_');
        try {
            (new Xlsx($libro))->save($ruta);
            $leido = IOFactory::load($ruta);
            $hoja = $leido->getActiveSheet();
            $this->assertSame('Gasto real', $hoja->getTitle());
            $this->assertEquals(135, $hoja->getCell('F5')->getCalculatedValue());
            $this->assertEquals(40, $hoja->getCell('F6')->getCalculatedValue());
            $this->assertEquals(175, $hoja->getCell('F7')->getCalculatedValue());
            $this->assertSame(DataType::TYPE_FORMULA, $hoja->getCell('F7')->getDataType());
            $this->assertSame('175.00', $hoja->getCell('F7')->getFormattedValue());
            $this->assertSame('00001', $hoja->getCell('A13')->getValue());
            $this->assertSame('=1+1', $hoja->getCell('B13')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $hoja->getCell('B13')->getDataType());
            $this->assertEquals(12, $hoja->getCell('E13')->getCalculatedValue());
            $this->assertEquals(1, $hoja->getCell('G13')->getValue());
            $textos = json_encode($hoja->toArray(null, false, false, false), JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('Solo presupuestado', $textos);
            $this->assertStringNotContainsString('Nunca retirado', $textos);
            $this->assertStringContainsString('NEUMÁTICO', $textos);
            $this->assertStringContainsString('TUBERÍAS', $textos);
            $this->assertStringContainsString('OS-001', $textos);
            $leido->disconnectWorksheets();
        } finally {
            $libro->disconnectWorksheets();
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
    }

    public function test_cierre_de_la_principal_no_oculta_una_os_pendiente(): void
    {
        $reporte = $this->reporte();
        $reporte['ordenes'][0]['estado'] = 'CERRADA';
        $libro = new Spreadsheet();
        (new HojaExcelGastoFinalService())->agregar($libro, $reporte);
        $this->assertStringContainsString('PROVISIONAL', $libro->getActiveSheet()->getCell('A3')->getValue());
        $libro->disconnectWorksheets();
        $reporte['ordenes'][1]['estado'] = 'CERRADA';
        $libro = new Spreadsheet();
        (new HojaExcelGastoFinalService())->agregar($libro, $reporte);
        $this->assertStringContainsString('ORDEN Y OS CERRADAS', $libro->getActiveSheet()->getCell('A3')->getValue());
        $libro->disconnectWorksheets();
    }

    public function test_sin_movimientos_exporta_cero_y_no_fabrica_partidas(): void
    {
        $reporte = $this->reporte();
        $reporte['materiales'] = [];
        $reporte['otros_reales'] = [];
        $libro = new Spreadsheet();
        (new HojaExcelGastoFinalService())->agregar($libro, $reporte);
        $this->assertEquals(0, $libro->getActiveSheet()->getCell('F7')->getCalculatedValue());
        $this->assertSame('Sin movimientos de consumo ni costos reales registrados.', $libro->getActiveSheet()->getCell('A12')->getValue());
        $libro->disconnectWorksheets();
    }

    public function test_retorno_total_con_diferencia_de_valor_no_divide_entre_cero_ni_oculta_el_saldo(): void
    {
        $reporte = $this->reporte();
        $reporte['materiales'] = [array_replace($reporte['materiales'][0], ['real' => 0, 'costo_real' => 0.01])];
        $reporte['otros_reales'] = [];
        $libro = new Spreadsheet();
        (new HojaExcelGastoFinalService())->agregar($libro, $reporte);
        $this->assertSame('N/D', $libro->getActiveSheet()->getCell('E13')->getCalculatedValue());
        $this->assertEqualsWithDelta(0.01, $libro->getActiveSheet()->getCell('F7')->getCalculatedValue(), 0.000001);
        $libro->disconnectWorksheets();
    }

    private function reporte(): array
    {
        $base = ['orden' => 'OP-001', 'codigo' => '00001', 'producto' => '=1+1', 'unidad' => 'UND',
            'area_clave' => '1|id:1', 'area' => 'NEUMÁTICO', 'salida' => 12.0, 'retorno' => 2.0,
            'real' => 10.0, 'costo_real' => 120.0, 'malogrado' => 1.0];
        return [
            'orden' => 'OP-001', 'generado_en' => '2026-09-05 12:00:00',
            'ordenes' => [['codigo' => 'OP-001', 'estado' => 'EN_PROCESO'], ['codigo' => 'OS-001', 'estado' => 'EN_PROCESO']],
            'materiales' => [$base,
                array_replace($base, ['area_clave' => '1|id:2', 'area' => 'TUBERÍAS', 'real' => 1.0, 'costo_real' => 15.0]),
                array_replace($base, ['producto' => 'Nunca retirado', 'salida' => 0.0, 'retorno' => 0.0, 'real' => 0.0, 'costo_real' => 0.0, 'malogrado' => 0.0]),
            ],
            'otros_reales' => [['orden' => 'OS-001', 'tipo' => 'MANO_OBRA', 'descripcion' => 'Trabajo registrado',
                'unidad' => 'Hora', 'cantidad' => 2.0, 'importe' => 40.0, 'documento' => 'DOC-1', 'fecha' => '2026-09-05']],
            'otros_estimados' => [['descripcion' => 'Solo presupuestado', 'importe' => 9999]],
        ];
    }
}
