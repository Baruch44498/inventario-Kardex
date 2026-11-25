<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RememberLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_iniciar_sesion_con_mantener_sesion_guarda_token_y_cookie(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'remember_token'));

        $usuario = $this->usuario();

        $respuesta = $this->post(route('login.store'), [
            'email' => $usuario->email,
            'password' => 'Password123',
            'remember' => '1',
        ]);

        $respuesta->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors()
            ->assertCookie(Auth::guard('web')->getRecallerName());

        $this->assertAuthenticatedAs($usuario);
        $this->assertNotEmpty($usuario->fresh()->getRememberToken());
    }

    public function test_iniciar_sesion_sin_mantener_sesion_no_guarda_token(): void
    {
        $usuario = $this->usuario();

        $this->post(route('login.store'), [
            'email' => $usuario->email,
            'password' => 'Password123',
        ])->assertRedirect(route('dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($usuario);
        $this->assertDatabaseHas('users', [
            'id' => $usuario->id,
            'remember_token' => null,
        ]);
    }

    private function usuario(): User
    {
        return User::query()->create([
            'username' => 'admin_prueba',
            'email' => 'admin_prueba@hidroil.test',
            'password' => 'Password123',
            'estado' => true,
        ]);
    }
}
