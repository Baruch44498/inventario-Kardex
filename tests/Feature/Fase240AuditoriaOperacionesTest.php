<?php

namespace Tests\Feature;

use App\Models\AuditoriaEvento;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class Fase240AuditoriaOperacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_actor_cambios_y_filtros_sin_revelar_valores_de_contrasena(): void
    {
        $administrador = $this->usuario('ADMINISTRADOR', 'admin-auditoria');
        $this->actingAs($administrador);
        $almacen = $this->usuario('ALMACEN', 'almacen-auditoria');
        $almacen->update(['estado' => false, 'password' => 'nueva-clave-privada']);

        $evento = AuditoriaEvento::query()
            ->where('entidad', 'User')->where('entidad_id', $almacen->id)
            ->where('accion', 'ACTUALIZADO')->firstOrFail();
        $this->assertSame($administrador->id, $evento->usuario_id);
        $this->assertContains('estado', $evento->campos);
        $this->assertContains('password', $evento->campos);
        $this->assertStringNotContainsString('nueva-clave-privada', json_encode($evento->getAttributes()));

        $this->get(route('auditoria.index', ['entidad' => 'User', 'accion' => 'ACTUALIZADO']))
            ->assertOk()->assertSee('almacen-auditoria')->assertDontSee('nueva-clave-privada');
        $this->get(route('modulos.show', 'auditoria'))->assertRedirect(route('auditoria.index'));
        $this->actingAs($almacen)->get(route('auditoria.index'))
                ->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    public function test_evento_se_revierte_junto_con_la_operacion_y_omite_accesos_sin_cambios_de_negocio(): void
    {
        $administrador = $this->usuario('ADMINISTRADOR', 'admin-transaccion');
        $this->actingAs($administrador);
        $total = AuditoriaEvento::query()->count();

        $administrador->update(['ultimo_acceso_en' => now()]);
        $this->assertSame($total, AuditoriaEvento::query()->count());

        try {
            DB::transaction(function () use ($administrador): void {
                $administrador->update(['estado' => false]);
                throw new RuntimeException('Revertir operación');
            });
        } catch (RuntimeException) {
            // La operación fallida no debe permanecer en la bitácora.
        }

        $this->assertSame($total, AuditoriaEvento::query()->count());
        $this->assertTrue($administrador->fresh()->estado);
    }

    private function usuario(string $rolCodigo, string $nombre): User
    {
        $rol = Role::query()->firstOrCreate(['codigo' => $rolCodigo], [
            'nombre' => $rolCodigo, 'estado' => true,
        ]);

        return User::query()->create([
            'role_id' => $rol->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'clave-inicial',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }
}
