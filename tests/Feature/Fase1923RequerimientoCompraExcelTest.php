<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class Fase1923RequerimientoCompraExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Requisicion $requerimiento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuario('ALMACEN', 'almacen_1923');
        $this->logistica = $this->usuario('COMERCIAL_LOGISTICA', 'logistica_1923');
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1923-001',
            'descripcion' => 'Conector bronce en codo para manguera',
            'estado' => true,
        ]);
        $this->requerimiento = Requisicion::query()->create([
            'codigo' => 'REQ-1923-001',
            'fecha_solicitud' => '2026-09-12',
            'origen' => 'REPOSICION',
            'descripcion' => 'Reposición para almacén',
            'prioridad' => 'NORMAL',
            'estado' => 'BORRADOR',
            'estado_abastecimiento' => 'PENDIENTE',
            'solicitado_por' => $this->almacen->id,
        ]);
        $this->requerimiento->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 15,
            'cantidad_sugerida' => 15,
            'cantidad_atendida' => 0,
        ]);
    }

    public function test_almacen_descarga_el_requerimiento_administrativo_con_logo_y_sin_precios(): void
    {
        $respuesta = $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.excel', $this->requerimiento));

        $respuesta->assertOk()->assertDownload('REQUERIMIENTO_COMPRA_REQ-1923-001.xlsx');

        $temporal = tempnam(sys_get_temp_dir(), 'requerimiento-1923-');
        $ruta = $temporal.'.xlsx';
        @unlink($temporal);
        file_put_contents($ruta, $respuesta->streamedContent());
        $hoja = IOFactory::load($ruta)->getActiveSheet();

        $this->assertSame('REQUERIMIENTO DE COMPRA', $hoja->getCell('C1')->getValue());
        $this->assertSame('REQ-1923-001', $hoja->getCell('C3')->getValue());
        $this->assertSame('USO INTERNO / REPOSICIÓN', $hoja->getCell('D4')->getValue());
        $this->assertSame('ITEM', $hoja->getCell('A5')->getValue());
        $this->assertSame('PEDIDO', $hoja->getCell('E5')->getValue());
        $this->assertSame('MAT-1923-001', $hoja->getCell('B6')->getValue());
        $this->assertSame('Conector bronce en codo para manguera', $hoja->getCell('C6')->getValue());
        $this->assertSame('UNIDAD', $hoja->getCell('D6')->getValue());
        $this->assertSame(15.0, (float) $hoja->getCell('E6')->getValue());
        $this->assertCount(1, $hoja->getDrawingCollection());

        @unlink($ruta);
    }

    public function test_logistica_no_descarga_un_borrador_pero_si_un_requerimiento_enviado(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.excel', $this->requerimiento))
            ->assertForbidden();

        $this->requerimiento->update(['estado' => 'ENVIADA']);

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.excel', $this->requerimiento))
            ->assertOk();
    }

    public function test_el_detalle_muestra_un_boton_independiente_del_excel_para_proveedores(): void
    {
        $respuesta = $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.show', $this->requerimiento));

        $respuesta->assertOk()
            ->assertSee('Exportar requerimiento')
            ->assertSee(route('requerimientos-compra.excel', $this->requerimiento));
    }

    private function usuario(string $rol, string $nombre): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $rol)->firstOrFail()->id,
            'username' => $nombre,
            'email' => $nombre.'@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
