<?php

namespace Tests\Feature;

use App\Models\AlertaStock;
use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Compras\BandejaOperativaComprasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1918BandejaOperativaPendientesTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1918');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1918');
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1918-A',
            'descripcion' => 'Material para bandeja operativa',
            'estado' => true,
        ]);
    }

    public function test_almacen_ve_alertas_sin_requerimiento_y_borradores_por_enviar(): void
    {
        $borrador = $this->crearRequerimiento('REQ-1918-BORRADOR', 'BORRADOR');
        $alertaLibre = $this->crearAlerta('R-1918-LIBRE');
        $alertaVinculada = $this->crearAlerta('R-1918-VINCULADA');
        $enviado = $this->crearRequerimiento('REQ-1918-ENVIADO', 'ENVIADA');
        $enviado->alertasStock()->attach($alertaVinculada->id);

        $bandeja = app(BandejaOperativaComprasService::class)->construir($this->almacen);
        $items = collect($bandeja['items'])->keyBy('titulo');

        $this->assertTrue($bandeja['visible']);
        $this->assertSame('Almacén', $bandeja['perfil']);
        $this->assertSame(1, $items['Alertas sin requerimiento']['cantidad']);
        $this->assertSame(1, $items['Borradores por enviar']['cantidad']);

        $this->actingAs($this->almacen)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bandeja operativa')
            ->assertSee('Alertas sin requerimiento')
            ->assertSee('Borradores por enviar')
            ->assertSee(route('requerimientos-compra.index', ['estado' => 'BORRADOR']))
            ->assertSee(route('alertas.index'));

        $this->assertNotNull($borrador->id);
        $this->assertNotNull($alertaLibre->id);
    }

    public function test_logistica_distingue_requerimientos_por_etapa_y_ofertas_disponibles(): void
    {
        $this->crearRequerimiento('REQ-1918-ENVIADA', 'ENVIADA');
        $this->crearRequerimiento('REQ-1918-REVISION', 'EN_REVISION');
        $this->crearRequerimiento('REQ-1918-SIN-OFERTA', 'COTIZANDO');
        $conOferta = $this->crearRequerimiento('REQ-1918-CON-OFERTA', 'COTIZANDO');
        $this->crearOferta($conOferta);

        $bandeja = app(BandejaOperativaComprasService::class)->construir($this->logistica);
        $items = collect($bandeja['items'])->keyBy('titulo');

        $this->assertTrue($bandeja['visible']);
        $this->assertSame('Logística', $bandeja['perfil']);
        $this->assertSame(1, $items['Requerimientos por recibir']['cantidad']);
        $this->assertSame(1, $items['Requerimientos en revisión']['cantidad']);
        $this->assertSame(1, $items['Pendientes de cotizar']['cantidad']);
        $this->assertSame(1, $items['Listos para comparar']['cantidad']);

        $this->actingAs($this->logistica)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bandeja operativa')
            ->assertSee('Requerimientos por recibir')
            ->assertSee('Requerimientos en revisión')
            ->assertSee('Pendientes de cotizar')
            ->assertSee('Listos para comparar');
    }

    public function test_un_perfil_sin_responsabilidad_de_compras_no_recibe_la_bandeja(): void
    {
        $jefePlanta = $this->crearUsuario('JEFE_PLANTA', 'planta_1918');
        $this->crearRequerimiento('REQ-1918-OCULTO', 'ENVIADA');

        $bandeja = app(BandejaOperativaComprasService::class)->construir($jefePlanta);

        $this->assertFalse($bandeja['visible']);
        $this->assertSame([], $bandeja['items']);

        $this->actingAs($jefePlanta)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Bandeja operativa');
    }

    public function test_almacen_recibe_confirmacion_cuando_no_hay_pendientes(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bandeja operativa')
            ->assertSee('Sin pendientes operativos');
    }

    private function crearRequerimiento(string $codigo, string $estado): Requisicion
    {
        $requerimiento = Requisicion::query()->create([
            'codigo' => $codigo,
            'fecha_solicitud' => today(),
            'origen' => 'REPOSICION',
            'prioridad' => 'ALTA',
            'estado' => $estado,
            'estado_abastecimiento' => 'PENDIENTE',
            'solicitado_por' => $this->almacen->id,
            'enviado_por' => $estado === 'BORRADOR' ? null : $this->almacen->id,
            'enviado_en' => $estado === 'BORRADOR' ? null : now(),
            'recibido_por' => in_array($estado, ['EN_REVISION', 'COTIZANDO'], true)
                ? $this->logistica->id
                : null,
            'recibido_en' => in_array($estado, ['EN_REVISION', 'COTIZANDO'], true)
                ? now()
                : null,
        ]);
        $requerimiento->detalles()->create([
            'producto_id' => $this->producto->id,
            'cantidad_solicitada' => 5,
            'cantidad_sugerida' => 5,
            'cantidad_atendida' => 0,
            'stock_fisico_snapshot' => 0,
            'reservado_snapshot' => 0,
            'disponible_snapshot' => 0,
            'stock_minimo_snapshot' => 5,
        ]);

        return $requerimiento;
    }

    private function crearOferta(Requisicion $requerimiento): void
    {
        $proveedor = Proveedor::query()->create([
            'ruc' => '20619180001',
            'razon_social' => 'Proveedor Bandeja 1918 S.A.C.',
            'estado' => true,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requerimiento->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-1918-001',
            'numero_documento' => 'DOC-1918-001',
            'fecha_cotizacion' => today(),
            'moneda' => 'PEN',
            'tipo_cambio' => 1,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'subtotal' => 50,
            'descuento_global_monto' => 0,
            'impuesto' => 9,
            'total_calculado' => 59,
            'ajuste_redondeo' => 0,
            'total' => 59,
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $linea = $requerimiento->detalles()->firstOrFail();
        $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $linea->id,
            'tipo_vinculacion' => 'SOLICITADO',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $linea->producto_id,
            'cantidad' => 5,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => 50,
            'impuesto' => 9,
            'total' => 59,
        ]);
    }

    private function crearAlerta(string $codigoRepisa): AlertaStock
    {
        $repisa = Repisa::query()->create([
            'codigo' => $codigoRepisa,
            'descripcion' => 'Repisa para bandeja',
            'estado' => true,
        ]);
        $inventario = Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'stock_maximo' => 20,
            'costo_promedio_soles' => 10,
        ]);

        return AlertaStock::query()->create([
            'inventario_id' => $inventario->id,
            'producto_id' => $this->producto->id,
            'repisa_id' => $repisa->id,
            'tipo_alerta' => 'STOCK_MINIMO',
            'nivel' => 'CRITICA',
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'mensaje' => 'Producto por debajo del mínimo.',
            'estado' => 'ACTIVA',
            'detectada_en' => now(),
        ]);
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
