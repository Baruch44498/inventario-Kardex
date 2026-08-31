<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\CotizacionComponente;
use App\Models\PlantillaCosteo;
use App\Models\Producto;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\Ventas\PresupuestoCotizacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase19062PlantillasCosteoTest extends TestCase
{
    use RefreshDatabase;

    private User $logistica;
    private CotizacionCliente $cotizacion;
    private TipoOrden $produccion;
    private TipoOrden $servicio;
    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistica = User::query()->create([
            'role_id' => Role::query()
                ->where('codigo', 'COMERCIAL_LOGISTICA')
                ->firstOrFail()
                ->id,
            'username' => 'logistica_19062',
            'email' => 'logistica_19062@example.com',
            'password' => 'password-seguro',
            'estado' => true,
            'fecha_creacion' => now(),
        ]);
        $tipoCliente = TipoCliente::query()->firstOrCreate(
            ['codigo' => 'FINAL'],
            ['nombre' => 'Final', 'porcentaje_ganancia' => 20, 'estado' => true]
        );
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id,
            'tipo_documento' => 'RUC',
            'numero_documento' => '20619062001',
            'ruc' => '20619062001',
            'razon_social' => 'Cliente Plantillas Costeo SAC',
            'estado' => true,
        ]);
        $unidad = UnidadMedida::query()->firstOrCreate(
            ['codigo' => 'M'],
            ['nombre' => 'Metro', 'abreviatura' => 'm', 'estado' => true]
        );
        $this->producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id,
            'codigo' => 'TUB-SCH40-19062',
            'descripcion' => 'Tubería SCH-40',
            'permite_fraccionamiento' => true,
            'estado' => true,
        ]);
        $this->produccion = $this->tipoOrden('OP', 'Producción');
        $this->servicio = $this->tipoOrden('OS', 'Servicio');
        $this->cotizacion = CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA',
            'cliente_id' => $cliente->id,
            'descripcion_trabajo' => 'Fabricación de cisterna',
            'codigo_base' => 'CC-19062',
            'version' => 1,
            'codigo' => 'CC-19062-VRS1',
            'cliente_documento' => $cliente->ruc,
            'cliente_nombre' => $cliente->razon_social,
            'fecha_emision' => now()->toDateString(),
            'moneda' => 'PEN',
            'tipo_cambio' => 3.8,
            'subtotal' => 0,
            'impuesto' => 0,
            'total' => 0,
            'estado' => 'ABIERTA',
            'cotizado_por' => $this->logistica->id,
        ]);
    }

    public function test_guarda_el_costeo_completo_de_un_componente_como_plantilla(): void
    {
        $origen = $this->componente($this->produccion, 1, 3.8);
        $this->cargarModeloBase($origen);

        $this->actingAs($this->logistica)
            ->post(route('cotizacion-componentes.plantillas.guardar', $origen), [
                'nombre' => 'Cisterna 5000 galones SCH-40',
                'descripcion' => 'Modelo base de producción',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $plantilla = PlantillaCosteo::query()
            ->with('partidas')
            ->firstOrFail();

        $this->assertSame($this->produccion->id, $plantilla->tipo_orden_id);
        $this->assertCount(2, $plantilla->partidas);
        $this->assertSame(
            ['ESTRUCTURA DEL TANQUE', 'PAGO DE PERSONAL'],
            $plantilla->partidas->pluck('grupo_costo')->all()
        );
        $this->assertSame($this->producto->id, $plantilla->partidas->first()->producto_id);
        $this->assertSame([1, 2], $plantilla->partidas->pluck('orden_secuencia')->all());
    }

    public function test_aplica_una_plantilla_al_componente_vacio_del_mismo_tipo(): void
    {
        $origen = $this->componente($this->produccion, 1, 3.8);
        $destino = $this->componente($this->produccion, 2, 4.0);
        $this->cargarModeloBase($origen);
        $this->cotizacion->update(['costeo_sincronizado_en' => now()]);

        $this->actingAs($this->logistica)
            ->post(route('cotizacion-componentes.plantillas.guardar', $origen), [
                'nombre' => 'Cisterna 5000 galones SCH-40',
            ])
            ->assertSessionHasNoErrors();

        $plantilla = PlantillaCosteo::query()->firstOrFail();
        $respuesta = $this->actingAs($this->logistica)
            ->post(route('cotizacion-componentes.plantillas.aplicar', $destino), [
                'plantilla_id' => $plantilla->id,
            ]);

        $respuesta
            ->assertRedirect(route('cotizaciones-cliente.presupuesto.show', [
                'cotizacionCliente' => $this->cotizacion,
                'componente_id' => $destino,
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $partidas = $this->cotizacion->presupuestos()
            ->where('componente_id', $destino->id)
            ->where('estado', 'VIGENTE')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $partidas);
        $this->assertSame(4.0, (float) $partidas->first()->tipo_cambio);
        $this->assertSame(80.0, (float) $partidas->first()->costo_neto_soles);
        $this->assertSame('ESTRUCTURA DEL TANQUE', $partidas->first()->grupo_costo);
        $this->assertNotNull($partidas->first()->cotizacion_area_id);
        $this->assertDatabaseHas('cotizacion_areas', [
            'id' => $partidas->first()->cotizacion_area_id,
            'cotizacion_cliente_id' => $this->cotizacion->id,
            'nombre_normalizado' => 'ESTRUCTURA DEL TANQUE',
        ]);
        $this->assertNull($this->cotizacion->fresh()->costeo_sincronizado_en);
    }

    public function test_no_duplica_costos_ni_cruza_plantillas_entre_op_y_os(): void
    {
        $origen = $this->componente($this->produccion, 1, 3.8);
        $destinoConCostos = $this->componente($this->produccion, 2, 3.8);
        $destinoServicio = $this->componente($this->servicio, 3, 3.8);
        $this->cargarModeloBase($origen);
        $this->partida($destinoConCostos, 'MANO_OBRA', 'PAGO DE PERSONAL', 'Ayudante', 1, 'DIA', 'PEN', 3.8, 80);

        $this->actingAs($this->logistica)
            ->post(route('cotizacion-componentes.plantillas.guardar', $origen), [
                'nombre' => 'Cisterna estándar',
            ])
            ->assertSessionHasNoErrors();
        $plantilla = PlantillaCosteo::query()->firstOrFail();

        $this->actingAs($this->logistica)
            ->post(route('cotizacion-componentes.plantillas.aplicar', $destinoConCostos), [
                'plantilla_id' => $plantilla->id,
            ])
            ->assertSessionHasErrors('plantilla_id');
        $this->assertSame(1, $destinoConCostos->presupuestos()->where('estado', 'VIGENTE')->count());

        $this->actingAs($this->logistica)
            ->post(route('cotizacion-componentes.plantillas.aplicar', $destinoServicio), [
                'plantilla_id' => $plantilla->id,
            ])
            ->assertSessionHasErrors('plantilla_id');
        $this->assertSame(0, $destinoServicio->presupuestos()->count());
    }

    public function test_la_pantalla_hace_visible_el_flujo_de_plantillas(): void
    {
        $origen = $this->componente($this->produccion, 1, 3.8);
        $this->cargarModeloBase($origen);

        $this->actingAs($this->logistica)
            ->get(route('cotizaciones-cliente.presupuesto.show', [
                'cotizacionCliente' => $this->cotizacion,
                'componente_id' => $origen,
            ]))
            ->assertOk()
            ->assertSee('Plantillas reutilizables de costeo')
            ->assertSee('Trabajando ahora')
            ->assertSee('Guardar este costeo como plantilla')
            ->assertSee('Guardar 2 partidas como plantilla')
            ->assertSee(route('plantillas-costeo.index'), false);
    }

    private function cargarModeloBase(CotizacionComponente $componente): void
    {
        $this->partida(
            $componente,
            'MATERIAL',
            'ESTRUCTURA DEL TANQUE',
            'Tubería SCH-40',
            2,
            'METRO',
            'USD',
            3.8,
            10,
            $this->producto
        );
        $this->partida(
            $componente,
            'MANO_OBRA',
            'PAGO DE PERSONAL',
            'Armado y soldadura',
            1,
            'DIA',
            'PEN',
            3.8,
            100
        );
    }

    private function partida(
        CotizacionComponente $componente,
        string $tipo,
        ?string $grupo,
        string $descripcion,
        float $cantidad,
        string $unidad,
        string $moneda,
        float $tipoCambio,
        float $costoUnitario,
        ?Producto $producto = null
    ): void {
        app(PresupuestoCotizacionService::class)->registrar(
            $this->cotizacion,
            [
                'componente_id' => $componente->id,
                'producto_id' => $producto?->id,
                'tipo_costo' => $tipo,
                'grupo_costo' => $grupo,
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'unidad' => $unidad,
                'moneda' => $moneda,
                'tipo_cambio' => $tipoCambio,
                'costo_unitario' => $costoUnitario,
                'margen_porcentaje' => 20,
                'carga_social_porcentaje' => 0,
                'igv_modo' => 'NO_APLICA',
                'igv_porcentaje' => 18,
                'igv_venta_porcentaje' => 18,
                'observacion' => null,
            ],
            $this->logistica
        );
    }

    private function componente(
        TipoOrden $tipo,
        int $secuencia,
        float $tipoCambio
    ): CotizacionComponente {
        return $this->cotizacion->componentes()->create([
            'tipo_orden_id' => $tipo->id,
            'descripcion_componente' => "Trabajo {$tipo->codigo} {$secuencia}",
            'tipo_cambio_comparacion' => $tipoCambio,
            'orden_secuencia' => $secuencia,
        ]);
    }

    private function tipoOrden(string $codigo, string $nombre): TipoOrden
    {
        return TipoOrden::query()->updateOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $nombre, 'estado' => true]
        );
    }
}
