<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Role;
use App\Models\TipoCliente;
use App\Models\TipoOrden;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase220DocumentoClienteImprimibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_om_muestra_precio_comercial_y_oculta_costos_y_margenes_internos(): void
    {
        $usuario = $this->usuario('COMERCIAL_LOGISTICA');
        $cotizacion = $this->cotizacion($usuario, 'OM');
        $cotizacion->detalles()->create([
            'codigo_producto' => 'REP-220', 'descripcion' => 'Repuesto visible al cliente',
            'unidad_medida' => 'UND', 'cantidad' => 2,
            'costo_referencia' => 98765.4321, 'margen_sugerido' => 77.77,
            'precio_sugerido' => 99999.9999, 'precio_unitario' => 20,
            'total' => 40, 'observacion' => 'USO INTERNO PRIVADO',
        ]);
        $cotizacion->detalles()->create([
            'tipo_linea' => 'SERVICIO', 'codigo_producto' => 'COST-OM-1',
            'descripcion' => 'Servicio de mantenimiento', 'cantidad' => 1,
            'precio_unitario' => 30, 'total' => 30,
        ]);

        $this->actingAs($usuario)->get(route('cotizaciones-cliente.show', $cotizacion))
            ->assertOk()->assertSee('Documento para imprimir');
        $respuesta = $this->get(route('cotizaciones-cliente.documento', $cotizacion))->assertOk();
        $directivasCache = array_map('trim', explode(',', $respuesta->headers->get('Cache-Control', '')));
        $this->assertContains('private', $directivasCache);
        $this->assertContains('no-store', $directivasCache);
        $respuesta->assertSee('Repuesto visible al cliente')
            ->assertSee('Otros conceptos incluidos en la propuesta')
            ->assertSee('118.00')
            ->assertDontSee('98765.4321')
            ->assertDontSee('99999.9999')
            ->assertDontSee('USO INTERNO PRIVADO')
            ->assertDontSee('COST-OM-1')
            ->assertDontSee('Presupuesto interno de ejecución');
    }

    public function test_os_muestra_solo_concepto_total_y_estado_borrador_con_acceso_protegido(): void
    {
        $usuario = $this->usuario('COMERCIAL_LOGISTICA');
        $planta = $this->usuario('JEFE_PLANTA', 'planta-documento');
        $cotizacion = $this->cotizacion($usuario, 'OS', 'ABIERTA');
        $cotizacion->update(['descripcion_trabajo' => 'Servicio integral visible']);
        $cotizacion->detalles()->create([
            'codigo_producto' => 'INTERNO-220', 'descripcion' => 'COSTO SECRETO DEL SERVICIO',
            'cantidad' => 1, 'precio_unitario' => 50, 'total' => 50,
        ]);

        $this->get(route('cotizaciones-cliente.documento', $cotizacion))->assertRedirect(route('login'));
        $this->actingAs($planta)->get(route('cotizaciones-cliente.documento', $cotizacion))->assertForbidden();
        $this->actingAs($usuario)->get(route('cotizaciones-cliente.documento', $cotizacion))
            ->assertOk()->assertSee('Servicio integral visible')->assertSee('BORRADOR')
            ->assertSee('Borrador sujeto a revisión')
            ->assertDontSee('COSTO SECRETO DEL SERVICIO')
            ->assertDontSee('INTERNO-220');
    }

    private function usuario(string $rol, string $nombre = 'logistica-documento'): User
    {
        $role = Role::query()->firstOrCreate(['codigo' => $rol], ['nombre' => $rol, 'estado' => true]);

        return User::query()->create([
            'role_id' => $role->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'clave-de-prueba',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }

    private function cotizacion(User $usuario, string $tipoCodigo, string $estado = 'CERRADA'): CotizacionCliente
    {
        $tipoCliente = TipoCliente::query()->firstOrCreate(['codigo' => 'FINAL'], [
            'nombre' => 'Final', 'porcentaje_ganancia' => 0, 'estado' => true,
        ]);
        $cliente = Cliente::query()->create([
            'tipo_cliente_id' => $tipoCliente->id, 'tipo_documento' => 'RUC',
            'numero_documento' => '20600220001', 'ruc' => '20600220001',
            'razon_social' => 'Cliente Documento', 'estado' => true,
        ]);
        $tipo = TipoOrden::query()->firstOrCreate(['codigo' => $tipoCodigo], [
            'nombre' => $tipoCodigo, 'estado' => true,
        ]);

        return CotizacionCliente::query()->create([
            'origen' => 'DIRECTA_LOGISTICA', 'cliente_id' => $cliente->id,
            'tipo_orden_id' => $tipo->id, 'codigo_base' => 'DOC-220',
            'version' => 1, 'codigo' => 'DOC-220-VRS1',
            'cliente_nombre' => $cliente->razon_social, 'cliente_documento' => $cliente->ruc,
            'fecha_emision' => today(), 'moneda' => 'PEN',
            'descripcion_trabajo' => 'Servicio cotizado', 'subtotal' => 100,
            'impuesto' => 18, 'total' => 118, 'estado' => $estado,
            'cotizado_por' => $usuario->id,
        ]);
    }
}
