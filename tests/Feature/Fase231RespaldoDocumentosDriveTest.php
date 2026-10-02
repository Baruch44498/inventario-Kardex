<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\DriveConexion;
use App\Models\ImportacionCotizacionProveedor;
use App\Models\OrdenOperacion;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\TipoOrden;
use App\Models\User;
use App\Services\Ordenes\ExportarGastoRealOrdenService;
use App\Services\Ordenes\GastoRealOrdenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class Fase231RespaldoDocumentosDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('b', 32)));
        config()->set('services.hidroil_drive.client_id', 'cliente-prueba');
        config()->set('services.hidroil_drive.client_secret', 'secreto-prueba');
        Storage::fake('local');
    }

    public function test_respalda_originales_directos_y_heredados_sin_duplicar(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $this->conectar($admin);
        $directa = $this->cotizacion($admin, '2311');
        $heredada = $this->cotizacion($admin, '2312');
        Storage::disk('local')->put('cotizaciones/directa.pdf', '%PDF-directa');
        Storage::disk('local')->put('cotizaciones/heredada.xlsx', 'excel-heredado');
        $directa->update(['archivo_original_path' => 'cotizaciones/directa.pdf', 'archivo_original_nombre' => 'directa.pdf']);
        ImportacionCotizacionProveedor::query()->create([
            'requisicion_id' => $heredada->requisicion_id, 'proveedor_id' => $heredada->proveedor_id,
            'cotizacion_id' => $heredada->id, 'tipo_archivo' => 'EXCEL',
            'nombre_original' => 'heredada.xlsx', 'ruta_archivo' => 'cotizaciones/heredada.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'datos_extraidos' => [], 'estado' => 'CONFIRMADA', 'creado_por' => $admin->id,
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-prueba'], 200),
            'https://www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'drive-cotizacion'], 200),
        ]);

        $this->actingAs($admin)->get(route('drive.index'))
            ->assertOk()->assertSee($directa->codigo)->assertSee($heredada->codigo);
        $this->post(route('drive.cotizaciones-proveedor.subir', $directa))->assertRedirect(route('drive.index'));
        $this->post(route('drive.cotizaciones-proveedor.subir', $heredada))->assertRedirect(route('drive.index'));
        $this->post(route('drive.cotizaciones-proveedor.subir', $heredada))->assertRedirect(route('drive.index'));
        $this->assertDatabaseCount('documentos_drive', 2);
        $this->assertDatabaseHas('documentos_drive', ['tipo' => 'COTIZACION_PROVEEDOR', 'origen_id' => $heredada->id, 'estado' => 'COMPLETO']);
        Http::assertSentCount(4);
    }

    public function test_excel_de_gasto_real_se_respalda_solo_para_orden_principal_cerrada(): void
    {
        $admin = $this->usuario('ADMINISTRADOR');
        $this->conectar($admin);
        $tipo = TipoOrden::query()->firstOrCreate(['codigo' => 'OM'], ['nombre' => 'Mantenimiento', 'estado' => true]);
        $orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipo->id, 'codigo_orden' => 'OM-2310',
            'fecha_apertura' => today(), 'estado' => 'CERRADA', 'creado_por' => $admin->id,
        ]);
        $this->mock(GastoRealOrdenService::class, fn ($mock) => $mock->shouldReceive('construir')->once()->andReturn(['orden' => 'OM-2310']));
        $this->mock(ExportarGastoRealOrdenService::class, fn ($mock) => $mock->shouldReceive('libro')->once()->andReturn(new Spreadsheet()));
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-prueba'], 200),
            'https://www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'drive-excel'], 200),
        ]);

        $this->actingAs($admin)->post(route('drive.gasto-real.subir', $orden))
            ->assertRedirect(route('drive.index'));
        $this->assertDatabaseHas('documentos_drive', [
            'tipo' => 'GASTO_REAL_ORDEN', 'origen_id' => $orden->id,
            'estado' => 'COMPLETO', 'drive_id' => 'drive-excel',
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/upload/drive/v3/files')
            && str_contains($request->body(), 'HIDROIL-GASTO-REAL-OM-2310'));

        $orden->update(['estado' => 'EN_PROCESO']);
        $this->post(route('drive.gasto-real.subir', $orden))->assertNotFound();
    }

    private function conectar(User $usuario): void
    {
        DriveConexion::query()->create(['refresh_token' => 'refresh-prueba', 'conectado_por' => $usuario->id]);
    }

    private function cotizacion(User $usuario, string $numero): Cotizacion
    {
        $proveedor = Proveedor::query()->create(['razon_social' => 'Proveedor '.$numero, 'ruc' => '2060000'.$numero, 'estado' => true]);
        $requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-'.$numero, 'fecha_solicitud' => today(), 'solicitado_por' => $usuario->id,
        ]);

        return Cotizacion::query()->create([
            'requisicion_id' => $requisicion->id, 'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-'.$numero, 'fecha_cotizacion' => today(),
            'registrado_por' => $usuario->id,
        ]);
    }

    private function usuario(string $codigo): User
    {
        $rol = Role::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $codigo, 'estado' => true]);

        return User::query()->create([
            'role_id' => $rol->id, 'username' => 'admin-drive-231',
            'email' => 'admin-drive-231@example.com', 'password' => 'clave-de-prueba',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }
}
