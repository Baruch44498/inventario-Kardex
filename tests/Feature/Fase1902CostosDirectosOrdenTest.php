<?php

namespace Tests\Feature;

use App\Models\CostoDirectoOrden;
use App\Models\OrdenOperacion;
use App\Models\Role;
use App\Models\TipoOrden;
use App\Models\User;
use App\Services\Ordenes\ResumenEjecucionOrdenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1902CostosDirectosOrdenTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private User $jefePlanta;
    private OrdenOperacion $orden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_1902');
        $this->jefePlanta = $this->usuarioConRol('JEFE_PLANTA', 'planta_1902');
        $tipo = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );

        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipo->id,
            'codigo_orden' => 'OP-1902-00001',
            'numero_correlativo' => 1,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para costos directos',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->logistica->id,
        ]);
    }

    public function test_logistica_registra_mano_de_obra_y_total_se_calcula_en_servidor(): void
    {
        $this->actingAs($this->logistica)
            ->post(route('ordenes-operacion.costos-directos.store', $this->orden), [
                'tipo' => 'MANO_OBRA',
                'fecha_costo' => now()->toDateString(),
                'descripcion' => 'Montaje hidráulico',
                'cantidad' => 3.5,
                'unidad' => 'HORA',
                'costo_unitario_soles' => 25,
                'total_soles' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('costos_directos_orden', [
            'orden_operacion_id' => $this->orden->id,
            'tipo' => 'MANO_OBRA',
            'cantidad' => 3.5,
            'costo_unitario_soles' => 25,
            'total_soles' => 87.5,
            'estado' => 'VIGENTE',
        ]);
    }

    public function test_planta_no_puede_ver_ni_registrar_importes(): void
    {
        $this->actingAs($this->jefePlanta)
            ->post(route('ordenes-operacion.costos-directos.store', $this->orden), [
                'tipo' => 'OTRO',
                'fecha_costo' => now()->toDateString(),
                'descripcion' => 'Intento no autorizado',
                'cantidad' => 1,
                'unidad' => 'GLOBAL',
                'costo_unitario_soles' => 50,
            ])
            ->assertForbidden();

        $this->actingAs($this->jefePlanta)
            ->get(route('ordenes-operacion.show', $this->orden))
            ->assertOk()
            ->assertDontSee('Costo real total acumulado');
    }

    public function test_anular_conserva_historial_y_excluye_el_costo_del_total(): void
    {
        $costo = CostoDirectoOrden::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'tipo' => 'TRANSPORTE',
            'fecha_costo' => now()->toDateString(),
            'descripcion' => 'Recojo manual desde agencia',
            'cantidad' => 1,
            'unidad' => 'VIAJE',
            'costo_unitario_soles' => 40,
            'total_soles' => 40,
            'estado' => 'VIGENTE',
            'registrado_por' => $this->logistica->id,
            'registrado_en' => now(),
        ]);

        $this->actingAs($this->logistica)
            ->patch(route('costos-directos-orden.anular', $costo), [
                'motivo_anulacion' => 'El traslado fue asumido por el proveedor.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('costos_directos_orden', [
            'id' => $costo->id,
            'estado' => 'ANULADO',
            'anulado_por' => $this->logistica->id,
        ]);

        $resumen = app(ResumenEjecucionOrdenService::class)
            ->construir($this->orden->fresh(), true);
        $this->assertSame(0.0, (float) $resumen['costos']['directos']);
        $this->assertSame(0.0, (float) $resumen['costos']['total_real']);
    }

    public function test_orden_cerrada_no_admite_nuevos_costos(): void
    {
        $this->orden->update(['estado' => 'CERRADA', 'cerrado_en' => now()]);

        $this->actingAs($this->logistica)
            ->post(route('ordenes-operacion.costos-directos.store', $this->orden), [
                'tipo' => 'SERVICIO_TERCERO',
                'fecha_costo' => now()->toDateString(),
                'descripcion' => 'Servicio posterior al cierre',
                'cantidad' => 1,
                'unidad' => 'SERVICIO',
                'costo_unitario_soles' => 80,
            ])
            ->assertSessionHasErrors('tipo');

        $this->assertDatabaseCount('costos_directos_orden', 0);
    }

    public function test_ordenes_activas_y_avance_comparten_un_solo_acceso(): void
    {
        $cerrada = $this->orden->replicate();
        $cerrada->codigo_orden = 'OP-1902-CERRADA';
        $cerrada->numero_correlativo = 2;
        $cerrada->estado = 'CERRADA';
        $cerrada->cerrado_en = now();
        $cerrada->save();

        $this->actingAs($this->jefePlanta)
            ->get(route('ordenes-operacion.index', ['estado' => 'ACTIVAS']))
            ->assertOk()
            ->assertSee($this->orden->codigo_orden)
            ->assertDontSee($cerrada->codigo_orden);

        $this->actingAs($this->jefePlanta)
            ->get(route('modulos.show', 'produccion'))
            ->assertRedirect(route('ordenes-operacion.index', ['estado' => 'ACTIVAS']));
    }

    private function usuarioConRol(string $codigoRol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $codigoRol)->firstOrFail()->id,
            'username' => $username,
            'email' => $username.'@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
