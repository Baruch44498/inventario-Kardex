<?php

namespace App\Console\Commands;

use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Repisa;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CargarInventarioDemoVolvo extends Command
{
    protected $signature = 'hidroil:inventario-volvo
        {--aplicar : Crear catálogo y ubicaciones DEMO (sin existencias)}
        {--stock-demo : Cargar cinco existencias ficticias con movimiento de Kardex; requiere --aplicar}
        {--usuario= : ID de usuario responsable de los movimientos de prueba}';

    protected $description = 'Carga local de materiales del Excel Volvo SCH-40 para probar Planta y Almacén';

    private const UNIDADES = [
        'UNIDAD' => ['UND', 'Unidad', false],
        'EPP' => ['UND', 'Unidad', false],
        '#N/A' => ['UND', 'Unidad', false],
        'METRO' => ['MTS', 'Metros', true],
        'GALON' => ['GLN', 'Galón', true],
        'KIT' => ['KIT', 'Kit', false],
        'PLIEGO' => ['PLG', 'Pliego', false],
        'KG' => ['KGM', 'Kilogramo', true],
        'CIENTO' => ['CTO', 'Ciento', false],
        'ROLLO' => ['ROL', 'Rollo', false],
    ];

    // Cantidades inventadas para ensayar faltantes y disponibilidad; no proceden del Excel.
    private const STOCK_DEMO = ['10154' => 2, '10276' => 10, '10459' => 12, '10004' => 8, '10199' => 2];

    public function handle(): int
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->error('Este comando solo puede ejecutarse con APP_ENV=local o testing.');
            return self::FAILURE;
        }

        if ($this->option('stock-demo') && ! $this->option('aplicar')) {
            $this->error('--stock-demo requiere --aplicar.');
            return self::FAILURE;
        }

        $usuario = null;
        if ($this->option('stock-demo')) {
            $usuario = User::query()->find($this->option('usuario'));
            if (! $usuario) {
                $this->error('Para --stock-demo indica un ID válido con --usuario=ID.');
                return self::FAILURE;
            }
        }

        try {
            $filas = $this->leerArchivo();
            $productos = $this->consolidar($filas);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info(count($filas).' líneas materiales, '.count($productos).' códigos únicos y 3 repisas DEMO.');
        $this->line('Costo del Excel e inventario: PEN con IGV de compra, como las notas de ingreso.');
        $this->line('Las cantidades presupuestadas NO se registran como stock.');

        if (! $this->option('aplicar')) {
            $this->comment('Vista previa: añade --aplicar para crear catálogo sin stock.');
            return self::SUCCESS;
        }

        $contadores = ['productos' => 0, 'ubicaciones' => 0, 'proveedores' => 0, 'movimientos' => 0, 'conflictos' => 0];

        DB::transaction(function () use ($filas, $productos, $usuario, &$contadores): void {
            $repisas = [];
            foreach (['DEMO-VOLVO-EST' => 'Estructura', 'DEMO-VOLVO-EQP' => 'Equipos', 'DEMO-VOLVO-ACB' => 'Acabados'] as $codigo => $nombre) {
                $repisas[$codigo] = Repisa::query()->firstOrCreate(['codigo' => $codigo], [
                    'descripcion' => 'PRUEBA Volvo SCH-40 · '.$nombre.' · ubicación ficticia',
                    'estado' => true,
                ]);
            }

            foreach (array_unique(array_filter(array_column($filas, 'proveedor_excel'))) as $nombre) {
                $proveedor = Proveedor::query()->firstOrCreate(['razon_social' => $nombre], [
                    'ruc' => null,
                    'estado' => true,
                ]);
                $contadores['proveedores'] += (int) $proveedor->wasRecentlyCreated;
            }

            foreach ($productos as $fila) {
                [$unidadCodigo, $unidadNombre, $fraccionable] = self::UNIDADES[$fila['unidad_excel']];
                $unidad = UnidadMedida::query()->firstOrCreate(['codigo' => $unidadCodigo], [
                    'nombre' => $unidadNombre,
                    'estado' => true,
                ]);

                $producto = Producto::query()->where('codigo', $fila['codigo'])->first();
                if ($producto && (
                    $this->normalizarDescripcion($producto->descripcion) !== $this->normalizarDescripcion($fila['descripcion'])
                    || (int) $producto->unidad_medida_id !== (int) $unidad->id
                )) {
                    $this->warn("Conflicto código {$fila['codigo']} (fila {$fila['fila_excel']}): producto existente distinto; se omite.");
                    $contadores['conflictos']++;
                    continue;
                }

                if (! $producto) {
                    $producto = Producto::query()->create([
                        'codigo' => $fila['codigo'],
                        'descripcion' => $fila['descripcion'],
                        'unidad_medida_id' => $unidad->id,
                        'permite_fraccionamiento' => $fraccionable || (float) $fila['cantidad_presupuestada'] !== floor((float) $fila['cantidad_presupuestada']),
                        'estado' => true,
                    ]);
                    $contadores['productos']++;
                }

                $costoInventario = round((float) $fila['costo_unitario_con_igv_pen'], 4);
                $inventario = Inventario::query()->firstOrCreate([
                    'producto_id' => $producto->id,
                    'repisa_id' => $repisas[$fila['repisa_demo']]->id,
                ], [
                    'stock_actual' => 0,
                    'stock_minimo' => 0,
                    'stock_maximo' => null,
                    'costo_promedio_soles' => $costoInventario,
                ]);
                $contadores['ubicaciones'] += (int) $inventario->wasRecentlyCreated;

                // Corregir solo la referencia antigua del fixture, sin tocar saldos ni Kardex.
                $referenciaAnterior = round($costoInventario / 1.18, 4);
                if (! $inventario->wasRecentlyCreated && (float) $inventario->stock_actual === 0.0
                    && abs((float) $inventario->costo_promedio_soles - $referenciaAnterior) < 0.0001
                    && ! $inventario->movimientos()->exists()) {
                    $inventario->update(['costo_promedio_soles' => $costoInventario]);
                }

                if ($usuario && isset(self::STOCK_DEMO[$fila['codigo']])) {
                    $cantidad = self::STOCK_DEMO[$fila['codigo']];
                    // Jamás sobreescribir un Kardex o existencias ya trabajadas en esta ubicación.
                    if ((float) $inventario->stock_actual !== 0.0 || $inventario->movimientos()->exists()) {
                        continue;
                    }

                    $inventario->update(['stock_actual' => $cantidad]);
                    MovimientoInventario::query()->create([
                        'inventario_id' => $inventario->id,
                        'producto_id' => $producto->id,
                        'repisa_id' => $inventario->repisa_id,
                        'tipo_movimiento' => 'ENTRADA',
                        'motivo' => 'DEMO_VOLVO',
                        'origen_tipo' => 'DEMO_VOLVO',
                        'origen_id' => 1,
                        'origen_detalle_id' => (int) $fila['fila_excel'],
                        'cantidad' => $cantidad,
                        'stock_anterior' => 0,
                        'stock_posterior' => $cantidad,
                        'costo_unitario' => $inventario->costo_promedio_soles,
                        'costo_promedio_anterior' => $inventario->costo_promedio_soles,
                        'costo_promedio_nuevo' => $inventario->costo_promedio_soles,
                        'fecha_movimiento' => now(),
                        'observacion' => 'EXISTENCIA FICTICIA para probar Planta y Almacén. Excel Volvo SCH-40, fila '.$fila['fila_excel'],
                        'registrado_por' => $usuario->id,
                    ]);
                    $contadores['movimientos']++;
                }
            }
        });

        $this->info('Creados: '.json_encode($contadores, JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }

    private function leerArchivo(): array
    {
        $ruta = database_path('fixtures/volvo_sch40_inventario.csv');
        if (! is_file($ruta)) {
            throw new RuntimeException('No se pudo abrir '.$ruta);
        }
        $archivo = fopen($ruta, 'rb');
        if ($archivo === false) {
            throw new RuntimeException('No se pudo abrir '.$ruta);
        }

        try {
            $cabecera = fgetcsv($archivo, 0, ';');
            if ($cabecera !== ['fila_excel', 'codigo', 'descripcion', 'unidad_excel', 'cantidad_presupuestada', 'costo_unitario_con_igv_pen', 'proveedor_excel', 'repisa_demo']) {
                throw new RuntimeException('La cabecera del fixture Volvo es incorrecta.');
            }
            $filas = [];
            while (($celdas = fgetcsv($archivo, 0, ';')) !== false) {
                if (count($celdas) !== 8) {
                    throw new RuntimeException('Fila incompleta en el fixture Volvo.');
                }
                $fila = array_combine($cabecera, $celdas);
                if (! isset(self::UNIDADES[$fila['unidad_excel']])
                    || ! in_array($fila['repisa_demo'], ['DEMO-VOLVO-EST', 'DEMO-VOLVO-EQP', 'DEMO-VOLVO-ACB'], true)
                    || ! is_numeric($fila['costo_unitario_con_igv_pen'])
                    || (float) $fila['costo_unitario_con_igv_pen'] <= 0) {
                    throw new RuntimeException('Unidad, repisa o precio inválido en fila Excel '.$fila['fila_excel']);
                }
                $filas[] = $fila;
            }
            if (count($filas) !== 187) {
                throw new RuntimeException('Se esperaban 187 líneas materiales; verifica el fixture Volvo.');
            }
            return $filas;
        } finally {
            fclose($archivo);
        }
    }

    private function consolidar(array $filas): array
    {
        $productos = [];
        foreach ($filas as $fila) {
            $codigo = $fila['codigo'];
            if (isset($productos[$codigo])) {
                $primera = $productos[$codigo];
                if ($primera['descripcion'] !== $fila['descripcion'] || $primera['unidad_excel'] !== $fila['unidad_excel']
                    || abs((float) $primera['costo_unitario_con_igv_pen'] - (float) $fila['costo_unitario_con_igv_pen']) > 0.000001) {
                    throw new RuntimeException('El código '.$codigo.' tiene datos contradictorios en el Excel.');
                }
                continue;
            }
            $productos[$codigo] = $fila;
        }
        return $productos;
    }

    private function normalizarDescripcion(string $descripcion): string
    {
        // El Excel duplica espacios; conservar el nombre registrado en el catálogo.
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim($descripcion)) ?? trim($descripcion));
    }
}
