<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

class ImportarCatalogoVolvo extends Command
{
    protected $signature = 'hidroil:importar-catalogo-volvo {--aplicar : Guarda el catálogo después de revisar la vista previa}';

    protected $description = 'Carga los productos y costos de referencia del Excel Volvo SCH-40 sin crear stock ni cotizaciones de proveedor';

    private const ORIGEN = 'VOLVO_SCH40_2026_10_06';

    public function handle(): int
    {
        $ruta = base_path('database/imports/volvo_sch40_catalogo.json');
        if (! is_file($ruta)) {
            $this->error('No se encuentra database/imports/volvo_sch40_catalogo.json.');
            return self::FAILURE;
        }

        try {
            $datos = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('El archivo de catálogo no es un JSON válido: '.$e->getMessage());
            return self::FAILURE;
        }
        $filas = $datos['materiales'] ?? [];
        if (! is_array($filas) || count($filas) !== 189 || count(array_unique(array_column($filas, 'fila_excel'))) !== 189) {
            $this->error('Se esperaban 189 referencias distintas. No se guardó ningún dato.');
            return self::FAILURE;
        }

        $productos = Producto::query()->with('unidadMedida')->get()->keyBy(fn (Producto $p): string => mb_strtoupper(trim($p->codigo)));
        $proveedores = Proveedor::query()->get()->keyBy(fn (Proveedor $p): string => mb_strtoupper(trim($p->razon_social)));
        $unidades = UnidadMedida::query()->get()->keyBy(fn (UnidadMedida $u): string => mb_strtoupper(trim($u->codigo)));
        $faltantes = [];
        $proveedoresNuevos = [];
        $unidadesNuevas = [];
        $fraccionarios = [];
        $errores = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $descripcion = trim((string) ($fila['descripcion'] ?? ''));
            $unidad = trim((string) ($fila['unidad_codigo'] ?? ''));
            $costo = (float) ($fila['costo_unitario_pen'] ?? 0);
            if ($codigo === '' || $descripcion === '' || $unidad === '' || $costo <= 0) {
                $errores[] = 'Fila '.($fila['fila_excel'] ?? '?').': falta código, descripción, unidad o costo.';
                continue;
            }
            $llave = mb_strtoupper($codigo);
            $existente = $productos->get($llave);
            if ($existente && mb_strtoupper(trim($existente->descripcion)) !== mb_strtoupper($descripcion)) {
                $errores[] = "Código {$codigo}: el catálogo ya tiene otra descripción (fila {$fila['fila_excel']}).";
            } elseif ($existente && mb_strtoupper((string) $existente->unidadMedida?->codigo) !== mb_strtoupper($unidad)) {
                $errores[] = "Código {$codigo}: la unidad existente es distinta (fila {$fila['fila_excel']}).";
            } elseif ($existente && ! $existente->estado) {
                $errores[] = "Código {$codigo}: el producto existente está inactivo.";
            } elseif ($existente && ! $existente->permite_fraccionamiento && $fila['permite_fraccionamiento']) {
                $errores[] = "Código {$codigo}: el Excel usa cantidad fraccionaria y el producto existente la prohíbe.";
            } elseif (! $existente) {
                $faltantes[$llave] = $fila;
            }
            if (! $unidades->has(mb_strtoupper($unidad))) {
                $unidadesNuevas[$unidad] = $fila['unidad_nombre'];
            }
            $proveedor = $fila['proveedor'] ?? null;
            if ($proveedor !== null && ! $proveedores->has(mb_strtoupper($proveedor))) {
                $proveedoresNuevos[mb_strtoupper($proveedor)] = $proveedor;
            }
            if ($fila['permite_fraccionamiento']) {
                $fraccionarios[$llave] = true;
            }
        }

        // Una segunda fila del mismo producto puede ser fraccionaria aunque la primera no lo sea.
        foreach ($faltantes as $llave => &$fila) {
            $fila['permite_fraccionamiento'] = isset($fraccionarios[$llave]);
        }
        unset($fila);

        $this->line('Origen: '.$datos['origen_archivo']);
        $this->line('Referencias: 189 · productos distintos: '.count(array_unique(array_column($filas, 'codigo'))));
        $this->line('Por crear: '.count($faltantes).' productos, '.count($proveedoresNuevos).' proveedores y '.count($unidadesNuevas).' unidades.');
        $this->line('Sin proveedor identificado: '.count(array_filter($filas, fn (array $f): bool => empty($f['proveedor']))).' filas.');

        if ($errores !== []) {
            foreach (array_unique($errores) as $error) {
                $this->error($error);
            }
            $this->error('Corrige los conflictos antes de importar; no se modificó la base de datos.');
            return self::FAILURE;
        }
        if (! $this->option('aplicar')) {
            $this->info('Vista previa: ejecuta de nuevo con --aplicar para guardar.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($filas, $faltantes, $proveedoresNuevos, $unidadesNuevas): void {
            foreach ($unidadesNuevas as $codigo => $nombre) {
                UnidadMedida::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'estado' => true]);
            }
            foreach ($proveedoresNuevos as $nombre) {
                Proveedor::query()->firstOrCreate(['razon_social' => $nombre], ['estado' => true]);
            }
            $idsUnidad = UnidadMedida::query()->get()->mapWithKeys(
                fn (UnidadMedida $u): array => [mb_strtoupper(trim($u->codigo)) => $u->id]
            );
            foreach ($faltantes as $fila) {
                Producto::query()->firstOrCreate(['codigo' => $fila['codigo']], [
                    'unidad_medida_id' => $idsUnidad[mb_strtoupper($fila['unidad_codigo'])],
                    'descripcion' => $fila['descripcion'],
                    'permite_fraccionamiento' => $fila['permite_fraccionamiento'],
                    'estado' => true,
                ]);
            }
            $idsProducto = Producto::query()->get()->mapWithKeys(
                fn (Producto $p): array => [mb_strtoupper(trim($p->codigo)) => $p->id]
            );
            $idsProveedor = Proveedor::query()->get()->mapWithKeys(
                fn (Proveedor $p): array => [mb_strtoupper(trim($p->razon_social)) => $p->id]
            );
            foreach ($filas as $fila) {
                DB::table('producto_referencias_costeo')->updateOrInsert(
                    ['origen' => self::ORIGEN, 'fila_excel' => $fila['fila_excel']],
                    [
                        'producto_id' => $idsProducto[mb_strtoupper($fila['codigo'])],
                        'proveedor_id' => $fila['proveedor'] ? ($idsProveedor[mb_strtoupper($fila['proveedor'])] ?? null) : null,
                        'codigo_original' => $fila['codigo_original'] ?: null,
                        'cantidad_referencial' => $fila['cantidad'],
                        'costo_unitario_pen' => $fila['costo_unitario_pen'],
                        'margen_porcentaje' => $fila['margen_porcentaje'],
                        'observacion' => $fila['observacion'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        $this->info('Catálogo cargado: 189 referencias. No se creó stock ni se alteraron los precios históricos reales.');
        return self::SUCCESS;
    }
}
