<?php

namespace Tests\Feature;

use App\Models\CotizacionCliente;
use App\Models\CotizacionClienteDetalle;
use App\Models\TipoOrden;
use App\Services\Ventas\ExportarCotizacionClienteExcelService;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Tests\TestCase;

class Fase243DisenoExcelClienteTest extends TestCase
{
    public function test_precio_unico_presenta_documento_comercial_sin_partidas_internas(): void
    {
        $cotizacion = $this->cotizacion();
        $libro = app(ExportarCotizacionClienteExcelService::class)->libro($cotizacion, 'precio-unico');
        $hoja = $libro->getActiveSheet();

        $this->assertSame('DOCUMENTO COMERCIAL · VERSIÓN 1', $hoja->getCell('D1')->getValue());
        $this->assertSame('BORRADOR', $hoja->getCell('H3')->getValue());
        $this->assertSame('Cisterna', $hoja->getCell('A7')->getValue());
        $this->assertSame(4850.0, $hoja->getCell('H7')->getValue());
        $this->assertSame(4850.0, $hoja->getCell('H9')->getValue());
        $this->assertStringNotContainsString('MAT-PRIVADO', json_encode($hoja->toArray()));
        $this->assertSame(PageSetup::ORIENTATION_PORTRAIT, $hoja->getPageSetup()->getOrientation());
        $this->assertTrue($hoja->getPageSetup()->getFitToWidth() === 1);
        $this->assertFalse($hoja->getShowGridlines());
        $this->assertContains('A1:C2', $hoja->getMergeCells());
        if (is_file(public_path('images/logo-hidroil.png'))) {
            $this->assertCount(1, $hoja->getDrawingCollection());
        }
        $libro->disconnectWorksheets();
    }

    public function test_detallado_con_muchas_partidas_repite_encabezado_y_destaca_total_guardado(): void
    {
        $cotizacion = $this->cotizacion();
        $libro = app(ExportarCotizacionClienteExcelService::class)->libro($cotizacion, 'detallado');
        $hoja = $libro->getActiveSheet();

        $this->assertSame('MAT-PRIVADO', $hoja->getCell('A7')->getValue());
        $this->assertSame('P. UNITARIO SIN IGV', $hoja->getCell('F6')->getValue());
        $this->assertSame(4850.0, $hoja->getCell('H28')->getValue());
        $this->assertSame(Border::BORDER_MEDIUM, $hoja->getStyle('H28')->getBorders()->getTop()->getBorderStyle());
        $this->assertSame(PageSetup::ORIENTATION_LANDSCAPE, $hoja->getPageSetup()->getOrientation());
        $this->assertSame([6, 6], $hoja->getPageSetup()->getRowsToRepeatAtTop());
        $this->assertStringNotContainsString('9999', json_encode($hoja->toArray()));
        $libro->disconnectWorksheets();
    }

    private function cotizacion(): CotizacionCliente
    {
        $cotizacion = new CotizacionCliente([
            'codigo' => 'COT-TEST-VRS1', 'version' => 1,
            'cliente_nombre' => 'Cliente de prueba', 'cliente_documento' => 'RUC 12345678901',
            'fecha_emision' => '2026-10-06', 'moneda' => 'PEN', 'descripcion_trabajo' => 'Cisterna',
            'estado' => 'ABIERTA', 'subtotal' => 4110.1695, 'impuesto' => 739.8305, 'total' => 4850,
        ]);
        $cotizacion->setRelation('tipoOrden', new TipoOrden(['codigo' => 'OP']));
        $detalles = collect();
        for ($n = 1; $n <= 18; $n++) {
            $detalles->push(new CotizacionClienteDetalle([
                'codigo_producto' => 'MAT-PRIVADO', 'descripcion' => 'Partida comercial '.$n,
                'unidad_medida' => 'UND', 'cantidad' => 1, 'precio_unitario' => 200,
                'impuesto' => 36, 'total' => 236, 'costo_referencia' => 9999,
            ]));
        }
        $cotizacion->setRelation('detalles', $detalles);

        return $cotizacion;
    }
}
