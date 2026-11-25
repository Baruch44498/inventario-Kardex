<?php

use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\Role;
use App\Models\SolicitudCompra;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'sqlite') {
    throw new RuntimeException('La auditoría requiere APP_ENV=testing y una base SQLite de prueba.');
}

$clave = Str::random(32);
$usuario = User::query()->create([
    'role_id' => Role::query()->where('codigo', 'ALMACEN')->firstOrFail()->id,
    'username' => 'almacen_auditoria',
    'email' => 'almacen-auditoria@example.test',
    'password' => $clave,
    'estado' => true,
    'fecha_creacion' => now(),
]);
$unidad = UnidadMedida::query()->where('codigo', 'UND')->firstOrFail();
$producto = Producto::query()->create([
    'unidad_medida_id' => $unidad->id,
    'codigo' => 'UI-MOVIL-01',
    'descripcion' => 'Punto de engrase industrial de prueba para auditoría móvil',
    'estado' => true,
]);
$repisa = Repisa::query()->create(['codigo' => 'QA001', 'descripcion' => 'Repisa de prueba', 'estado' => true]);
$inventario = Inventario::query()->create([
    'producto_id' => $producto->id,
    'repisa_id' => $repisa->id,
    'stock_actual' => 10,
    'costo_promedio_soles' => 5,
]);

$proveedor = Proveedor::query()->create([
    'ruc' => '20999999991',
    'razon_social' => 'Proveedor de prueba de interfaz S.A.C.',
    'estado' => true,
]);
$cotizacion = Cotizacion::query()->create([
    'proveedor_id' => $proveedor->id,
    'codigo' => 'CP-UI-01',
    'numero_documento' => 'DOC-UI-01',
    'fecha_cotizacion' => now()->toDateString(),
    'moneda' => 'PEN',
    'tipo_cambio' => 1,
    'subtotal' => 100,
    'impuesto' => 18,
    'total_calculado' => 118,
    'total' => 118,
    'estado' => 'SELECCIONADA',
    'registrado_por' => $usuario->id,
]);
$oferta = $cotizacion->detalles()->create([
    'producto_id' => $producto->id,
    'tipo_vinculacion' => 'ADICIONAL',
    'vinculacion_origen' => 'MANUAL',
    'cantidad' => 10,
    'precio_unitario' => 11.8,
    'igv_modo' => 'INCLUIDO',
    'igv_porcentaje' => 18,
    'subtotal' => 100,
    'impuesto' => 18,
    'total' => 118,
]);
$justificacion = 'Orden para pruebas de interfaz.';
$solicitud = SolicitudCompra::query()->create([
    'cotizacion_id' => $cotizacion->id,
    'codigo' => 'SC-UI-01',
    'fecha_solicitud' => now()->toDateString(),
    'origen' => 'COMPRA_DIRECTA',
    'justificacion_origen' => $justificacion,
    'total_lineas' => 118,
    'total_seleccionado' => 118,
    'estado' => 'CONVERTIDA',
    'solicitado_por' => $usuario->id,
]);
$detalle = $solicitud->detalles()->create([
    'cotizacion_detalle_id' => $oferta->id,
    'producto_id' => $producto->id,
    'cantidad' => 10,
    'precio_unitario' => 11.8,
    'subtotal' => 118,
]);
$orden = OrdenCompra::query()->create([
    'solicitud_compra_id' => $solicitud->id,
    'proveedor_id' => $proveedor->id,
    'codigo' => 'OC-UI-01',
    'origen' => 'COMPRA_DIRECTA',
    'justificacion_origen' => $justificacion,
    'fecha_emision' => now()->subDays(5)->toDateString(),
    'fecha_entrega_requerida' => now()->subDays(2)->toDateString(),
    'moneda' => 'PEN',
    'tipo_cambio' => 1,
    'subtotal' => 100,
    'impuesto' => 18,
    'total' => 118,
    'estado' => 'APROBADA',
    'emitido_por' => $usuario->id,
    'aprobado_por' => $usuario->id,
    'aprobado_en' => now(),
]);
$lineaOrden = $orden->detalles()->create([
    'solicitud_compra_detalle_id' => $detalle->id,
    'producto_id' => $producto->id,
    'cantidad_ordenada' => 10,
    'cantidad_recibida' => 0,
    'precio_unitario' => 11.8,
    'subtotal' => 118,
]);

$directorio = __DIR__.'/.runtime';
if (! is_dir($directorio)) {
    mkdir($directorio, 0700);
}

file_put_contents($directorio.'/escenario.json', json_encode([
    'email' => $usuario->email,
    'clave' => $clave,
    'producto' => $producto->codigo,
    'repisa' => $repisa->codigo,
    'orden' => $orden->codigo,
    'rutas' => [
        'registro' => route('registro-almacen.index', [], false),
        'registroDetalle' => route('registro-almacen.index', ['q' => $producto->codigo, 'producto' => $producto->id], false),
        'inventario' => route('inventarios-periodicos.create', [], false),
        'orden' => route('ordenes-compra.show', $orden, false),
    ],
], JSON_THROW_ON_ERROR));

require __DIR__.'/preparar-barrido.php';
