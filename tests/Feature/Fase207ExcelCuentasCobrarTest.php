<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Proforma;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class Fase207ExcelCuentasCobrarTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_contiene_numeros_fechas_texto_seguro_y_resumen_por_moneda(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD');
        $logistica = $this->usuario('COMERCIAL_LOGISTICA');
        $pen = $this->cotizacion($logistica, '7101', 'PEN', 320);
        $usd = $this->cotizacion($logistica, '7102', 'USD', 50);
        $pen->update([
            'cliente_nombre' => '=SUM(1+1)', 'cliente_documento' => '00123456789',
            'fecha_emision' => today()->subDays(2),
        ]);
        $pen->cobros()->create([
            'fecha_cobro' => today()->subDay(), 'monto' => 120, 'medio_cobro' => 'TRANSFERENCIA',
            'registrado_por' => $contabilidad->id,
        ]);
        $pen->cobros()->create([
            'fecha_cobro' => today(), 'monto' => 10, 'medio_cobro' => 'TRANSFERENCIA',
            'registrado_por' => $contabilidad->id, 'anulado_en' => now(),
        ]);

        $respuesta = $this->actingAs($contabilidad)->get(route('cuentas-cobrar.excel'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('CUENTAS_POR_COBRAR_', $respuesta->headers->get('Content-Disposition'));

        $libro = $this->leer($respuesta->streamedContent());
        try {
            $hoja = $libro->getSheetByName('Cuentas por cobrar');
            $resumen = $libro->getSheetByName('Resumen');
            $this->assertNotNull($hoja);
            $this->assertNotNull($resumen);
            $this->assertStringContainsString('por '.$contabilidad->nombreVisible(), $hoja->getCell('A2')->getValue());
            $this->assertSame('A5', $hoja->getFreezePane());
            $this->assertSame('A4:N6', $hoja->getAutoFilter()->getRange());

            $filaPen = $hoja->getCell('I5')->getValue() === 'PEN' ? 5 : 6;
            $this->assertSame($pen->codigo, $hoja->getCell('A'.$filaPen)->getValue());
            $this->assertSame('Venta directa', $hoja->getCell('D'.$filaPen)->getValue());
            $this->assertSame($pen->proforma->codigo, $hoja->getCell('E'.$filaPen)->getValue());
            $this->assertSame(DataType::TYPE_STRING, $hoja->getCell('B'.$filaPen)->getDataType());
            $this->assertSame('=SUM(1+1)', $hoja->getCell('B'.$filaPen)->getValue());
            $this->assertSame('00123456789', $hoja->getCell('C'.$filaPen)->getValue());
            $this->assertSame(DataType::TYPE_STRING, $hoja->getCell('C'.$filaPen)->getDataType());
            $this->assertSame(DataType::TYPE_NUMERIC, $hoja->getCell('J'.$filaPen)->getDataType());
            $this->assertEquals(320.0, $hoja->getCell('J'.$filaPen)->getValue());
            $this->assertSame('#,##0.00', $hoja->getStyle('J'.$filaPen)->getNumberFormat()->getFormatCode());
            $this->assertEquals(120.0, $hoja->getCell('K'.$filaPen)->getValue());
            $this->assertEquals(200.0, $hoja->getCell('L'.$filaPen)->getValue());
            $this->assertSame('Cobro parcial', $hoja->getCell('M'.$filaPen)->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $hoja->getCell('F'.$filaPen)->getDataType());
            $this->assertSame('dd/mm/yyyy', $hoja->getStyle('F'.$filaPen)->getNumberFormat()->getFormatCode());
            $this->assertSame(today()->subDays(2)->toDateString(), Date::excelToDateTimeObject($hoja->getCell('F'.$filaPen)->getValue())->format('Y-m-d'));
            $this->assertSame(today()->subDay()->toDateString(), Date::excelToDateTimeObject($hoja->getCell('N'.$filaPen)->getValue())->format('Y-m-d'));
            $this->assertEquals(2, $hoja->getCell('G'.$filaPen)->getValue());

            $this->assertSame('PEN', $resumen->getCell('A5')->getValue());
            $this->assertSame('USD', $resumen->getCell('A6')->getValue());
            $this->assertStringStartsWith('=COUNTIFS(', $resumen->getCell('B5')->getValue());
            $this->assertStringStartsWith('=SUMIFS(', $resumen->getCell('C5')->getValue());
            $this->assertEquals(1, $resumen->getCell('B5')->getCalculatedValue());
            $this->assertEquals(320, $resumen->getCell('C5')->getCalculatedValue());
            $this->assertEquals(120, $resumen->getCell('D5')->getCalculatedValue());
            $this->assertEquals(200, $resumen->getCell('E5')->getCalculatedValue());
            $this->assertEquals(50, $resumen->getCell('C6')->getCalculatedValue());
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    public function test_excel_respeta_filtros_y_no_exporta_monedas_ausentes(): void
    {
        $contabilidad = $this->usuario('CONTABILIDAD');
        $logistica = $this->usuario('COMERCIAL_LOGISTICA');
        $pen = $this->cotizacion($logistica, '7201', 'PEN', 320);
        $this->cotizacion($logistica, '7202', 'USD', 80);
        $menor = $this->cotizacion($logistica, '7203', 'PEN', 60);
        $pen->cobros()->create([
            'fecha_cobro' => today(), 'monto' => 20, 'medio_cobro' => 'TRANSFERENCIA',
            'registrado_por' => $contabilidad->id,
        ]);
        $menor->cobros()->create([
            'fecha_cobro' => today(), 'monto' => 10, 'medio_cobro' => 'TRANSFERENCIA',
            'registrado_por' => $contabilidad->id,
        ]);

        $contenido = $this->actingAs($contabilidad)->get(route('cuentas-cobrar.excel', [
            'q' => '72', 'origen' => 'VENTA_DIRECTA', 'moneda' => 'PEN',
            'estado' => 'PARCIAL', 'orden' => 'total_desc',
        ]))->assertOk()->streamedContent();
        $libro = $this->leer($contenido);
        try {
            $hoja = $libro->getSheetByName('Cuentas por cobrar');
            $resumen = $libro->getSheetByName('Resumen');
            $this->assertSame($pen->codigo, $hoja->getCell('A5')->getValue());
            $this->assertSame($menor->codigo, $hoja->getCell('A6')->getValue());
            $this->assertSame('A4:N6', $hoja->getAutoFilter()->getRange());
            $this->assertNull($hoja->getCell('A7')->getValue());
            $this->assertSame('PEN', $resumen->getCell('A5')->getValue());
            $this->assertNull($resumen->getCell('A6')->getValue());
            $this->assertEquals(2, $resumen->getCell('B5')->getCalculatedValue());
            $this->assertStringContainsString('Estado: Cobro parcial', $hoja->getCell('A2')->getValue());
            $this->assertStringContainsString('Orden: Mayor total', $hoja->getCell('A2')->getValue());
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    public function test_excel_comparte_permisos_y_validaciones_del_listado(): void
    {
        $this->get(route('cuentas-cobrar.excel'))->assertRedirect(route('login'));
        $this->actingAs($this->usuario('COMERCIAL_LOGISTICA'))
            ->get(route('cuentas-cobrar.excel'))->assertForbidden();
        $this->actingAs($this->usuario('CONTABILIDAD'))
            ->get(route('cuentas-cobrar.excel', ['estado' => 'INVENTADO']))->assertSessionHasErrors('estado');
    }

    public function test_excel_vacio_conserva_encabezados_y_no_inventa_totales(): void
    {
        $contenido = $this->actingAs($this->usuario('CONTABILIDAD'))
            ->get(route('cuentas-cobrar.excel'))->assertOk()->streamedContent();
        $libro = $this->leer($contenido);
        try {
            $this->assertSame('Cotización', $libro->getSheetByName('Cuentas por cobrar')->getCell('A4')->getValue());
            $this->assertNull($libro->getSheetByName('Resumen')->getCell('A5')->getValue());
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    private function leer(string $contenido): Spreadsheet
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cxc_excel_');
        $this->assertNotFalse($ruta);
        try {
            file_put_contents($ruta, $contenido);

            return IOFactory::load($ruta);
        } finally {
            @unlink($ruta);
        }
    }

    private function usuario(string $rol): User
    {
        $role = Role::query()->firstOrCreate(['codigo' => $rol], ['nombre' => $rol, 'estado' => true]);
        $nombre = strtolower($rol).'-'.(User::query()->count() + 1);

        return User::query()->create([
            'role_id' => $role->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'password-seguro',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }

    private function cotizacion(User $usuario, string $codigo, string $moneda, float $total): CotizacionCliente
    {
        $tipoCliente = TipoCliente::query()->firstOrCreate(['codigo' => 'FINAL'], [
            'nombre' => 'Final', 'porcentaje_ganancia' => 0, 'estado' => true,
        ]);
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id, 'tipo_documento' => 'RUC',
            'numero_documento' => '2060020'.$codigo, 'ruc' => '2060020'.$codigo,
            'razon_social' => 'Cliente '.$codigo, 'estado' => true,
        ]);
        $tipo = TipoOrden::query()->firstOrCreate(['codigo' => 'OS'], [
            'nombre' => 'Servicio', 'estado' => true,
        ]);
        $proforma = Proforma::query()->create([
            'cliente_id' => $cliente->id, 'codigo' => 'PRO-'.$codigo,
            'fecha_emision' => today(), 'moneda' => $moneda,
            'estado' => 'COTIZADA', 'registrado_por' => $usuario->id,
        ]);

        return CotizacionCliente::query()->create([
            'proforma_id' => $proforma->id, 'origen' => 'PROFORMA_ALMACEN',
            'cliente_id' => $cliente->id, 'tipo_orden_id' => $tipo->id,
            'codigo_base' => 'COB-'.$codigo, 'version' => 1,
            'codigo' => 'COB-'.$codigo.'-VRS1', 'cliente_nombre' => $cliente->razon_social,
            'fecha_emision' => today(), 'moneda' => $moneda,
            'tipo_cambio' => $moneda === 'USD' ? 3.8 : null,
            'subtotal' => $total, 'total' => $total,
            'estado' => 'CERRADA', 'cotizado_por' => $usuario->id,
        ]);
    }
}
