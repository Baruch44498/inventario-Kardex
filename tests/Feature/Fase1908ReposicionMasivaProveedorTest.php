<?php

namespace Tests\Feature;

use App\Models\AlertaStock;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Compras\ProveedoresSugeridosProductoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase1908ReposicionMasivaProveedorTest extends TestCase
{
    use RefreshDatabase;

    private User $almacen;
    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->almacen = User::query()->create([
            'role_id' => Role::query()->where('codigo', 'ALMACEN')->firstOrFail()->id,
            'username' => 'almacen_1908',
            'email' => 'almacen_1908@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $this->unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'UND'],
            ['nombre' => 'Unidad', 'estado' => true]
        );
    }

    public function test_prepara_una_o_varias_alertas_y_consolida_el_producto_repetido_por_repisa(): void
    {
        [$producto, $alertaA] = $this->crearAlerta('MAT-1908-A', 'Producto masivo A', 'R-1908-A');
        [, $alertaB] = $this->crearAlertaParaProducto($producto, 'R-1908-B');
        [$otro, $alertaC] = $this->crearAlerta('MAT-1908-B', 'Producto masivo B', 'R-1908-C');

        $response = $this->actingAs($this->almacen)
            ->post(route('alertas.preparar-requerimiento'), [
                'alcance' => 'SELECCIONADAS',
                'alerta_ids' => [$alertaA->id, $alertaB->id, $alertaC->id],
            ]);

        $response->assertRedirect(route('requerimientos-compra.create'));

        $input = session()->get('_old_input');
        $this->assertSame('REPOSICION', $input['origen']);
        $this->assertSame('ALTA', $input['prioridad']);
        $this->assertCount(2, $input['detalles']);
        $this->assertEqualsCanonicalizing(
            [$producto->id, $otro->id],
            collect($input['detalles'])->pluck('producto_id')->all()
        );
        $detallesPorProducto = collect($input['detalles'])->keyBy('producto_id');
        $this->assertEquals(58, (float) $detallesPorProducto[$producto->id]['cantidad_solicitada']);
        $this->assertEquals(29, (float) $detallesPorProducto[$otro->id]['cantidad_solicitada']);
        $this->assertStringContainsString('R-1908-A, R-1908-B', $input['detalles'][0]['observacion']);
    }

    public function test_prepara_todas_las_alertas_elegibles_respetando_los_filtros(): void
    {
        [$incluido] = $this->crearAlerta('MAT-1908-F1', 'Filtro bomba hidráulica', 'R-1908-F1');
        $this->crearAlerta('MAT-1908-F2', 'Filtro eléctrico', 'R-1908-F2');
        [, $resuelta] = $this->crearAlerta('MAT-1908-F3', 'Filtro bomba resuelta', 'R-1908-F3', 'RESUELTA');

        $response = $this->actingAs($this->almacen)
            ->post(route('alertas.preparar-requerimiento'), [
                'alcance' => 'FILTRADAS',
                'q' => 'bomba',
            ]);

        $response->assertRedirect(route('requerimientos-compra.create'));

        $detalles = collect(session()->get('_old_input.detalles'));
        $this->assertSame([$incluido->id], $detalles->pluck('producto_id')->all());
        $this->assertFalse($detalles->contains('producto_id', $resuelta->producto_id));
    }

    public function test_exige_una_seleccion_cuando_el_alcance_es_seleccionadas(): void
    {
        $this->actingAs($this->almacen)
            ->from(route('alertas.index'))
            ->post(route('alertas.preparar-requerimiento'), [
                'alcance' => 'SELECCIONADAS',
            ])
            ->assertRedirect(route('alertas.index'))
            ->assertSessionHasErrors('alerta_ids');
    }

    public function test_distribuye_sin_repetir_productos_y_separa_los_sin_proveedor(): void
    {
        $service = app(ProveedoresSugeridosProductoService::class);
        $porProducto = collect([
            1 => collect([$this->filaProveedor(1, 10, 'Proveedor A', '2026-09-01')]),
            2 => collect([
                $this->filaProveedor(2, 10, 'Proveedor A', '2026-09-01'),
                $this->filaProveedor(2, 20, 'Proveedor B', '2026-08-01'),
            ]),
            3 => collect([$this->filaProveedor(3, 20, 'Proveedor B', '2026-08-01')]),
        ]);

        $resultado = $service->coberturaSugerida([1, 2, 3, 4], $porProducto);

        $this->assertFalse($resultado['cobertura_total']);
        $this->assertCount(2, $resultado['grupos']);
        $this->assertSame([1, 2], $resultado['grupos'][0]['producto_ids']->all());
        $this->assertSame([3], $resultado['grupos'][1]['producto_ids']->all());
        $this->assertSame([4], $resultado['sin_proveedor']->all());

        $asignados = $resultado['grupos']->pluck('producto_ids')->flatten();
        $this->assertCount($asignados->unique()->count(), $asignados);
    }

    public function test_detecta_cuando_un_solo_proveedor_cubre_toda_la_lista(): void
    {
        $service = app(ProveedoresSugeridosProductoService::class);
        $porProducto = collect([
            1 => collect([$this->filaProveedor(1, 10, 'Proveedor total', '2026-09-01')]),
            2 => collect([$this->filaProveedor(2, 10, 'Proveedor total', '2026-09-01')]),
        ]);

        $resultado = $service->coberturaSugerida([1, 2], $porProducto);

        $this->assertTrue($resultado['cobertura_total']);
        $this->assertCount(1, $resultado['grupos']);
        $this->assertSame([1, 2], $resultado['grupos']->first()['producto_ids']->all());
        $this->assertTrue($resultado['sin_proveedor']->isEmpty());
    }

    /** @return array{Producto, AlertaStock} */
    private function crearAlerta(
        string $codigo,
        string $descripcion,
        string $repisaCodigo,
        string $estado = 'ACTIVA'
    ): array {
        $producto = Producto::query()->create([
            'unidad_medida_id' => $this->unidad->id,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'estado' => true,
        ]);

        return $this->crearAlertaParaProducto($producto, $repisaCodigo, $estado);
    }

    /** @return array{Producto, AlertaStock} */
    private function crearAlertaParaProducto(
        Producto $producto,
        string $repisaCodigo,
        string $estado = 'ACTIVA'
    ): array {
        $repisa = Repisa::query()->create([
            'codigo' => $repisaCodigo,
            'descripcion' => 'Repisa ' . $repisaCodigo,
            'estado' => true,
        ]);
        $inventario = Inventario::query()->create([
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'stock_maximo' => 30,
            'costo_promedio_soles' => 10,
        ]);
        $alerta = AlertaStock::query()->create([
            'inventario_id' => $inventario->id,
            'producto_id' => $producto->id,
            'repisa_id' => $repisa->id,
            'tipo_alerta' => 'STOCK_MINIMO',
            'nivel' => 'CRITICA',
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'mensaje' => 'Producto por debajo del mínimo.',
            'estado' => $estado,
            'detectada_en' => now(),
        ]);

        return [$producto, $alerta];
    }

    private function filaProveedor(
        int $productoId,
        int $proveedorId,
        string $nombre,
        string $fecha
    ): object {
        return (object) [
            'producto_id' => $productoId,
            'proveedor_id' => $proveedorId,
            'ruc' => '20' . str_pad((string) $proveedorId, 9, '0', STR_PAD_LEFT),
            'razon_social' => $nombre . ' S.A.C.',
            'nombre_comercial' => $nombre,
            'telefono' => null,
            'correo' => null,
            'contacto' => null,
            'cotizaciones_registradas' => 2,
            'ultima_cotizacion' => $fecha,
        ];
    }
}
