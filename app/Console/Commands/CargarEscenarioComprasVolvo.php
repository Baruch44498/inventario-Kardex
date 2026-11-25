<?php

namespace App\Console\Commands;

use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\NotaIngreso;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\SolicitudCompra;
use App\Models\User;
use App\Services\Inventario\RegistrarNotaIngresoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CargarEscenarioComprasVolvo extends Command
{
    protected $signature = 'hidroil:escenario-volvo
        {--aplicar : Crear documentos e ingresos ficticios en las repisas DEMO}
        {--usuario= : ID del usuario que registra y aprueba los documentos}';

    protected $description = 'Escenario local de tres proveedores, compras e ingresos Volvo con Kardex';

    public function handle(RegistrarNotaIngresoService $ingresos): int
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->error('Solo se permite APP_ENV=local o testing.');
            return self::FAILURE;
        }

        $usuario = null;
        if ($this->option('aplicar')) {
            $usuario = User::query()->where('estado', true)->find($this->option('usuario'));
            if (! $usuario) {
                $this->error('Indica un usuario activo con --usuario=ID.');
                return self::FAILURE;
            }
        }

        $codigos = [1, 2, 3];
        $codigosOrden = array_map(fn (int $n): string => $this->codigo('OC', $n), $codigos);
        $codigosCotizacion = array_map(fn (int $n): string => $this->codigo('CV', $n), $codigos);
        $codigosSolicitud = array_map(fn (int $n): string => $this->codigo('SC', $n), $codigos);
        $ordenesPrevias = OrdenCompra::query()->whereIn('codigo', $codigosOrden)->get();
        if ($ordenesPrevias->count() === 3 && $ordenesPrevias->every(
            fn (OrdenCompra $orden): bool => $orden->estado === 'RECIBIDA'
                && NotaIngreso::query()->where('orden_compra_id', $orden->id)
                    ->where('estado', 'CONFIRMADA')->exists()
        )) {
            $this->info('El escenario Volvo ya tiene tres órdenes y notas confirmadas; no se duplica.');
            return self::SUCCESS;
        }
        if ($ordenesPrevias->isNotEmpty()
            || Cotizacion::query()->whereIn('codigo', $codigosCotizacion)->exists()
            || SolicitudCompra::query()->whereIn('codigo', $codigosSolicitud)->exists()) {
            $this->error('Hay documentos DEMO incompletos con esos códigos. Revísalos antes de volver a cargar.');
            return self::FAILURE;
        }

        $ruta = database_path('fixtures/volvo_sch40_inventario.csv');
        if (! is_file($ruta) || ! ($archivo = fopen($ruta, 'rb'))) {
            $this->error('Falta el CSV del inventario Volvo.');
            return self::FAILURE;
        }

        $repisas = Repisa::query()->whereIn('codigo', ['DEMO-VOLVO-EST', 'DEMO-VOLVO-EQP', 'DEMO-VOLVO-ACB'])
            ->get()->keyBy('codigo');
        $filas = [];
        try {
            $cabecera = fgetcsv($archivo, 0, ';');
            while (($datos = fgetcsv($archivo, 0, ';')) !== false) {
                if (! is_array($cabecera) || count($datos) !== count($cabecera)) {
                    $this->error('El CSV Volvo contiene una fila inválida.');
                    return self::FAILURE;
                }
                $fila = array_combine($cabecera, $datos);
                $filas[$fila['codigo']] ??= $fila;
            }
        } finally {
            fclose($archivo);
        }

        if (count($filas) !== 177 || $repisas->count() !== 3) {
            $this->error('Primero aplica el catálogo Volvo: php artisan hidroil:inventario-volvo --aplicar');
            return self::FAILURE;
        }

        $productos = Producto::query()->whereIn('codigo', array_keys($filas))->get()->keyBy('codigo');
        $inventarios = Inventario::query()->whereIn('producto_id', $productos->pluck('id'))
            ->get()->keyBy(fn (Inventario $i): string => $i->producto_id.':'.$i->repisa_id);
        foreach ($filas as $fila) {
            $producto = $productos->get($fila['codigo']);
            $repisa = $repisas->get($fila['repisa_demo']);
            if (! $producto || ! $repisa || ! $inventarios->has($producto->id.':'.$repisa->id)) {
                $this->error('Falta ubicación DEMO para '.$fila['codigo'].'. Completa la importación del catálogo.');
                return self::FAILURE;
            }
        }

        $grupos = [1 => [], 2 => [], 3 => []];
        $omitidos = 0;
        foreach ($filas as $fila) {
            $producto = $productos->get($fila['codigo']);
            $semilla = (int) sprintf('%u', crc32($fila['codigo']));
            if ($semilla % 5 === 0 || $inventarios->where('producto_id', $producto->id)
                ->contains(fn (Inventario $i): bool => (float) $i->stock_actual > 0)) {
                $omitidos++;
                continue;
            }

            $plan = (float) $fila['cantidad_presupuestada'];
            $factor = [0.6, 1.0, 1.4][$semilla % 3];
            $cantidad = $producto->permite_fraccionamiento
                ? round(max(0.5, min(12, $plan * $factor)), 3)
                : max(1, min(12, (int) round($plan * $factor)));
            $grupo = ($semilla % 3) + 1;
            $grupos[$grupo][] = [
                'producto' => $producto,
                'repisa' => $repisas->get($fila['repisa_demo']),
                'cantidad' => $cantidad,
                'precio' => round((float) $fila['costo_unitario_con_igv_pen'], 4),
                'fila_excel' => $fila['fila_excel'],
            ];
        }

        $this->info('Vista previa: '.array_sum(array_map('count', $grupos)).' productos con ingreso, '
            .$omitidos.' sin ingreso adicional; 3 proveedores y 3 notas de compra DEMO.');
        $this->line('Precios del Excel en PEN con IGV. Cantidades simuladas, no existencias físicas.');
        if (! $this->option('aplicar')) {
            $this->comment('Añade --aplicar --usuario=ID para crear el escenario.');
            return self::SUCCESS;
        }

        try {
            $notas = DB::transaction(function () use ($grupos, $usuario, $ingresos): array {
                $notas = [];
                foreach ($grupos as $numero => $items) {
                    if ($items === []) {
                        throw new \RuntimeException('Un proveedor quedó sin productos; se cancela el escenario.');
                    }
                    $proveedor = Proveedor::query()->firstOrCreate(
                        ['razon_social' => sprintf('PROVEEDOR DEMO VOLVO %02d', $numero)],
                        ['ruc' => null, 'estado' => true]
                    );
                    $bruto = 0.0;
                    $base = 0.0;
                    foreach ($items as $item) {
                        $total = round($item['cantidad'] * $item['precio'], 4);
                        $bruto += $total;
                        $base += round($total / 1.18, 4);
                    }
                    $bruto = round($bruto, 4);
                    $base = round($base, 4);
                    $datosDocumento = [
                        'subtotal' => $base,
                        'impuesto' => round($bruto - $base, 4),
                        'total' => $bruto,
                    ];

                    $cotizacion = Cotizacion::query()->create([
                        'proveedor_id' => $proveedor->id,
                        'codigo' => $this->codigo('CV', $numero),
                        'fecha_cotizacion' => today()->toDateString(),
                        'moneda' => 'PEN',
                        ...$datosDocumento,
                        'total_calculado' => $bruto,
                        'estado' => 'SELECCIONADA',
                        'observacion' => 'SIMULACIÓN Volvo SCH-40: proveedor y cantidades ficticios.',
                        'registrado_por' => $usuario->id,
                    ]);
                    $solicitud = SolicitudCompra::query()->create([
                        'cotizacion_id' => $cotizacion->id,
                        'codigo' => $this->codigo('SC', $numero),
                        'fecha_solicitud' => today()->toDateString(),
                        'origen' => 'COMPRA_DIRECTA',
                        'descripcion' => 'SIMULACIÓN de compra Volvo SCH-40',
                        'total_lineas' => $bruto,
                        'total_seleccionado' => $bruto,
                        'estado' => 'CONVERTIDA',
                        'solicitado_por' => $usuario->id,
                        'aprobado_por' => $usuario->id,
                        'aprobado_en' => now(),
                    ]);
                    $orden = OrdenCompra::query()->create([
                        'solicitud_compra_id' => $solicitud->id,
                        'proveedor_id' => $proveedor->id,
                        'codigo' => $this->codigo('OC', $numero),
                        'fecha_emision' => today()->toDateString(),
                        'origen' => 'COMPRA_DIRECTA',
                        'justificacion_origen' => 'SIMULACIÓN para probar Planta y Almacén.',
                        'moneda' => 'PEN',
                        ...$datosDocumento,
                        'estado' => 'APROBADA',
                        'observacion' => 'SIMULACIÓN: no corresponde a una compra ni deuda real.',
                        'emitido_por' => $usuario->id,
                        'aprobado_por' => $usuario->id,
                        'aprobado_en' => now(),
                    ]);
                    $detallesIngreso = [];
                    foreach ($items as $item) {
                        $total = round($item['cantidad'] * $item['precio'], 4);
                        $neto = round($total / 1.18, 4);
                        $detalleCotizacion = $cotizacion->detalles()->create([
                            'tipo_vinculacion' => 'ADICIONAL',
                            'vinculacion_origen' => 'MANUAL',
                            'producto_id' => $item['producto']->id,
                            'cantidad' => $item['cantidad'],
                            'precio_unitario' => $item['precio'],
                            'descuento_modo' => 'SIN_DESCUENTO',
                            'igv_modo' => 'INCLUIDO',
                            'igv_porcentaje' => 18,
                            'subtotal' => $neto,
                            'impuesto' => round($total - $neto, 4),
                            'total' => $total,
                            'observacion' => 'Precio del Excel Volvo, fila '.$item['fila_excel'].'; proveedor simulado.',
                        ]);
                        $detalleSolicitud = $solicitud->detalles()->create([
                            'cotizacion_detalle_id' => $detalleCotizacion->id,
                            'producto_id' => $item['producto']->id,
                            'cantidad' => $item['cantidad'],
                            'precio_unitario' => $item['precio'],
                            'subtotal' => $total,
                        ]);
                        $detalleOrden = $orden->detalles()->create([
                            'solicitud_compra_detalle_id' => $detalleSolicitud->id,
                            'producto_id' => $item['producto']->id,
                            'cantidad_ordenada' => $item['cantidad'],
                            'cantidad_recibida' => 0,
                            'precio_unitario' => $item['precio'],
                            'subtotal' => $total,
                        ]);
                        $detallesIngreso[] = [
                            'orden_compra_detalle_id' => $detalleOrden->id,
                            'producto_id' => $item['producto']->id,
                            'repisa_id' => $item['repisa']->id,
                            'cantidad' => $item['cantidad'],
                            'observacion' => 'SIMULACIÓN Volvo SCH-40, fila '.$item['fila_excel'],
                        ];
                    }
                    $nota = $ingresos->registrarYConfirmar([
                        'motivo_ingreso' => 'COMPRA',
                        'orden_compra_id' => $orden->id,
                        'fecha_ingreso' => today()->toDateString(),
                        'observacion' => 'SIMULACIÓN: materiales de prueba, sin compra física.',
                        'detalles' => $detallesIngreso,
                    ], $usuario);
                    $notas[] = $nota->codigo;
                }
                return $notas;
            });
        } catch (\Throwable $e) {
            $this->error('No se guardó ningún documento ni ingreso: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Escenario creado. Notas de ingreso: '.implode(', ', $notas));
        return self::SUCCESS;
    }

    private function codigo(string $tipo, int $numero): string
    {
        return sprintf('%s-DEMO-VOLVO-%02d', $tipo, $numero);
    }
}
