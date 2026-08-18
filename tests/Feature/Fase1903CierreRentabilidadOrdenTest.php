<?php

namespace Tests\Feature;

use App\Models\CostoDirectoOrden;
use App\Models\CotizacionCliente;
use App\Models\NotaSalida;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Cliente;
use App\Services\Ordenes\ResumenEjecucionOrdenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1903CierreRentabilidadOrdenTest extends TestCase
{
    use RefreshDatabase;

    private User $jefePlanta;
    private User $logistica;
    private OrdenOperacion $orden;
    private Producto $herramienta;
    private Repisa $repisa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jefePlanta = $this->usuarioConRol('JEFE_PLANTA', 'planta_1903');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_1903');
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619030001',
            'ruc' => '20619030001',
            'razon_social' => 'Cliente Cierre Fase 19 SAC',
            'estado' => true,
        ]);
        $tipo = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->repisa = Repisa::query()->create([
            'codigo' => 'R-1903',
            'descripcion' => 'Repisa fase 19.0.3',
            'estado' => true,
        ]);
        $this->herramienta = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'HER-1903',
            'descripcion' => 'Herramienta temporal fase 19.0.3',
            'estado' => true,
        ]);

        $this->orden = OrdenOperacion::query()->create([
            'tipo_orden_id' => $tipo->id,
            'cliente_id' => $cliente->id,
            'codigo_orden' => 'OP-1903-00001',
            'numero_correlativo' => 1,
            'anio' => (int) now()->format('Y'),
            'fecha_apertura' => now()->toDateString(),
            'descripcion' => 'Orden para cierre y rentabilidad',
            'estado' => 'EN_PROCESO',
            'creado_por' => $this->logistica->id,
        ]);

        CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $cliente->id,
            'tipo_orden_id' => $tipo->id,
            'descripcion_trabajo' => 'Fabricación para cierre',
            'codigo_base' => 'CC-1903',
            'version' => 1,
            'codigo' => 'CC-1903-vrs1',
            'cliente_nombre' => $cliente->razon_social,
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'subtotal' => 1000,
            'impuesto' => 180,
            'total' => 1180,
            'estado' => 'CONVERTIDA_EN_ORDEN',
            'cotizado_por' => $this->logistica->id,
            'orden_operacion_id' => $this->orden->id,
        ]);

        CostoDirectoOrden::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'tipo' => 'MANO_OBRA',
            'fecha_costo' => now()->toDateString(),
            'descripcion' => 'Mano de obra acumulada',
            'cantidad' => 1,
            'unidad' => 'GLOBAL',
            'costo_unitario_soles' => 600,
            'total_soles' => 600,
            'estado' => 'VIGENTE',
            'registrado_por' => $this->logistica->id,
            'registrado_en' => now(),
        ]);
    }

    public function test_calcula_proyeccion_sin_igv_antes_del_cierre(): void
    {
        $resumen = app(ResumenEjecucionOrdenService::class)
            ->construir($this->orden->fresh(), true);

        $rentabilidad = $resumen['costos']['rentabilidad'];

        $this->assertSame(1000.0, (float) $rentabilidad['ingreso_neto_soles']);
        $this->assertSame(600.0, (float) $resumen['costos']['total_real']);
        $this->assertSame(400.0, (float) $rentabilidad['utilidad_real_soles']);
        $this->assertSame(40.0, (float) $rentabilidad['margen_real_porcentaje']);
        $this->assertSame('UTILIDAD', $rentabilidad['resultado']);
        $this->assertFalse($rentabilidad['cierre_congelado']);
    }

    public function test_no_cierra_si_el_avance_no_llego_al_cien_por_ciento(): void
    {
        $this->registrarAvance(80);

        $this->actingAs($this->jefePlanta)
            ->patch(route('ordenes-operacion.cerrar', $this->orden), [
                'observacion_cierre' => 'Trabajo aún incompleto.',
            ])
            ->assertSessionHasErrors('cierre');

        $this->assertSame('EN_PROCESO', $this->orden->fresh()->estado);
    }

    public function test_cierre_congela_resultado_y_respeta_visibilidad_por_perfil(): void
    {
        $this->registrarAvance(100);

        $this->actingAs($this->jefePlanta)
            ->patch(route('ordenes-operacion.cerrar', $this->orden), [
                'observacion_cierre' => 'Pruebas finales completadas y trabajo entregado.',
            ])
            ->assertRedirect();

        $orden = $this->orden->fresh();
        $this->assertSame('CERRADA', $orden->estado);
        $this->assertSame($this->jefePlanta->id, $orden->cerrado_por);
        $this->assertSame(1000.0, (float) $orden->ingreso_neto_cierre_soles);
        $this->assertSame(600.0, (float) $orden->costo_real_cierre_soles);
        $this->assertSame(400.0, (float) $orden->utilidad_real_cierre_soles);
        $this->assertSame(40.0, (float) $orden->margen_real_cierre_porcentaje);

        $this->actingAs($this->jefePlanta)
            ->get(route('ordenes-operacion.show', $orden))
            ->assertOk()
            ->assertDontSee('Utilidad real');

        $this->actingAs($this->logistica)
            ->get(route('ordenes-operacion.show', $orden))
            ->assertOk()
            ->assertSee('Utilidad real')
            ->assertSee('Los importes corresponden al cierre congelado.');
    }

    public function test_no_cierra_con_herramientas_pendientes_de_devolucion(): void
    {
        $this->registrarAvance(100);
        $salida = NotaSalida::query()->create([
            'orden_operacion_id' => $this->orden->id,
            'motivo_salida' => 'ORDEN_OPERACION',
            'codigo' => 'NS-1903-1',
            'fecha_salida' => now()->toDateString(),
            'estado' => 'CONFIRMADA',
            'registrado_por' => $this->logistica->id,
            'confirmado_por' => $this->logistica->id,
            'confirmado_en' => now(),
        ]);
        $salida->detalles()->create([
            'producto_id' => $this->herramienta->id,
            'repisa_id' => $this->repisa->id,
            'cantidad' => 1,
            'tratamiento' => 'USO_TEMPORAL',
            'costo_unitario_promedio' => 20,
            'subtotal' => 20,
        ]);

        $this->actingAs($this->jefePlanta)
            ->patch(route('ordenes-operacion.cerrar', $this->orden))
            ->assertSessionHasErrors('cierre');

        $this->assertSame('EN_PROCESO', $this->orden->fresh()->estado);
    }

    private function registrarAvance(float $porcentaje): void
    {
        $this->orden->avances()->create([
            'porcentaje' => $porcentaje,
            'detalle' => 'Avance para validar el cierre.',
            'registrado_por' => $this->jefePlanta->id,
            'registrado_en' => now(),
        ]);
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
