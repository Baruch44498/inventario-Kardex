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

class Fase1919AccionesDirectasRequerimientosTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private User $logistica;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->crearUsuario('ALMACEN', 'almacen_1919');
        $this->logistica = $this->crearUsuario('COMERCIAL_LOGISTICA', 'logistica_1919');
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-1919-A',
            'descripcion' => 'Material para acciones directas',
            'estado' => true,
        ]);
    }

    public function test_logistica_recibe_la_accion_correcta_para_cada_etapa(): void
    {
        $enviado = $this->crearRequerimiento('REQ-1919-ENVIADO', 'ENVIADA');
        $revision = $this->crearRequerimiento('REQ-1919-REVISION', 'EN_REVISION');
        $sinOferta = $this->crearRequerimiento('REQ-1919-SIN-OFERTA', 'COTIZANDO');
        $conOferta = $this->crearRequerimiento('REQ-1919-CON-OFERTA', 'COTIZANDO');
        $this->crearOferta($conOferta);

        $this->actingAs($this->logistica)
            ->get(route('requerimientos-compra.index'))
            ->assertOk()
            ->assertSee('Tomar requerimiento')
            ->assertSee(route('requerimientos-compra.show', $enviado).'#gestion-logistica', false)
            ->assertSee('Iniciar cotización')
            ->assertSee(route('requerimientos-compra.show', $revision).'#gestion-logistica', false)
            ->assertSee('Registrar cotización')
            ->assertSee(route('cotizaciones-proveedor.create', [
                'requisicion_id' => $sinOferta->id,
            ]))
            ->assertSee('Comparar ofertas')
            ->assertSee(route('requerimientos-compra.comparativo', $conOferta));
    }

    public function test_almacen_puede_continuar_borradores_desde_el_listado(): void
    {
        $borrador = $this->crearRequerimiento('REQ-1919-BORRADOR', 'BORRADOR');

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.index', ['estado' => 'BORRADOR']))
            ->assertOk()
            ->assertSee('Continuar borrador')
            ->assertSee(route('requerimientos-compra.edit', $borrador));
    }

    public function test_almacen_accede_a_las_recepciones_pendientes_desde_el_listado(): void
    {
        $atendido = $this->crearRequerimiento('REQ-1919-ATENDIDO', 'ATENDIDA');

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.index', ['estado' => 'ATENDIDA']))
            ->assertOk()
            ->assertSee('Ver órdenes pendientes')
            ->assertSee(route('ordenes-compra.index', [
                'q' => $atendido->codigo,
                'situacion' => 'PENDIENTE',
            ]));
    }

    public function test_almacen_no_recibe_acciones_reservadas_a_logistica(): void
    {
        $this->crearRequerimiento('REQ-1919-SOLO-LECTURA', 'COTIZANDO');

        $this->actingAs($this->almacen)
            ->get(route('requerimientos-compra.index', ['estado' => 'COTIZANDO']))
            ->assertOk()
            ->assertDontSee('Registrar cotización')
            ->assertDontSee('Comparar ofertas')
            ->assertSee('Ver requerimiento');
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

    private function crearOferta(Requisicion $requerimiento): void
    {
        $proveedor = Proveedor::query()->create([
            'ruc' => '20619190001',
            'razon_social' => 'Proveedor Acciones 1919 S.A.C.',
            'estado' => true,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requerimiento->id,
            'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-1919-001',
            'numero_documento' => 'DOC-1919-001',
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
