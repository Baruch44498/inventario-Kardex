<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RutasHeredadasModulosTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_rutas_heredadas_llevan_a_los_modulos_vigentes(): void
    {
        $this->actingAs($this->usuario('ADMINISTRADOR'));

        foreach ([
            'clientes' => 'clientes.index',
            'facturas' => 'facturas-proveedor.index',
            'proformas' => 'proformas.index',
        ] as $modulo => $ruta) {
            $this->get(route('modulos.show', $modulo))
                ->assertRedirect(route($ruta));
        }
    }

    public function test_las_rutas_heredadas_respetan_los_permisos_de_los_destinos(): void
    {
        $this->actingAs($this->usuario('COMERCIAL_LOGISTICA'));
        $this->get(route('modulos.show', 'clientes'))->assertRedirect(route('clientes.index'));
        $this->get(route('modulos.show', 'facturas'))->assertRedirect(route('facturas-proveedor.index'));
        $this->get(route('modulos.show', 'proformas'))->assertRedirect(route('proformas.index'));

        $this->actingAs($this->usuario('CONTABILIDAD'));
        $this->get(route('modulos.show', 'clientes'))->assertForbidden();
        $this->get(route('modulos.show', 'facturas'))->assertRedirect(route('facturas-proveedor.index'));
        $this->get(route('modulos.show', 'proformas'))->assertForbidden();

        $this->actingAs($this->usuario('ALMACEN'));
        $this->get(route('modulos.show', 'clientes'))->assertForbidden();
        $this->get(route('modulos.show', 'facturas'))->assertRedirect(route('facturas-proveedor.index'));
        $this->get(route('modulos.show', 'proformas'))->assertRedirect(route('proformas.index'));

        $this->actingAs($this->usuario('JEFE_PLANTA'));
        foreach (['clientes', 'facturas', 'proformas'] as $modulo) {
            $this->get(route('modulos.show', $modulo))->assertForbidden();
        }
    }

    private function usuario(string $codigoRol): User
    {
        $rol = Role::query()->firstOrCreate(['codigo' => $codigoRol], [
            'nombre' => $codigoRol,
            'estado' => true,
        ]);

        return User::query()->create([
            'role_id' => $rol->id,
            'username' => 'rutas_'.strtolower($codigoRol),
            'email' => 'rutas_'.strtolower($codigoRol).'@example.com',
            'password' => 'clave-inicial',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
