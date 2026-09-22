<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Fase19034RecorridoVisualTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $codigoRol): User
    {
        $role = Role::query()->create([
            'codigo' => $codigoRol,
            'nombre' => $codigoRol,
            'estado' => true,
        ]);

        return User::query()->create([
            'role_id' => $role->id,
            'username' => 'revision_'.strtolower($codigoRol),
            'email' => strtolower($codigoRol).'@example.com',
            'password' => 'clave-de-prueba',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }

    public function test_acceso_requiere_sesion_y_permiso_de_panel(): void
    {
        $this->get(route('revision-visual.index'))
            ->assertRedirect(route('login'));

        $role = Role::query()->create(['codigo' => 'SIN_PERMISOS', 'nombre' => 'Sin permisos', 'estado' => true]);
        $usuario = User::query()->create([
            'role_id' => $role->id,
            'username' => 'sin_permisos_19034',
            'email' => 'sin_permisos_19034@example.com',
            'password' => 'clave-de-prueba',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        $this->actingAs($usuario)
            ->get(route('revision-visual.index'))
            ->assertForbidden();
    }

    public function test_el_recorrido_muestra_solo_las_pantallas_del_perfil(): void
    {
        $administrador = $this->usuario('ADMINISTRADOR');
        $this->actingAs($administrador)
            ->get(route('revision-visual.index'))
            ->assertOk()
            ->assertSee('Recorrido visual')
            ->assertSee('href="'.route('cotizaciones-cliente.index').'"', false)
            ->assertSee('href="'.route('kardex.index').'"', false)
            ->assertSee('href="'.route('usuarios.index').'"', false);

        $almacen = $this->usuario('ALMACEN');
        $this->actingAs($almacen)
            ->get(route('revision-visual.index'))
            ->assertOk()
            ->assertSee('href="'.route('notas-ingreso.index').'"', false)
            ->assertSee('href="'.route('inventario.index').'"', false)
            ->assertDontSee('href="'.route('usuarios.index').'"', false)
            ->assertDontSee('href="'.route('cotizaciones-proveedor.index').'"', false);
    }

    public function test_todos_los_enlaces_del_recorrido_son_rutas_get_sin_parametros_obligatorios(): void
    {
        $this->assertSame('/revision-visual', route('revision-visual.index', [], false));

        foreach (config('hidroil_revision_visual.grupos') as $grupo) {
            foreach ($grupo['enlaces'] as $enlace) {
                $ruta = Route::getRoutes()->getByName($enlace['ruta']);

                $this->assertNotNull($ruta, $enlace['ruta']);
                $this->assertContains('GET', $ruta->methods(), $enlace['ruta']);
                $this->assertSame([], $ruta->parameterNames(), $enlace['ruta']);
                $this->assertNotEmpty($enlace['permisos'], $enlace['ruta']);
            }
        }
    }
}
