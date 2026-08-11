<?php

namespace Tests\Feature;

use App\Models\HistorialRequerimientoCompra;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1711AtencionRequerimientoLogisticaHistorialTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_1711');
        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_1711');

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $repisa = Repisa::query()->create([
            'codigo' => 'R-1711',
            'descripcion' => 'Repisa fase 17.1.1',
            'estado' => true,
        ]);
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1711-A',
            'descripcion' => 'Material para seguimiento logístico',
            'estado' => true,
        ]);
        Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 2,
            'stock_minimo' => 5,
            'stock_maximo' => 30,
            'costo_promedio_soles' => 10,
        ]);
    }

    public function test_crear_borrador_registra_primer_movimiento_del_historial(): void
    {
        $requerimiento = $this->crearBorrador();

        $this->assertDatabaseHas('historial_requerimientos_compra', [
            'requisicion_id' => $requerimiento->id,
            'estado_anterior' => null,
            'estado_nuevo' => 'BORRADOR',
            'registrado_por' => $this->almacen->id,
        ]);
    }

    public function test_envio_y_recepcion_registran_actor_fecha_responsable_y_nota(): void
    {
        $requerimiento = $this->crearEnviado();

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.recibir', $requerimiento), [
                'observacion_seguimiento' => 'Revisaré primero disponibilidad y plazo de entrega.',
            ])
            ->assertRedirect();

        $requerimiento->refresh();
        $this->assertSame('EN_REVISION', $requerimiento->estado);
        $this->assertSame($this->logistica->id, $requerimiento->recibido_por);
        $this->assertNotNull($requerimiento->recibido_en);

        $this->assertDatabaseHas('historial_requerimientos_compra', [
            'requisicion_id' => $requerimiento->id,
            'estado_anterior' => 'ENVIADA',
            'estado_nuevo' => 'EN_REVISION',
            'observacion' => 'Revisaré primero disponibilidad y plazo de entrega.',
            'registrado_por' => $this->logistica->id,
        ]);
    }

    public function test_flujo_logistico_respeta_enviada_revision_cotizando_atendida(): void
    {
        $requerimiento = $this->crearEnviado();

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.recibir', $requerimiento))
            ->assertRedirect();
        $this->assertSame('EN_REVISION', $requerimiento->fresh()->estado);

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.cotizando', $requerimiento), [
                'observacion_seguimiento' => 'Se solicitarán precios a tres proveedores.',
            ])
            ->assertRedirect();
        $this->assertSame('COTIZANDO', $requerimiento->fresh()->estado);

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.atender', $requerimiento), [
                'observacion_seguimiento' => 'Atención logística concluida.',
            ])
            ->assertRedirect();

        $requerimiento->refresh();
        $this->assertSame('ATENDIDA', $requerimiento->estado);
        $this->assertSame($this->logistica->id, $requerimiento->atendido_por);
        $this->assertNotNull($requerimiento->atendido_en);

        $historial = HistorialRequerimientoCompra::query()
            ->where('requisicion_id', $requerimiento->id)
            ->orderBy('id')
            ->pluck('estado_nuevo')
            ->all();

        $this->assertSame(['BORRADOR', 'ENVIADA', 'EN_REVISION', 'COTIZANDO', 'ATENDIDA'], $historial);
    }

    public function test_no_se_puede_marcar_atendido_sin_pasar_por_cotizando(): void
    {
        $requerimiento = $this->crearEnviado();

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.recibir', $requerimiento))
            ->assertRedirect();

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.atender', $requerimiento))
            ->assertSessionHasErrors('estado');

        $this->assertSame('EN_REVISION', $requerimiento->fresh()->estado);
    }

    public function test_almacen_consulta_avance_pero_no_puede_editar_despues_de_enviar(): void
    {
        $requerimiento = $this->crearEnviado();

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.recibir', $requerimiento))
            ->assertRedirect();

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Atención de Logística')
            ->assertSee($this->logistica->nombreVisible())
            ->assertSee('Historial del requerimiento');

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.edit', $requerimiento))
            ->assertForbidden();
    }

    public function test_detalle_muestra_historial_notas_y_responsable_de_logistica(): void
    {
        $requerimiento = $this->crearEnviado();

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.recibir', $requerimiento), [
                'observacion_seguimiento' => 'Priorizar proveedor con entrega inmediata.',
            ])
            ->assertRedirect();

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Responsable de Logística')
            ->assertSee($this->logistica->nombreVisible())
            ->assertSee('Priorizar proveedor con entrega inmediata.')
            ->assertSee('Enviada → En Revision');
    }

    private function crearBorrador(): Requisicion
    {
        $this->actingAs($this->almacen)
            ->post(route('requerimientos-compra.store'), [
                'fecha_solicitud' => now()->toDateString(),
                'origen' => 'REPOSICION',
                'prioridad' => 'ALTA',
                'descripcion' => 'Reposición para fase 17.1.1.',
                'detalles' => [[
                    'producto_id' => $this->producto->id,
                    'cantidad_solicitada' => 10,
                    'observacion' => 'Reponer stock.',
                ]],
            ])
            ->assertRedirect();

        return Requisicion::query()->latest('id')->firstOrFail();
    }

    private function crearEnviado(): Requisicion
    {
        $requerimiento = $this->crearBorrador();

        $this->actingAs($this->almacen)
            ->patch(route('requerimientos-compra.enviar', $requerimiento))
            ->assertRedirect();

        return $requerimiento->fresh();
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
