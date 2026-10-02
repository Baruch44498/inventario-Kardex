<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Fase242AuditoriaCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_conserva_filtros_exporta_todas_las_paginas_y_neutraliza_formulas(): void
    {
        $admin = $this->usuario('ADMINISTRADOR', 'admin-audit-csv');
        $this->actingAs($admin);

        for ($numero = 1; $numero <= 31; $numero++) {
            DB::table('auditoria_eventos')->insert([
                'usuario_id' => $admin->id, 'entidad' => 'Producto',
                'entidad_id' => $numero, 'etiqueta' => $numero === 31 ? '=SUM(1+1)' : 'PRODUCTO-'.$numero,
                'accion' => 'ACTUALIZADO', 'campos' => json_encode(['descripcion']),
                'estado_anterior' => null, 'estado_nuevo' => null, 'created_at' => now(),
            ]);
        }
        DB::table('auditoria_eventos')->insert([
            'usuario_id' => $admin->id, 'entidad' => 'Producto',
            'entidad_id' => 40, 'etiqueta' => 'FUERA-DEL-RANGO',
            'accion' => 'ACTUALIZADO', 'campos' => null,
            'created_at' => now()->subDay(),
        ]);

        $filtros = [
            'entidad' => 'Producto', 'accion' => 'ACTUALIZADO',
            'desde' => today()->toDateString(), 'hasta' => today()->toDateString(),
        ];
        $this->get(route('auditoria.index', $filtros))
            ->assertOk()->assertSee('Descargar CSV')->assertDontSee('PRODUCTO-1 <small>');

        $respuesta = $this->get(route('auditoria.csv', $filtros))->assertOk();
        $directivasCache = array_map('trim', explode(',', $respuesta->headers->get('Cache-Control', '')));
        $this->assertContains('private', $directivasCache);
        $this->assertContains('no-store', $directivasCache);
        $csv = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('PRODUCTO-1', $csv);
        $this->assertStringContainsString("'=SUM(1+1)", $csv);
        $this->assertStringNotContainsString('FUERA-DEL-RANGO', $csv);
        $this->assertCount(32, array_filter(explode("\n", trim($csv))));
    }

    public function test_csv_no_expone_valores_de_contrasena_y_exige_permiso(): void
    {
        $this->get(route('auditoria.csv'))->assertRedirect(route('login'));
        $almacen = $this->usuario('ALMACEN', 'almacen-audit-csv');
        $this->actingAs($almacen)->get(route('auditoria.csv'))->assertForbidden();

        $admin = $this->usuario('ADMINISTRADOR', 'admin-seguro-csv');
        $this->actingAs($admin);
        $almacen->update(['password' => 'CLAVE_PRIVADA_CSV_242']);

        $csv = $this->get(route('auditoria.csv', [
            'entidad' => 'User', 'accion' => 'ACTUALIZADO',
        ]))->assertOk()->streamedContent();
        $this->assertStringContainsString('password', $csv);
        $this->assertStringNotContainsString('CLAVE_PRIVADA_CSV_242', $csv);

        $this->get(route('auditoria.csv', [
            'desde' => today()->toDateString(), 'hasta' => today()->subDay()->toDateString(),
        ]))->assertSessionHasErrors('hasta');
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
