<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\MaterialRequeridoOrden;
use App\Models\OrdenOperacion;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase170743ComposicionInternaCotizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private User $almacen;
    private Cliente $cliente;
    private Producto $producto;
    private TipoOrden $tipoProduccion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = $this->usuarioConRol('COMERCIAL_LOGISTICA', 'logistica_170743');
        $this->almacen = $this->usuarioConRol('ALMACEN', 'almacen_170743');

        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );

        $this->cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20617074301',
            'ruc' => '20617074301',
            'razon_social' => 'Cliente composición interna SAC',
            'estado' => true,
        ]);

        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );

        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'MAT-CISTERNA-01',
            'descripcion' => 'Válvula interna para fabricación de cisterna',
            'estado' => true,
        ]);

        $this->tipoProduccion = TipoOrden::query()->updateOrCreate(
            ['codigo' => 'OP'],
            ['nombre' => 'Producción', 'estado' => true]
        );
    }

    public function test_formulario_directo_prepara_detalle_diferenciado_por_tipo_de_orden(): void
    {
        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.create'))
            ->assertOk()
            ->assertSee('data-commercial-detail-title', false)
            ->assertSee('data-commercial-detail-note-title', false)
            ->assertSee('Selecciona OM, OS u OP para definir cómo se mostrará este detalle al cliente.');

        $javascript = file_get_contents(public_path('js/proforma-documentos.js'));

        $this->assertStringContainsString('Composición interna y valorización', $javascript);
        $this->assertStringContainsString('Materiales y repuestos cotizados', $javascript);
        $this->assertStringContainsString('Detalle visible para el cliente', $javascript);
    }

    public function test_logistica_ve_resumen_comercial_y_desglose_interno_separados(): void
    {
        $cotizacion = $this->crearCotizacionDirecta();

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('Resumen que corresponde al cliente')
            ->assertSee('Fabricación de cisterna de 10,000 L')
            ->assertSee('Composición interna valorizada')
            ->assertSee('Uso interno · No incluir en documento al cliente')
            ->assertSee('MAT-CISTERNA-01');
    }

    public function test_usuario_solo_consulta_no_ve_desglose_interno_de_cotizacion_directa(): void
    {
        $cotizacion = $this->crearCotizacionDirecta();

        $this->actingAs($this->almacen)
            ->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()
            ->assertSee('Resumen que corresponde al cliente')
            ->assertSee('Fabricación de cisterna de 10,000 L')
            ->assertDontSee('Composición interna valorizada')
            ->assertDontSee('MAT-CISTERNA-01');
    }

    public function test_composicion_interna_pasa_a_materiales_iniciales_al_convertir_en_op(): void
    {
        $cotizacion = $this->crearCotizacionDirecta();

        $this->actingAs($this->logistica)
            ->patch(route('cotizaciones-cliente.cerrar', $cotizacion))
            ->assertRedirect();

        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.convertir-orden', $cotizacion), [
                'fecha_apertura' => now()->toDateString(),
            ])
            ->assertRedirect();

        $orden = OrdenOperacion::query()->latest('id')->firstOrFail();
        $material = MaterialRequeridoOrden::query()
            ->where('orden_operacion_id', $orden->id)
            ->where('producto_id', $this->producto->id)
            ->firstOrFail();

        $this->assertEquals(4, (float) $material->cantidad_requerida);
        $this->assertDatabaseHas('historial_materiales_requeridos_orden', [
            'material_requerido_orden_id' => $material->id,
            'tipo_movimiento' => 'INICIAL',
            'cantidad_nueva' => 4,
        ]);
    }

    private function crearCotizacionDirecta(): CotizacionCliente
    {
        $this->actingAs($this->logistica)
            ->post(route('cotizaciones-cliente.store'), [
                'cliente_id' => $this->cliente->id,
                'tipo_orden_id' => $this->tipoProduccion->id,
                'cliente_direccion_id' => null,
                'vehiculo_id' => null,
                'descripcion_trabajo' => 'Fabricación de cisterna de 10,000 L',
                'fecha_emision' => now()->toDateString(),
                'fecha_validez' => now()->addDays(15)->toDateString(),
                'moneda' => 'PEN',
                'tipo_cambio' => null,
                'condiciones_pago' => '50 % adelanto',
                'condiciones_entrega' => 'Según coordinación',
                'observacion' => 'Composición interna fase 17.0.7.4',
                'detalles' => [[
                    'producto_id' => $this->producto->id,
                    'cantidad' => 4,
                    'precio_unitario' => 250,
                    'igv_modo' => 'AGREGAR',
                ]],
            ])
            ->assertRedirect();

        return CotizacionCliente::query()->latest('id')->firstOrFail();
    }

    private function usuarioConRol(string $codigoRol, string $username): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $codigoRol)->firstOrFail()->id,
            'username' => $username,
            'email' => $username . '@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
