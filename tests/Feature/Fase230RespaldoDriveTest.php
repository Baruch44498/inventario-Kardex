<?php

namespace Tests\Feature;

use App\Models\DriveConexion;
use App\Models\Cotizacion;
use App\Models\FacturaProveedor;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\User;
use App\Services\Documentos\GoogleDriveRespaldoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Fase230RespaldoDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    public function test_autorizacion_protegida_y_subida_con_respuesta_simulada(): void
    {
        config()->set('services.hidroil_drive.client_id', 'cliente-prueba');
        config()->set('services.hidroil_drive.client_secret', 'secreto-prueba');
        $admin = $this->usuario('ADMINISTRADOR');
        $almacen = $this->usuario('ALMACEN');

        $this->actingAs($almacen)->get(route('drive.index'))->assertForbidden();
        $this->actingAs($almacen)->get(route('drive.conectar'))->assertForbidden();
        $this->actingAs($admin)->get(route('drive.index'))->assertOk();

        Http::fake([
            'https://oauth2.googleapis.com/token' => function ($request) {
                return Http::response($request->data()['grant_type'] === 'authorization_code'
                    ? ['refresh_token' => 'refresh-prueba', 'access_token' => 'access-inicial']
                    : ['access_token' => 'access-renovado'], 200);
            },
            'https://www.googleapis.com/upload/drive/v3/files*' => Http::response([
                'id' => 'archivo-prueba',
                'webViewLink' => 'https://drive.google.com/file/d/archivo-prueba/view',
            ], 200),
        ]);

        $inicio = $this->actingAs($admin)->get(route('drive.conectar'))->assertRedirect();
        $url = $inicio->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $parametros);
        $this->get(route('drive.callback', ['state' => 'estado-incorrecto', 'code' => 'codigo']))
            ->assertForbidden();

        $inicio = $this->get(route('drive.conectar'))->assertRedirect();
        parse_str(parse_url($inicio->headers->get('Location'), PHP_URL_QUERY), $parametros);
        $this->get(route('drive.callback', ['state' => $parametros['state'], 'code' => 'codigo']))
            ->assertRedirect(route('drive.index'));

        $this->assertSame('refresh-prueba', DriveConexion::query()->firstOrFail()->refresh_token);
        $this->assertNotSame('refresh-prueba', DB::table('drive_conexiones')->value('refresh_token'));

        Storage::fake('local');
        Storage::disk('local')->put('comprobante.pdf', '%PDF-comprobante-prueba');
        $resultado = app(GoogleDriveRespaldoService::class)->subir(
            Storage::disk('local')->path('comprobante.pdf'), 'comprobante.pdf', 'application/pdf'
        );
        $this->assertSame('archivo-prueba', $resultado['id']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/upload/drive/v3/files')
            && str_contains($request->body(), '%PDF-comprobante-prueba')
            && $request->hasHeader('Authorization', 'Bearer access-renovado'));
    }

    public function test_sin_credenciales_la_pantalla_muestra_configuracion_y_no_inicia_oauth(): void
    {
        config()->set('services.hidroil_drive.client_id', null);
        config()->set('services.hidroil_drive.client_secret', null);
        $this->actingAs($this->usuario('ADMINISTRADOR'))
            ->get(route('drive.index'))->assertOk()->assertSee('Falta configurar OAuth');
        $this->get(route('drive.conectar'))->assertRedirect()->assertSessionHas('error');
    }

    public function test_comprobante_se_respalda_una_vez_y_rechaza_archivo_modificado(): void
    {
        config()->set('services.hidroil_drive.client_id', 'cliente-prueba');
        config()->set('services.hidroil_drive.client_secret', 'secreto-prueba');
        $admin = $this->usuario('ADMINISTRADOR');
        $factura = $this->factura($admin);
        DriveConexion::query()->create(['refresh_token' => 'refresh-prueba', 'conectado_por' => $admin->id]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-prueba'], 200),
            'https://www.googleapis.com/upload/drive/v3/files*' => Http::response([
                'id' => 'archivo-drive', 'webViewLink' => 'https://drive.google.com/file/d/archivo-drive/view',
            ], 200),
        ]);

        $this->actingAs($admin)->post(route('drive.facturas.subir', $factura))
            ->assertRedirect(route('drive.index'));
        $this->assertDatabaseHas('documentos_drive', [
            'tipo' => 'FACTURA_PROVEEDOR', 'origen_id' => $factura->id,
            'estado' => 'COMPLETO', 'drive_id' => 'archivo-drive',
        ]);
        $this->post(route('drive.facturas.subir', $factura))->assertRedirect(route('drive.index'));
        Http::assertSentCount(2);

        Storage::disk('local')->put($factura->archivo_original_path, '%PDF-modificado');
        $this->post(route('drive.facturas.subir', $factura))
            ->assertSessionHas('error');
        Http::assertSentCount(2);
    }

    private function factura(User $usuario): FacturaProveedor
    {
        Storage::fake('local');
        $contenido = '%PDF-documento-original';
        Storage::disk('local')->put('facturas-proveedor/factura.pdf', $contenido);
        $proveedor = Proveedor::query()->create([
            'razon_social' => 'Proveedor Drive', 'ruc' => '20600002301', 'estado' => true,
        ]);
        $requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-2301', 'fecha_solicitud' => today(), 'solicitado_por' => $usuario->id,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requisicion->id, 'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-2301', 'fecha_cotizacion' => today(), 'registrado_por' => $usuario->id,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id, 'codigo' => 'SC-2301',
            'fecha_solicitud' => today(), 'solicitado_por' => $usuario->id,
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id, 'proveedor_id' => $proveedor->id,
            'codigo' => 'OC-2301', 'fecha_emision' => today(),
            'emitido_por' => $usuario->id, 'moneda' => 'PEN', 'total' => 100,
        ]);

        return FacturaProveedor::query()->create([
            'orden_compra_id' => $orden->id, 'proveedor_id' => $proveedor->id,
            'tipo_documento' => 'FACTURA', 'serie' => 'F001', 'numero' => '2301',
            'fecha_emision' => today(), 'moneda' => 'PEN', 'total' => 100,
            'archivo_original_path' => 'facturas-proveedor/factura.pdf',
            'archivo_original_nombre' => 'factura.pdf',
            'archivo_original_mime' => 'application/pdf',
            'archivo_original_hash' => hash('sha256', $contenido),
            'registrado_por' => $usuario->id, 'estado' => 'REGISTRADA',
        ]);
    }

    private function usuario(string $codigo): User
    {
        $rol = Role::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $codigo, 'estado' => true]);
        $nombre = strtolower($codigo).'-'.(User::query()->count() + 1);

        return User::query()->create([
            'role_id' => $rol->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'clave-de-prueba',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }
}
