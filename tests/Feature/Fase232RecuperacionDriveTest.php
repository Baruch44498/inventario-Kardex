<?php

namespace Tests\Feature;

use App\Models\DocumentoDrive;
use App\Models\DriveConexion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Fase232RecuperacionDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('c', 32)));
        config()->set('services.hidroil_drive.client_id', 'cliente-prueba');
        config()->set('services.hidroil_drive.client_secret', 'secreto-prueba');
    }

    public function test_descarga_copia_solo_si_el_contenido_coincide_con_sha256(): void
    {
        $admin = $this->usuario('ADMINISTRADOR', 'admin-recuperacion');
        $almacen = $this->usuario('ALMACEN', 'almacen-recuperacion');
        DriveConexion::query()->create(['refresh_token' => 'refresh-prueba', 'conectado_por' => $admin->id]);
        $original = 'contenido-original';
        $contenidoRemoto = $original;
        $registro = DocumentoDrive::query()->create([
            'tipo' => 'FACTURA_PROVEEDOR', 'origen_id' => 232,
            'hash_sha256' => hash('sha256', $original),
            'estado' => 'COMPLETO', 'drive_id' => 'drive-232',
            'registrado_por' => $admin->id,
        ]);
        Http::fake(function ($solicitud) use (&$contenidoRemoto, $original) {
            $url = $solicitud->url();
            if (str_contains($url, 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'access-prueba'], 200);
            }
            if (str_contains($url, 'alt=media')) {
                return Http::response($contenidoRemoto, 200);
            }

            return Http::response([
                'id' => 'drive-232', 'name' => '../respaldo.pdf',
                'size' => (string) strlen($original),
                'capabilities' => ['canDownload' => true],
            ], 200);
        });

        $this->get(route('drive.documentos.descargar', $registro))->assertRedirect(route('login'));
        $this->actingAs($almacen)->get(route('drive.documentos.descargar', $registro))->assertForbidden();
        Http::assertNothingSent();

        $respuesta = $this->actingAs($admin)->get(route('drive.documentos.descargar', $registro))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $directivasCache = array_map('trim', explode(',', $respuesta->headers->get('Cache-Control', '')));
        $this->assertContains('private', $directivasCache);
        $this->assertContains('no-store', $directivasCache);
        $this->assertStringContainsString('respaldo.pdf', $respuesta->headers->get('Content-Disposition'));
        $this->assertSame($original, $respuesta->streamedContent());
        Http::assertSentCount(3);

        $contenidoRemoto = 'contenido-alterado';
        $this->get(route('drive.documentos.descargar', $registro))
            ->assertRedirect(route('drive.index'))->assertSessionHas('error');
        $registro->update(['estado' => 'ERROR']);
        $this->get(route('drive.documentos.descargar', $registro))->assertNotFound();
    }

    public function test_rechaza_copia_sin_permisos_de_drive_o_mayor_al_limite(): void
    {
        $admin = $this->usuario('ADMINISTRADOR', 'admin-limite');
        DriveConexion::query()->create(['refresh_token' => 'refresh-prueba', 'conectado_por' => $admin->id]);
        $registro = DocumentoDrive::query()->create([
            'tipo' => 'GASTO_REAL_ORDEN', 'origen_id' => 1,
            'hash_sha256' => hash('sha256', 'archivo'), 'estado' => 'COMPLETO',
            'drive_id' => 'drive-limitado', 'registrado_por' => $admin->id,
        ]);
        $permitido = false;
        Http::fake(function ($solicitud) use (&$permitido) {
            if (str_contains($solicitud->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'access-prueba'], 200);
            }

            return Http::response([
                'id' => 'drive-limitado', 'name' => 'gasto.xlsx',
                'size' => (string) (16 * 1024 * 1024),
                'capabilities' => ['canDownload' => $permitido],
            ], 200);
        });

        $this->actingAs($admin)->get(route('drive.documentos.descargar', $registro))
            ->assertRedirect(route('drive.index'))->assertSessionHas('error');
        $permitido = true;
        $this->get(route('drive.documentos.descargar', $registro))
            ->assertRedirect(route('drive.index'))->assertSessionHas('error');
        Http::assertSentCount(4);
    }

    private function usuario(string $codigo, string $nombre): User
    {
        $rol = Role::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $codigo, 'estado' => true]);

        return User::query()->create([
            'role_id' => $rol->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'clave-de-prueba',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }
}
