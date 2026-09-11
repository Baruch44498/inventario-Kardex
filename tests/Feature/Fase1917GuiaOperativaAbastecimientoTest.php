<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1917GuiaOperativaAbastecimientoTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1917');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1917');
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1917-A',
            'descripcion' => 'Material para guía operativa',
            'estado' => true,
        ]);
    }

    public function test_guia_indica_quien_debe_tomar_un_requerimiento_enviado(): void
    {
        $requerimiento = $this->crearRequerimiento('ENVIADA');

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Siguiente acción: Logística debe tomar el requerimiento')
            ->assertSee('Ir a tomar requerimiento')
            ->assertSee('#gestion-logistica', false);
    }

    public function test_guia_distingue_registrar_cotizacion_de_comparar_ofertas(): void
    {
        $requerimiento = $this->crearRequerimiento('COTIZANDO');

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Siguiente acción: Registrar cotizaciones de proveedores')
            ->assertSee('Registrar cotización');

        $proveedor = Proveedor::query()->create([
            'ruc' => '20619170001',
            'razon_social' => 'Proveedor Guía 1917 S.A.C.',
            'estado' => true,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requerimiento->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-1917-001',
            'numero_documento' => 'DOC-1917-001',
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
            'estado' => 'REGISTRADA',
            'registrado_por' => $this->logistica->id,
        ]);
        $linea = $requerimiento->detalles()->firstOrFail();
        $cotizacion->detalles()->create([
            'requisicion_detalle_id' => $linea->id,
            'tipo_vinculacion' => 'SOLICITADO',
            'vinculacion_origen' => 'MANUAL',
            'producto_id' => $this->producto->id,
            'cantidad' => 1,
            'precio_unitario' => 11.8,
            'descuento_porcentaje' => 0,
            'descuento_modo' => 'SIN_DESCUENTO',
            'igv_modo' => 'INCLUIDO',
            'igv_porcentaje' => 18,
            'subtotal' => 10,
            'impuesto' => 1.8,
            'total' => 11.8,
        ]);

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Siguiente acción: Comparar ofertas y elegir proveedores')
            ->assertSee(route('requerimientos-compra.comparativo', $requerimiento));
    }

    public function test_almacen_recibe_acceso_directo_a_ordenes_pendientes(): void
    {
        $requerimiento = $this->crearRequerimiento('ATENDIDA');

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.show', $requerimiento))
            ->assertOk()
            ->assertSee('Siguiente acción: Registrar las recepciones pendientes')
            ->assertSee('Ver órdenes pendientes')
            ->assertSee(route('ordenes-compra.index', [
                'q' => $requerimiento->codigo,
                'situacion' => 'PENDIENTE',
            ]));
    }

    public function test_busqueda_de_ordenes_admite_el_codigo_del_requerimiento(): void
    {
        $controlador = file_get_contents(app_path('Http/Controllers/OrdenCompraController.php'));

        $this->assertStringContainsString(
            "orWhereHas('solicitudCompra.cotizacion.requisicion'",
            $controlador
        );
    }

    private function crearRequerimiento(string $estado): Requisicion
    {
        $requerimiento = Requisicion::query()->create([
            'codigo' => 'REQ-1917-'.$estado,
            'fecha_solicitud' => now()->toDateString(),
            'origen' => 'REPOSICION',
            'prioridad' => 'ALTA',
            'estado' => $estado,
            'estado_abastecimiento' => 'PENDIENTE',
            'solicitado_por' => $this->almacen->id,
            'enviado_por' => $estado === 'BORRADOR' ? null : $this->almacen->id,
            'enviado_en' => $estado === 'BORRADOR' ? null : now(),
            'recibido_por' => in_array($estado, ['EN_REVISION', 'COTIZANDO', 'ATENDIDA'], true)
                ? $this->logistica->id
                : null,
            'recibido_en' => in_array($estado, ['EN_REVISION', 'COTIZANDO', 'ATENDIDA'], true)
                ? now()
                : null,
            'atendido_por' => $estado === 'ATENDIDA' ? $this->logistica->id : null,
            'atendido_en' => $estado === 'ATENDIDA' ? now() : null,
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
