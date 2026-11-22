<?php

namespace Tests\Feature;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistroAlmacenTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private Producto $producto;
    private Repisa $origen;
    private Repisa $destino;
    private Inventario $existencia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = $this->usuario('ALMACEN');
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true],
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => '11375',
            'descripcion' => 'Punto de engrase de prueba',
            'estado' => true,
        ]);
        $this->origen = Repisa::query()->create(['codigo' => 'AA011', 'estado' => true]);
        $this->destino = Repisa::query()->create(['codigo' => 'AA025', 'estado' => true]);
        $this->existencia = Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->origen->id,
            'stock_actual' => 22,
            'costo_promedio_soles' => 5,
        ]);
    }

    public function test_traslada_parcialmente_entre_dos_repisas_del_mismo_producto_y_no_duplica_al_reintentar(): void
    {
        $otra = Inventario::query()->create([
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->destino->id,
            'stock_actual' => 22,
            'costo_promedio_soles' => 9,
        ]);
        $solicitud = $this->traslado();
        $this->actingAs($this->almacen)
            ->post(route('registro-almacen.operaciones.store', $this->producto), $solicitud)
            ->assertSessionHasNoErrors()
            ->assertStatus(303);
        $this->post(route('registro-almacen.operaciones.store', $this->producto), $solicitud)
            ->assertSessionHasNoErrors()
            ->assertStatus(303);

        $this->assertSame('12.000', $this->existencia->fresh()->stock_actual);
        $this->assertSame('32.000', $otra->fresh()->stock_actual);
        $this->assertSame('7.7500', $otra->fresh()->costo_promedio_soles);
        $this->assertDatabaseCount('operaciones_almacen', 1);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        foreach (['SALIDA' => $this->origen->id, 'ENTRADA' => $this->destino->id] as $tipo => $repisa) {
            $this->assertDatabaseHas('movimientos_inventario', [
                'tipo_movimiento' => $tipo,
                'repisa_id' => $repisa,
                'cantidad' => 10,
                'costo_unitario' => 5,
                'motivo' => 'TRASLADO_INTERNO',
                'origen_tipo' => 'REGISTRO_ALMACEN',
                'origen_id' => 1,
            ]);
        }
    }

    public function test_traslado_completo_a_repisa_vacia_conserva_el_costo_y_el_historial_del_origen(): void
    {
        $this->actingAs($this->almacen)
            ->post(route('registro-almacen.operaciones.store', $this->producto), $this->traslado(['cantidad' => 22]))
            ->assertSessionHasNoErrors()->assertStatus(303);

        $this->assertSame('0.000', $this->existencia->fresh()->stock_actual);
        $this->assertDatabaseHas('inventarios', [
            'producto_id' => $this->producto->id,
            'repisa_id' => $this->destino->id,
            'stock_actual' => 22,
            'costo_promedio_soles' => 5,
        ]);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_rechaza_traslado_sin_stock_con_fracciones_o_hacia_la_misma_repisa(): void
    {
        $this->actingAs($this->almacen);
        $ruta = route('registro-almacen.operaciones.store', $this->producto);
        $this->post($ruta, $this->traslado(['cantidad' => 23]))->assertSessionHasErrors('cantidad');
        $this->post($ruta, $this->traslado(['cantidad' => '1.500']))->assertSessionHasErrors('cantidad');
        $this->post($ruta, $this->traslado(['destino_id' => $this->origen->id]))->assertSessionHasErrors('origen_id');
        $this->assertSame('22.000', $this->existencia->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_protege_conteos_abiertos_y_exige_confirmar_destino_compartido(): void
    {
        $otro = Producto::query()->create([
            'unidad_medida_id' => $this->producto->unidad_medida_id,
            'codigo' => 'OTRO-01',
            'descripcion' => 'Otro material',
            'estado' => true,
        ]);
        Inventario::query()->create([
            'producto_id' => $otro->id,
            'repisa_id' => $this->destino->id,
            'stock_actual' => 3,
        ]);
        $this->actingAs($this->almacen);
        $ruta = route('registro-almacen.operaciones.store', $this->producto);
        $this->post($ruta, $this->traslado())->assertSessionHasErrors('confirmar_compartida');

        $this->post(route('inventarios-periodicos.store'), ['repisa_id' => $this->origen->id])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post($ruta, $this->traslado(['confirmar_compartida' => '1']))->assertSessionHasErrors('destino_id');
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_saldo_inicial_tiene_movimiento_y_no_sobrescribe_un_saldo_existente(): void
    {
        $ruta = route('registro-almacen.operaciones.store', $this->producto);
        $datos = [
            'solicitud' => (string) Str::uuid(),
            'tipo' => 'SALDO_INICIAL',
            'destino_id' => $this->destino->id,
            'cantidad' => 5,
            'observacion' => 'Unidades verificadas en repisa',
        ];

        $this->actingAs($this->almacen)->post($ruta, $datos)
            ->assertSessionHasNoErrors()->assertStatus(303);
        $this->assertDatabaseHas('movimientos_inventario', [
            'repisa_id' => $this->destino->id,
            'tipo_movimiento' => 'ENTRADA',
            'motivo' => 'SALDO_INICIAL',
            'stock_posterior' => 5,
            'costo_promedio_nuevo' => 0,
        ]);

        $datos['solicitud'] = (string) Str::uuid();
        $this->post($ruta, $datos)->assertSessionHasErrors('destino_id');
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_busqueda_por_repisa_muestra_formularios_y_el_precio_requiere_igv_explicito(): void
    {
        $this->actingAs($this->almacen)
            ->get(route('registro-almacen.index', ['q' => 'AA011', 'producto' => $this->producto->id]))
            ->assertOk()
            ->assertSee('Punto de engrase de prueba')
            ->assertSee('Mover stock')
            ->assertSee('Entrega a personal')
            ->assertSee('Selecciona según el precio de origen');

        $ruta = route('registro-almacen.precios.store', $this->producto);
        $datos = ['precio_soles' => 118, 'fecha_referencia' => today()->toDateString(), 'fuente' => 'Excel almacén'];
        $this->post($ruta, $datos)->assertSessionHasErrors('igv_modo');
        $this->assertDatabaseCount('precios_referencia_almacen', 0);
        $this->post($ruta, $datos + ['igv_modo' => 'INCLUIDO'])
            ->assertSessionHasNoErrors()->assertStatus(303);
        $this->assertDatabaseHas('precios_referencia_almacen', [
            'producto_id' => $this->producto->id, 'precio_soles' => 118, 'igv_modo' => 'INCLUIDO',
        ]);
        $this->assertSame('5.0000', $this->existencia->fresh()->costo_promedio_soles);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_usuarios_sin_permiso_no_registran_operaciones_ni_precios(): void
    {
        $this->actingAs($this->usuario('COMERCIAL_LOGISTICA'));
        $this->get(route('registro-almacen.index'))->assertForbidden();
        $this->post(route('registro-almacen.operaciones.store', $this->producto), $this->traslado())->assertForbidden();
        $this->post(route('registro-almacen.precios.store', $this->producto))->assertForbidden();
    }

    private function traslado(array $cambios = []): array
    {
        return $cambios + [
            'solicitud' => (string) Str::uuid(),
            'tipo' => 'TRASLADO',
            'origen_id' => $this->origen->id,
            'destino_id' => $this->destino->id,
            'cantidad' => 10,
            'observacion' => 'Ordenar la zona AA',
        ];
    }

    private function usuario(string $rol): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('codigo', $rol)->firstOrFail()->id,
            'username' => strtolower($rol).'_registro',
            'email' => strtolower($rol).'_registro@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
    }
}
