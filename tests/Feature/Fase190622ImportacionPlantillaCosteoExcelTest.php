<?php

namespace Tests\Feature;

use App\Models\ImportacionPlantillaCosteo;
use App\Models\PlantillaCosteo;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Fase190622ImportacionPlantillaCosteoExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private TipoOrden $produccion;
    private Producto $tuberia;
    private Producto $casco;

    public function test_revision_usa_el_paginador_compacto_del_sistema(): void
    {
        $vista = file_get_contents(
            resource_path('views/plantillas_costeo/importacion_revision.blade.php')
        );

        $this->assertStringContainsString(
            '<x-ui.pagination :paginator="$partidas" />',
            $vista
        );
        $this->assertStringNotContainsString('$partidas->links()', $vista);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'COMERCIAL_LOGISTICA')->firstOrFail()->id,
            'username' => 'logistica_190622',
            'email' => 'logistica_190622@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $this->produccion = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $metro = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'M'],
            ['nombre' => 'Metro', 'estado' => true]
        );
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->tuberia = Producto::query()->create([
            'unidad_medida_id' => $metro->id,
            'codigo' => '10154',
            'descripcion' => 'Plancha para manta de cisterna',
            'permite_fraccionamiento' => true,
            'estado' => true,
        ]);
        $this->casco = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'EPP-CASCO-01',
            'descripcion' => 'Casco de seguridad',
            'permite_fraccionamiento' => false,
            'estado' => true,
        ]);
    }

    public function test_importa_revisa_y_confirma_el_excel_como_plantilla(): void
    {
        $ruta = $this->crearExcel();

        try {
            $respuesta = $this->actingAs($this->logistica)
                ->post(route('plantillas-costeo.importaciones.store'), [
                    'tipo_orden_id' => $this->produccion->id,
                    'nombre' => 'Cisterna 5000 galones SCH-40',
                    'descripcion' => 'Modelo importado de Excel',
                    'documento' => new UploadedFile(
                        $ruta,
                        'COSTO DE FABRICACION.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ]);
        } finally {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }

        $importacion = ImportacionPlantillaCosteo::query()->with('partidas')->firstOrFail();
        $respuesta->assertRedirect(route('plantillas-costeo.importaciones.show', $importacion));
        $this->assertCount(4, $importacion->partidas);
        $this->assertSame($this->tuberia->id, $importacion->partidas->firstWhere('fila_excel', 5)->producto_id);
        $this->assertSame('SERVICIO_TERCERO', $importacion->partidas->firstWhere('fila_excel', 7)->tipo_costo);
        $this->assertSame('DIA', $importacion->partidas->firstWhere('fila_excel', 7)->unidad);
        $this->assertSame('POR_DEFINIR', $importacion->partidas->firstWhere('fila_excel', 7)->ejecucion_servicio);
        $this->assertSame('PENDIENTE', $importacion->partidas->firstWhere('fila_excel', 9)->estado_vinculacion);
        $this->assertSame('MANO_OBRA', $importacion->partidas->firstWhere('fila_excel', 11)->tipo_costo);

        $epp = $importacion->partidas->firstWhere('fila_excel', 9);
        $this->actingAs($this->logistica)
            ->patch(route('plantillas-costeo.importaciones.partidas.update', $epp), [
                'accion' => 'GUARDAR',
                'tipo_costo' => 'MATERIAL',
                'producto_id' => $this->casco->id,
                'unidad' => 'UNIDAD',
                'grupo_costo' => $epp->grupo_costo,
                'descripcion' => $epp->descripcion,
                'cantidad' => $epp->cantidad,
                'costo_unitario' => $epp->costo_unitario,
                'margen_porcentaje' => $epp->margen_porcentaje,
                'tipo_cambio' => $epp->tipo_cambio,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->logistica)
            ->post(route('plantillas-costeo.importaciones.confirmar', $importacion))
            ->assertSessionHasErrors('importacion');
        $this->assertDatabaseCount('plantillas_costeo', 0);

        $servicio = $importacion->partidas->firstWhere('fila_excel', 7);
        $this->actingAs($this->logistica)
            ->patch(route('plantillas-costeo.importaciones.partidas.update', $servicio), [
                'accion' => 'GUARDAR',
                'tipo_costo' => 'SERVICIO_TERCERO',
                'ejecucion_servicio' => 'EXTERNO',
                'unidad' => $servicio->unidad,
                'grupo_costo' => $servicio->grupo_costo,
                'descripcion' => $servicio->descripcion,
                'cantidad' => $servicio->cantidad,
                'costo_unitario' => $servicio->costo_unitario,
                'margen_porcentaje' => $servicio->margen_porcentaje,
                'tipo_cambio' => $servicio->tipo_cambio,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->logistica)
            ->post(route('plantillas-costeo.importaciones.confirmar', $importacion))
            ->assertRedirect(route('plantillas-costeo.index'))
            ->assertSessionHasNoErrors();

        $plantilla = PlantillaCosteo::query()->with('partidas')->firstOrFail();
        $this->assertSame('EXCEL', $plantilla->origen);
        $this->assertCount(4, $plantilla->partidas);
        $this->assertSame(
            ['MATERIAL', 'SERVICIO_TERCERO', 'MATERIAL', 'MANO_OBRA'],
            $plantilla->partidas->pluck('tipo_costo')->all()
        );
        $this->assertFalse($plantilla->partidas->contains('tipo_costo', 'HERRAMIENTA_EQUIPO'));
        $this->assertSame(
            'EXTERNO',
            $plantilla->partidas->firstWhere('tipo_costo', 'SERVICIO_TERCERO')->ejecucion_servicio
        );
        $this->assertSame('CONFIRMADA', $importacion->fresh()->estado);
    }

    public function test_no_confirma_mientras_exista_material_pendiente(): void
    {
        $ruta = $this->crearExcel();
        try {
            $this->actingAs($this->logistica)
                ->post(route('plantillas-costeo.importaciones.store'), [
                    'tipo_orden_id' => $this->produccion->id,
                    'nombre' => 'Modelo pendiente de revisar',
                    'documento' => new UploadedFile(
                        $ruta,
                        'modelo.xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        null,
                        true
                    ),
                ]);
        } finally {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }

        $importacion = ImportacionPlantillaCosteo::query()->firstOrFail();
        $this->actingAs($this->logistica)
            ->post(route('plantillas-costeo.importaciones.confirmar', $importacion))
            ->assertSessionHasErrors('importacion');

        $this->assertDatabaseCount('plantillas_costeo', 0);
    }

    private function crearExcel(): string
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('COSTO SCH-40');
        $hoja->fromArray([
            ['CISTERNA DE AGUA 5000 GLNS'],
            [],
            ['CODIGO', 'CANT.', 'DESCRIPCION DEL PRODUCTO', 'U.M', 'PROVEEDOR', 'COSTO UNITARIO (SOLES +IGV)', 'COSTO TOTAL SOLES', '%', null, null, null, null, null, 'T.C'],
            [null, null, 'ESTRUCTURA DEL TANQUE'],
            ['10154', 2.5, 'MANTA DE CISTERNA', 'M', null, 100, 250, 0.10, null, null, null, null, null, 3.8],
            [null, null, 'SERVICIOS'],
            ['20001', 2, 'ALQUILER DE EQUIPO DE CORTE', 'DIA', null, 80, 160, 0, null, null, null, null, null, 3.8],
            [null, null, 'EQUIPOS DE PROTECCION PERSONAL (EPP)'],
            ['IGV', 1, 'CASCO DE SEGURIDAD', 'EPP', null, 20, 20, 0, null, null, null, null, null, 3.8],
            [null, null, 'PAGO DE PERSONAL / PLAME / AFP'],
            ['30000', 1, 'TECNICO SOLDADOR', 'DIA', null, 100, 100, 0, null, null, null, null, null, 3.8],
        ], null, 'A1');

        $temporal = tempnam(sys_get_temp_dir(), 'hidroil_costeo_');
        $ruta = $temporal . '.xlsx';
        unlink($temporal);
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }
}
