<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1906E01VinculoEmpleadoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_si_hay_varios_administradores_uno_puede_confirmarse_como_principal_una_sola_vez(): void
    {
        $javier = $this->usuarioConRol('ADMINISTRADOR', 'javier');
        $otroAdministrador = $this->usuarioConRol('ADMINISTRADOR', 'admin_existente');

        $this->actingAs($javier)
            ->post(route('usuarios.establecer-principal'))
            ->assertSessionHas('success');

        $this->assertTrue($javier->fresh()->esAdministradorPrincipal());

        $this->actingAs($otroAdministrador)
            ->post(route('usuarios.establecer-principal'))
            ->assertSessionHas('error');

        $this->assertFalse($otroAdministrador->fresh()->esAdministradorPrincipal());
        $this->assertSame(
            1,
            User::query()->where('es_administrador_principal', true)->count()
        );
    }

    public function test_javier_puede_vincularse_una_vez_y_su_autoridad_queda_protegida(): void
    {
        $javier = $this->usuarioConRol('ADMINISTRADOR', 'javier_principal', true);
        $empleadoJavier = $this->crearEmpleado('Javier Dueño Hidroil', '70000001');
        $otroEmpleado = $this->crearEmpleado('Empleado alternativo', '70000002');

        $this->actingAs($javier)
            ->put(route('usuarios.update', $javier), $this->datosUsuario(
                $javier,
                $empleadoJavier,
                $javier->role
            ))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($empleadoJavier->id, $javier->fresh()->empleado_id);

        $this->actingAs($javier)
            ->from(route('usuarios.edit', $javier))
            ->put(route('usuarios.update', $javier), $this->datosUsuario(
                $javier,
                $otroEmpleado,
                $javier->role
            ))
            ->assertSessionHas('error');

        $this->assertSame($empleadoJavier->id, $javier->fresh()->empleado_id);
        $this->assertTrue($javier->fresh()->estado);
        $this->assertTrue($javier->fresh()->esAdministradorPrincipal());
    }

    public function test_edicion_de_usuario_vinculado_incluye_su_empleado_sin_error(): void
    {
        $empleadoJavier = $this->crearEmpleado('Javier Vinculado', '70000011');
        $javier = $this->usuarioConRol(
            'ADMINISTRADOR',
            'javier_edicion',
            true,
            $empleadoJavier
        );

        $this->actingAs($javier)
            ->get(route('usuarios.edit', $javier))
            ->assertOk()
            ->assertSee('Javier Vinculado')
            ->assertSee('DNI 70000011')
            ->assertSee('ADMINISTRADOR PRINCIPAL');
    }

    public function test_usuarios_nuevos_exigen_empleado_y_solo_principal_otorga_rol_admin(): void
    {
        $javier = $this->usuarioConRol('ADMINISTRADOR', 'javier_crea', true);
        $rolAdministrador = $javier->role;
        $empleadoDelegado = $this->crearEmpleado('Administrador Delegado', '70000003');

        $this->actingAs($javier)
            ->post(route('usuarios.store'), [
                'role_id' => $rolAdministrador->id,
                'username' => 'sin_empleado',
                'email' => 'sin_empleado@hidroil.test',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'estado' => '1',
            ])
            ->assertSessionHasErrors('empleado_id');

        $this->actingAs($javier)
            ->post(route('usuarios.store'), [
                'empleado_id' => $empleadoDelegado->id,
                'role_id' => $rolAdministrador->id,
                'username' => 'admin_delegado',
                'email' => 'admin_delegado@hidroil.test',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'estado' => '1',
            ])
            ->assertSessionHasNoErrors();

        $delegado = User::query()->where('username', 'admin_delegado')->firstOrFail();
        $this->assertFalse($delegado->esAdministradorPrincipal());
        $this->assertSame($empleadoDelegado->id, $delegado->empleado_id);

        $otroEmpleado = $this->crearEmpleado('Administrador no autorizado', '70000004');

        $this->actingAs($delegado)
            ->post(route('usuarios.store'), [
                'empleado_id' => $otroEmpleado->id,
                'role_id' => $rolAdministrador->id,
                'username' => 'admin_no_autorizado',
                'email' => 'admin_no_autorizado@hidroil.test',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'estado' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'admin_no_autorizado']);
    }

    public function test_administradores_delegados_no_modifican_al_principal_ni_a_sus_pares(): void
    {
        $javier = $this->usuarioConRol('ADMINISTRADOR', 'javier_protegido', true);
        $empleadoJavier = $this->crearEmpleado('Javier Principal', '70000005');
        $javier->update(['empleado_id' => $empleadoJavier->id]);

        $delegadoUno = $this->usuarioConRol(
            'ADMINISTRADOR',
            'delegado_uno',
            false,
            $this->crearEmpleado('Delegado Uno', '70000006')
        );
        $delegadoDos = $this->usuarioConRol(
            'ADMINISTRADOR',
            'delegado_dos',
            false,
            $this->crearEmpleado('Delegado Dos', '70000007')
        );

        $this->actingAs($delegadoUno)
            ->get(route('usuarios.edit', $javier))
            ->assertForbidden();

        $this->actingAs($delegadoUno)
            ->get(route('usuarios.edit', $delegadoDos))
            ->assertForbidden();

        $this->actingAs($delegadoUno)
            ->patch(route('usuarios.toggle', $delegadoDos))
            ->assertForbidden();

        $this->actingAs($delegadoUno)
            ->get(route('empleados.edit', $empleadoJavier))
            ->assertForbidden();

        $this->assertTrue($javier->fresh()->estado);
        $this->assertTrue($delegadoDos->fresh()->estado);
    }

    public function test_principal_puede_retirar_el_rol_a_un_administrador_delegado(): void
    {
        $javier = $this->usuarioConRol('ADMINISTRADOR', 'javier_degrada', true);
        $empleadoDelegado = $this->crearEmpleado('Delegado a Almacén', '70000008');
        $delegado = $this->usuarioConRol(
            'ADMINISTRADOR',
            'delegado_degradable',
            false,
            $empleadoDelegado
        );
        $rolAlmacen = Role::query()->where('codigo', 'ALMACEN')->firstOrFail();

        $this->actingAs($javier)
            ->put(route('usuarios.update', $delegado), $this->datosUsuario(
                $delegado,
                $empleadoDelegado,
                $rolAlmacen
            ))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($rolAlmacen->id, $delegado->fresh()->role_id);
        $this->assertFalse($delegado->fresh()->esAdministrador());
    }

    public function test_desactivar_empleado_desactiva_usuario_y_reactivarlo_no_devuelve_acceso(): void
    {
        $javier = $this->usuarioConRol('ADMINISTRADOR', 'javier_cascada', true);
        $empleado = $this->crearEmpleado('Trabajador de Almacén', '70000009');
        $usuario = $this->usuarioConRol('ALMACEN', 'trabajador_almacen', false, $empleado);

        $this->actingAs($javier)
            ->patch(route('empleados.toggle', $empleado))
            ->assertSessionHas('success');

        $this->assertFalse($empleado->fresh()->estado);
        $this->assertFalse($usuario->fresh()->estado);

        $this->actingAs($javier)
            ->patch(route('empleados.toggle', $empleado))
            ->assertSessionHas('success');

        $this->assertTrue($empleado->fresh()->estado);
        $this->assertFalse($usuario->fresh()->estado);

        $this->actingAs($javier)
            ->patch(route('usuarios.toggle', $usuario))
            ->assertSessionHas('success');

        $this->assertTrue($usuario->fresh()->estado);
    }

    public function test_empleado_del_principal_no_puede_inhabilitarse(): void
    {
        $empleadoJavier = $this->crearEmpleado('Javier Protegido', '70000010');
        $javier = $this->usuarioConRol(
            'ADMINISTRADOR',
            'javier_inmortal',
            true,
            $empleadoJavier
        );

        $this->actingAs($javier)
            ->patch(route('empleados.toggle', $empleadoJavier))
            ->assertSessionHas('error');

        $this->actingAs($javier)
            ->patch(route('usuarios.toggle', $javier))
            ->assertSessionHas('error');

        $this->assertTrue($empleadoJavier->fresh()->estado);
        $this->assertTrue($javier->fresh()->estado);
        $this->assertTrue($javier->fresh()->esAdministradorPrincipal());
    }

    private function crearEmpleado(string $nombre, string $dni): Empleado
    {
        return Empleado::query()->create([
            'nombre_completo' => $nombre,
            'dni' => $dni,
            'estado' => true,
        ]);
    }

    private function usuarioConRol(
        string $codigoRol,
        string $username,
        bool $principal = false,
        ?Empleado $empleado = null
    ): User {
        $rol = Role::query()->where('codigo', $codigoRol)->firstOrFail();
        $usuario = User::query()->create([
            'role_id' => $rol->id,
            'empleado_id' => $empleado?->id,
            'username' => $username,
            'email' => "{$username}@hidroil.test",
            'password' => 'Password123',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);

        if ($principal) {
            $usuario->forceFill(['es_administrador_principal' => true])->save();
        }

        return $usuario->fresh(['role', 'empleado']);
    }

    private function datosUsuario(
        User $usuario,
        Empleado $empleado,
        Role $rol
    ): array {
        return [
            'empleado_id' => $empleado->id,
            'role_id' => $rol->id,
            'username' => $usuario->username,
            'email' => $usuario->email,
            'estado' => '1',
            'password' => '',
            'password_confirmation' => '',
        ];
    }
}
