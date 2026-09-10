<?php

namespace Tests\Feature;

use App\Models\AlertaStock;
use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1913AnulacionRequerimientoCompraTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1913');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1913');
        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
    }

    public function test_anula_borrador_con_motivo_y_libera_la_alerta_sin_borrar_la_traza(): void
    {
        [$requerimiento, $alerta] = $this->crearRequerimientoConAlerta(
            'REQ-1913-BORRADOR',
            'BORRADOR',
            'ACTIVA'
        );

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Anular requerimiento');

        $this->actingAs($this->almacen)
            ->patch(route('requerimientos-compra.anular', $requerimiento), [
                'motivo_anulacion' => 'La necesidad fue registrada por duplicado.',
            ])
            ->assertRedirect(route('requerimientos-compra.show', $requerimiento))
            ->assertSessionHasNoErrors();

        $requerimiento->refresh();
        $alerta->refresh();

        $this->assertSame('ANULADA', $requerimiento->estado);
        $this->assertSame('La necesidad fue registrada por duplicado.', $requerimiento->motivo_anulacion);
        $this->assertSame($this->almacen->id, $requerimiento->anulado_por);
        $this->assertNotNull($requerimiento->anulado_en);
        $this->assertSame('PENDIENTE', $requerimiento->estado_abastecimiento);
        $this->assertSame('ACTIVA', $alerta->estado);
        $this->assertDatabaseHas('alerta_stock_requisicion', [
            'alerta_stock_id' => $alerta->id,
            'requisicion_id' => $requerimiento->id,
        ]);
        $this->assertDatabaseHas('historial_requerimientos_compra', [
            'requisicion_id' => $requerimiento->id,
            'estado_anterior' => 'BORRADOR',
            'estado_nuevo' => 'ANULADA',
            'registrado_por' => $this->almacen->id,
        ]);

        $this->actingAs($this->almacen)
            ->post(route('alertas.preparar-requerimiento'), [
                'alcance' => 'SELECCIONADAS',
                'alerta_ids' => [$alerta->id],
            ])
            ->assertRedirect(route('requerimientos-compra.create'))
            ->assertSessionHasNoErrors();
    }

    public function test_reactiva_la_alerta_atendida_y_logistica_puede_anular_una_solicitud_recibida(): void
    {
        [$requerimiento, $alerta] = $this->crearRequerimientoConAlerta(
            'REQ-1913-RECIBIDA',
            'EN_REVISION',
            'ATENDIDA',
            true
        );

        $this->actingAs($this->almacen)
            ->patch(route('requerimientos-compra.anular', $requerimiento), [
                'motivo_anulacion' => 'Almacén intenta cancelar después de la recepción.',
            ])
            ->assertForbidden();

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.anular', $requerimiento), [
                'motivo_anulacion' => 'Logística confirmó que la compra ya no es necesaria.',
            ])
            ->assertRedirect(route('requerimientos-compra.show', $requerimiento))
            ->assertSessionHasNoErrors();

        $alerta->refresh();
        $this->assertSame('ANULADA', $requerimiento->fresh()->estado);
        $this->assertSame('ACTIVA', $alerta->estado);
        $this->assertNull($alerta->atendida_por);
        $this->assertNull($alerta->atendida_en);
    }

    public function test_bloquea_la_anulacion_si_existe_una_orden_de_compra_vigente(): void
    {
        [$requerimiento, $alerta] = $this->crearRequerimientoConAlerta(
            'REQ-1913-CON-OC',
            'ATENDIDA',
            'ATENDIDA',
            true
        );
        $this->crearOrdenCompra($requerimiento, 'OC-1913-VIGENTE');

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('La anulación está bloqueada')
            ->assertDontSee('data-confirm-title="Anular requerimiento"', false);

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.anular', $requerimiento), [
                'motivo_anulacion' => 'Se intenta cancelar un documento que ya tiene compra.',
            ])
            ->assertSessionHasErrors('estado');

        $this->assertSame('ATENDIDA', $requerimiento->fresh()->estado);
        $this->assertSame('ATENDIDA', $alerta->fresh()->estado);
    }

    public function test_al_anular_invalida_cotizacion_y_solicitud_pendientes_para_impedir_una_compra_posterior(): void
    {
        [$requerimiento] = $this->crearRequerimientoConAlerta(
            'REQ-1913-PENDIENTE',
            'COTIZANDO',
            'ATENDIDA',
            true
        );
        [$cotizacion, $solicitud] = $this->crearSolicitudPendiente($requerimiento);

        $this->actingAs($this->logistica)
            ->patch(route('requerimientos-compra.anular', $requerimiento), [
                'motivo_anulacion' => 'El solicitante confirmó que ya no requiere estos productos.',
            ])
            ->assertRedirect(route('requerimientos-compra.show', $requerimiento))
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADA', $cotizacion->fresh()->estado);
        $this->assertSame('ANULADA', $solicitud->fresh()->estado);
        $this->assertSame($this->logistica->id, $cotizacion->fresh()->anulado_por);
        $this->assertSame($this->logistica->id, $solicitud->fresh()->anulado_por);
        $this->assertFalse($solicitud->fresh()->puedeConvertirseEnOrden());
    }

    public function test_exige_un_motivo_suficiente_para_anular(): void
    {
        [$requerimiento] = $this->crearRequerimientoConAlerta(
            'REQ-1913-MOTIVO',
            'BORRADOR',
            'ACTIVA'
        );

        $this->actingAs($this->almacen)
            ->patch(route('requerimientos-compra.anular', $requerimiento), [
                'motivo_anulacion' => 'Error',
            ])
            ->assertSessionHasErrors('motivo_anulacion');

        $this->assertSame('BORRADOR', $requerimiento->fresh()->estado);
        $this->assertNull($requerimiento->fresh()->anulado_en);
    }

    /** @return array{Requisicion, AlertaStock} */
    private function crearRequerimientoConAlerta(
        string $codigo,
        string $estado,
        string $estadoAlerta,
        bool $recibido = false
    ): array {
        $producto = Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => str_replace('REQ-', 'MAT-', $codigo),
            'descripcion' => 'Producto para anulación '.$codigo,
            'estado' => true,
        ]);
        $repisa = Repisa::query()->create([
            'codigo' => str_replace('REQ-', 'R-', $codigo),
            'descripcion' => 'Repisa para '.$codigo,
            'estado' => true,
        ]);
        $inventario = Inventario::query()->create([
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 10,
        ]);
        $requerimiento = Requisicion::query()->create([
            'codigo' => $codigo,
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'descripcion' => 'Reposición controlada para probar la anulación.',
            'prioridad' => 'ALTA',
            'estado' => $estado,
            'estado_abastecimiento' => 'PENDIENTE',
            'solicitado_por' => $this->almacen->id,
            'enviado_por' => $estado !== 'BORRADOR' ? $this->almacen->id : null,
            'enviado_en' => $estado !== 'BORRADOR' ? now() : null,
            'recibido_por' => $recibido ? $this->logistica->id : null,
            'recibido_en' => $recibido ? now() : null,
            'atendido_por' => $estado === 'ATENDIDA' ? $this->logistica->id : null,
            'atendido_en' => $estado === 'ATENDIDA' ? now() : null,
        ]);
        $requerimiento->detalles()->create([
            'producto_id' => $producto->id,
            'cantidad_solicitada' => 5,
            'cantidad_sugerida' => 5,
            'cantidad_atendida' => 0,
            'stock_fisico_snapshot' => 1,
            'reservado_snapshot' => 0,
            'disponible_snapshot' => 1,
            'stock_minimo_snapshot' => 5,
        ]);
        $alerta = AlertaStock::query()->create([
            'inventario_id' => $inventario->id,
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'tipo_alerta' => 'STOCK_MINIMO',
            'nivel' => 'ADVERTENCIA',
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'mensaje' => 'Producto por debajo del mínimo.',
            'estado' => $estadoAlerta,
            'detectada_en' => now(),
            'atendida_por' => $estadoAlerta === 'ATENDIDA' ? $this->almacen->id : null,
            'atendida_en' => $estadoAlerta === 'ATENDIDA' ? now() : null,
        ]);
        $requerimiento->alertasStock()->attach($alerta->id);

        return [$requerimiento, $alerta];
    }

    private function crearOrdenCompra(Requisicion $requerimiento, string $codigo): OrdenCompra
    {
        [$cotizacion, $solicitud, $proveedor] = $this->crearSolicitudPendiente($requerimiento);
        $cotizacion->update(['estado' => 'SELECCIONADA']);
        $solicitud->update([
            'estado' => 'CONVERTIDA',
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);

        return OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => $codigo,
            'origen' => 'REQUERIMIENTO',
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'subtotal' => 10,
            'impuesto' => 1.8,
            'ajuste_redondeo' => 0,
            'total' => 11.8,
            'estado' => 'APROBADA',
            'emitido_por' => $this->logistica->id,
            'aprobado_por' => $this->logistica->id,
            'aprobado_en' => now(),
        ]);
    }

    /** @return array{Cotizacion, SolicitudCompra, Proveedor} */
    private function crearSolicitudPendiente(Requisicion $requerimiento): array
    {
        $proveedor = Proveedor::query()->create([
            'ruc' => '2061913'.str_pad((string) $requerimiento->id, 4, '0', STR_PAD_LEFT),
            'razon_social' => 'Proveedor Anulación 1913 S.A.C.',
            'nombre_comercial' => 'Proveedor 1913',
            'estado' => true,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requerimiento->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-1913-'.$requerimiento->id,
            'numero_documento' => 'DOC-1913-'.$requerimiento->id,
            'fecha_cotizacion' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 10,
            'descuento_global_monto' => 0,
            'impuesto' => 1.8,
            'total_calculado' => 11.8,
            'ajuste_redondeo' => 0,
            'total' => 11.8,
            'estado' => 'SELECCIONADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'codigo' => 'SC-1913-'.$requerimiento->id,
            'fecha_solicitud' => now()->toDateString(),
            'total_lineas' => 11.8,
            'ajuste_redondeo' => 0,
            'total_seleccionado' => 11.8,
            'estado' => 'PENDIENTE',
            'solicitado_por' => $this->logistica->id,
        ]);

        return [$cotizacion, $solicitud, $proveedor];
    }

    private function crearUsuario(string $rol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $rol)->firstOrFail()->id,
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
