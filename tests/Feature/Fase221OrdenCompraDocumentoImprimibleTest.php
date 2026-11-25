<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase221OrdenCompraDocumentoImprimibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_documento_muestra_importes_y_condiciones_sin_datos_operativos_internos(): void
    {
        $usuario = $this->usuario('COMERCIAL_LOGISTICA', 'logistica-221');
        $orden = $this->orden($usuario);

        $this->actingAs($usuario)->get(route('ordenes-compra.show', $orden))
            ->assertOk()->assertSee('Documento para imprimir');

        $respuesta = $this->get(route('ordenes-compra.documento', $orden))->assertOk();
        $directivasCache = array_map('trim', explode(',', $respuesta->headers->get('Cache-Control', '')));
        $this->assertContains('private', $directivasCache);
        $this->assertContains('no-store', $directivasCache);
        $respuesta->assertSee('Proveedor Documento 221 SAC')
            ->assertSee('20622100001')
            ->assertSee('Producto para documento')
            ->assertSee('UND')
            ->assertSee('50.00')
            ->assertSee('10.00')
            ->assertSee('90.00')
            ->assertSee('16.20')
            ->assertSee('-0.01')
            ->assertSee('106.19')
            ->assertSee('Crédito a 15 días')
            ->assertSee('Entrega en almacén principal')
            ->assertDontSee('OBSERVACIÓN INTERNA PRIVADA')
            ->assertDontSee('REGISTRO INTERNO DE RECEPCIÓN');
    }

    public function test_acceso_protegido_y_orden_anulada_identificada(): void
    {
        $usuario = $this->usuario('COMERCIAL_LOGISTICA', 'logistica-protegida-221');
        $planta = $this->usuario('JEFE_PLANTA', 'planta-221');
        $orden = $this->orden($usuario);

        $this->get(route('ordenes-compra.documento', $orden))->assertRedirect(route('login'));
        $this->actingAs($planta)->get(route('ordenes-compra.documento', $orden))->assertForbidden();

        $orden->update(['estado' => 'ANULADA']);
        $this->actingAs($usuario)->get(route('ordenes-compra.documento', $orden))
            ->assertOk()->assertSee('Orden anulada.');
    }

    private function orden(User $usuario): OrdenCompra
    {
        $unidad = UnidadMedida::query()->firstOrCreate(['codigo' => 'UND'], [
            'nombre' => 'Unidad', 'estado' => true,
        ]);
        $producto = Producto::query()->create([
            'unidad_medida_id' => $unidad->id, 'codigo' => 'DOC-221-P',
            'descripcion' => 'Producto para documento', 'estado' => true,
        ]);
        $proveedor = Proveedor::query()->create([
            'ruc' => '20622100001', 'razon_social' => 'Proveedor Documento 221 SAC',
            'direccion' => 'Av. Industrial 221', 'estado' => true,
        ]);
        $requisicion = Requisicion::query()->create([
            'codigo' => 'REQ-221', 'fecha_solicitud' => today(), 'origen' => 'REPOSICION',
            'prioridad' => 'MEDIA', 'estado' => 'COTIZANDO', 'solicitado_por' => $usuario->id,
        ]);
        $cotizacion = Cotizacion::query()->create([
            'requisicion_id' => $requisicion->id, 'proveedor_id' => $proveedor->id,
            'codigo' => 'CP-221', 'numero_documento' => 'PROV-221',
            'fecha_cotizacion' => today(), 'moneda' => 'PEN', 'subtotal' => 90,
            'impuesto' => 16.20, 'total_calculado' => 106.20,
            'ajuste_redondeo' => -0.01, 'total' => 106.19,
            'estado' => 'SELECCIONADA', 'registrado_por' => $usuario->id,
        ]);
        $solicitud = SolicitudCompra::query()->create([
            'cotizacion_id' => $cotizacion->id, 'codigo' => 'SC-221',
            'fecha_solicitud' => today(), 'total_lineas' => 106.20,
            'ajuste_redondeo' => -0.01, 'total_seleccionado' => 106.19,
            'estado' => 'CONVERTIDA', 'solicitado_por' => $usuario->id,
        ]);
        $orden = OrdenCompra::query()->create([
            'solicitud_compra_id' => $solicitud->id, 'proveedor_id' => $proveedor->id,
            'codigo' => 'OC-221', 'fecha_emision' => today(),
            'fecha_entrega_requerida' => today()->addDays(7), 'moneda' => 'PEN',
            'subtotal' => 90, 'impuesto' => 16.20,
            'ajuste_redondeo' => -0.01, 'total' => 106.19,
            'condiciones_pago' => 'Crédito a 15 días',
            'condiciones_entrega' => 'Entrega en almacén principal',
            'observacion' => 'OBSERVACIÓN INTERNA PRIVADA',
            'estado' => 'APROBADA', 'emitido_por' => $usuario->id,
        ]);
        $orden->detalles()->create([
            'producto_id' => $producto->id, 'cantidad_ordenada' => 2,
            'precio_unitario' => 50, 'descuento_porcentaje' => 10,
            'subtotal' => 90, 'observacion' => 'REGISTRO INTERNO DE RECEPCIÓN',
        ]);

        return $orden;
    }

    private function usuario(string $rol, string $nombre): User
    {
        $role = Role::query()->firstOrCreate(['codigo' => $rol], [
            'nombre' => $rol, 'estado' => true,
        ]);

        return User::query()->create([
            'role_id' => $role->id, 'username' => $nombre,
            'email' => $nombre.'@example.com', 'password' => 'clave-de-prueba',
            'estado' => true, 'fecha_creacion' => now(),
        ]);
    }
}
