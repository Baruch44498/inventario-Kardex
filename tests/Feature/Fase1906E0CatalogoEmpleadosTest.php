<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1906E0CatalogoEmpleadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_puede_listar_registrar_y_actualizar_empleados(): void
    {
        $administrador = $this->usuarioConRol('ADMINISTRADOR', 'admin_empleados');

        $this->actingAs($administrador)
            ->get(route('empleados.index'))
            ->assertOk()
            ->assertSee('Lista de empleados')
            ->assertSee('Nuevo empleado');

        $this->actingAs($administrador)
            ->post(route('empleados.store'), [
                'nombre_completo' => '  Juan   Pérez Ramírez  ',
                'dni' => '74859621',
                'estado' => '1',
            ])
            ->assertSessionHasNoErrors();

        $empleado = Empleado::query()->sole();

        $this->assertSame('Juan Pérez Ramírez', $empleado->nombre_completo);
        $this->assertSame('74859621', $empleado->dni);
        $this->assertTrue($empleado->estado);
        $this->assertSame($administrador->id, $empleado->registrado_por);
        $this->assertSame($administrador->id, $empleado->actualizado_por);

        $this->actingAs($administrador)
            ->put(route('empleados.update', $empleado), [
                'nombre_completo' => 'Juan Pérez Rojas',
                'dni' => '74859621',
                'estado' => '1',
            ])
            ->assertRedirect(route('empleados.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('empleados', [
            'id' => $empleado->id,
            'nombre_completo' => 'Juan Pérez Rojas',
            'dni' => '74859621',
            'estado' => 1,
        ]);
    }

    public function test_dni_debe_tener_ocho_digitos_y_ser_unico(): void
    {
        $administrador = $this->usuarioConRol('ADMINISTRADOR', 'admin_dni');
        Empleado::query()->create([
            'nombre_completo' => 'Empleado existente',
            'dni' => '12345678',
            'estado' => true,
            'registrado_por' => $administrador->id,
            'actualizado_por' => $administrador->id,
        ]);

        $this->actingAs($administrador)
            ->post(route('empleados.store'), [
                'nombre_completo' => 'DNI incompleto',
                'dni' => '1234567',
                'estado' => '1',
            ])
            ->assertSessionHasErrors('dni');

        $this->actingAs($administrador)
            ->post(route('empleados.store'), [
                'nombre_completo' => 'DNI repetido',
                'dni' => '12345678',
                'estado' => '1',
            ])
            ->assertSessionHasErrors('dni');

        $this->assertSame(1, Empleado::query()->count());
    }

    public function test_solo_administrador_puede_gestionar_el_catalogo(): void
    {
        $almacen = $this->usuarioConRol('ALMACEN', 'almacen_empleados');
        $empleado = Empleado::query()->create([
            'nombre_completo' => 'Empleado protegido',
            'dni' => '87654321',
            'estado' => true,
        ]);

        $this->actingAs($almacen)
            ->get(route('empleados.index'))
            ->assertForbidden();

        $this->actingAs($almacen)
            ->post(route('empleados.store'), [
                'nombre_completo' => 'Registro no permitido',
                'dni' => '11223344',
                'estado' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($almacen)
            ->put(route('empleados.update', $empleado), [
                'nombre_completo' => 'Cambio no permitido',
                'dni' => '87654321',
                'estado' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($almacen)
            ->patch(route('empleados.toggle', $empleado))
            ->assertForbidden();

        $this->assertDatabaseMissing('empleados', [
            'dni' => '11223344',
        ]);
        $this->assertSame('Empleado protegido', $empleado->fresh()->nombre_completo);
        $this->assertTrue($empleado->fresh()->estado);
    }

    public function test_desactivar_conserva_empleado_y_listado_busca_por_nombre_o_dni(): void
    {
        $administrador = $this->usuarioConRol('ADMINISTRADOR', 'admin_busqueda');
        $empleado = Empleado::query()->create([
            'nombre_completo' => 'María Torres López',
            'dni' => '44556677',
            'estado' => true,
            'registrado_por' => $administrador->id,
            'actualizado_por' => $administrador->id,
        ]);

        $this->actingAs($administrador)
            ->patch(route('empleados.toggle', $empleado))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('empleados', [
            'id' => $empleado->id,
            'dni' => '44556677',
            'estado' => 0,
        ]);

        $this->actingAs($administrador)
            ->get(route('empleados.index', ['q' => '44556677', 'estado' => '0']))
            ->assertOk()
            ->assertSee('María Torres López')
            ->assertSee('INACTIVO');
    }

    private function usuarioConRol(string $codigoRol, string $username): User
    {
        $rol = Role::query()->where('codigo', $codigoRol)->firstOrFail();

        return User::query()->create([
            'role_id' => $rol->id,
            'username' => $username,
            'email' => "{$username}@hidroil.test",
            'password' => 'Password123',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
