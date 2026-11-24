<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportarCatalogoAlmacen extends Command
{
    protected $signature = 'hidroil:importar-catalogo-almacen {--aplicar : Inserta después de la vista previa}';

    protected $description = 'Importa el catálogo depurado de almacén sin existencias ni precios';

    private const BASE = 'hidroil_desarrollo_personal';
    private const TOTAL = 1908;

    public function handle(): int
    {
        $ruta = base_path('database/imports/Catalogo_HIDROIL_1908_importar.csv');
        if (! is_file($ruta)) {
            $this->error('Falta database/imports/Catalogo_HIDROIL_1908_importar.csv');
            return self::FAILURE;
        }

        try {
            $conexion = DB::connection();
            $base = $conexion->getDatabaseName();
            if ($conexion->getDriverName() !== 'mysql' || $base !== self::BASE) {
                $this->error('Base inesperada. Se requiere MySQL / '.self::BASE.'. Conectado a: '.$base);
                return self::FAILURE;
            }

            $existentes = (int) DB::table('productos')->count();
            if ($existentes !== 0) {
                $this->error("Hay {$existentes} productos existentes. Esta carga inicial requiere el catálogo vacío; no se modificó nada.");
                return self::FAILURE;
            }

            $unidades = DB::table('unidades_medida')->get(['id', 'codigo', 'estado'])
                ->keyBy(fn ($u) => mb_strtoupper(trim($u->codigo)));
            $archivo = fopen($ruta, 'rb');
            if ($archivo === false) {
                $this->error('No se pudo abrir el archivo CSV.');
                return self::FAILURE;
            }

            try {
                $cabecera = fgetcsv($archivo, 0, ';', '"', '\\');
                if (is_array($cabecera)) {
                    $cabecera[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cabecera[0]);
                }
                if ($cabecera !== ['fila_origen', 'codigo', 'descripcion', 'unidad_medida_codigo']) {
                    $this->error('Cabecera CSV incorrecta. Usa el archivo entregado con separador punto y coma.');
                    return self::FAILURE;
                }

                $filas = [];
                $codigos = [];
                $descripciones = [];
                $errores = [];
                $numero = 1;
                while (($row = fgetcsv($archivo, 0, ';', '"', '\\')) !== false) {
                    $numero++;
                    if (count($row) !== 4) {
                        $errores[] = "Fila CSV {$numero}: se esperaban cuatro columnas.";
                        continue;
                    }
                    [$origen, $codigo, $descripcion, $unidad] = array_map('trim', $row);
                    $codigo = mb_strtoupper($codigo);
                    $unidad = mb_strtoupper($unidad);
                    $descripcion = preg_replace('/\s+/u', ' ', $descripcion);
                    $claveDescripcion = mb_strtoupper($descripcion);
                    $registroUnidad = $unidades->get($unidad);
                    if (! ctype_digit($origen) || $codigo === '' || mb_strlen($codigo) > 50
                        || $descripcion === '' || mb_strlen($descripcion) > 500) {
                        $errores[] = "Fila CSV {$numero}: origen, código o descripción inválido.";
                    }
                    if (isset($codigos[$codigo])) {
                        $errores[] = "Código repetido {$codigo}: filas CSV {$codigos[$codigo]} y {$numero}.";
                    }
                    if (isset($descripciones[$claveDescripcion])) {
                        $errores[] = "Descripción repetida: filas CSV {$descripciones[$claveDescripcion]} y {$numero}.";
                    }
                    if (! $registroUnidad || ! (bool) $registroUnidad->estado) {
                        $errores[] = "Fila CSV {$numero}: unidad {$unidad} no existe o está inactiva.";
                    }
                    $codigos[$codigo] = $numero;
                    $descripciones[$claveDescripcion] = $numero;
                    $filas[] = [
                        'unidad_medida_id' => $registroUnidad?->id,
                        'marca_principal_id' => null,
                        'codigo' => $codigo,
                        'descripcion' => $descripcion,
                        'permite_fraccionamiento' => in_array($unidad, ['MTS', 'KGM', 'GLN'], true),
                        'estado' => true,
                    ];
                }
            } finally {
                fclose($archivo);
            }

            if (count($filas) !== self::TOTAL) {
                $errores[] = 'El archivo tiene '.count($filas).' productos; se esperaban '.self::TOTAL.'.';
            }
            if ($errores !== []) {
                foreach (array_slice($errores, 0, 30) as $error) {
                    $this->error($error);
                }
                $this->error('Se encontraron '.count($errores).' errores. No se guardó ningún producto.');
                return self::FAILURE;
            }

            $this->info('Base: '.$base.' | productos existentes: 0');
            $this->line('CSV validado: '.count($filas).' productos; código, descripción y unidad confirmados.');
            $this->line('Fraccionamiento habilitado por unidad: MTS, KGM y GLN.');
            $this->line('No se cargan stock, repisas, precios, costos, marcas ni presentaciones.');
            if (! $this->option('aplicar')) {
                $this->comment('Vista previa completa. Repite con --aplicar para crear el catálogo.');
                return self::SUCCESS;
            }

            DB::transaction(function () use ($filas): void {
                if (DB::table('productos')->exists()) {
                    throw new \RuntimeException('Aparecieron productos antes de aplicar la carga.');
                }
                $fecha = now();
                foreach (array_chunk($filas, 200) as $grupo) {
                    foreach ($grupo as &$fila) {
                        $fila['created_at'] = $fecha;
                        $fila['updated_at'] = $fecha;
                    }
                    unset($fila);
                    DB::table('productos')->insert($grupo);
                }
                if ((int) DB::table('productos')->count() !== self::TOTAL) {
                    throw new \RuntimeException('La cantidad final no coincide; se revierte la transacción.');
                }
            });

            $this->info(self::TOTAL.' productos creados en '.self::BASE.'.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Carga cancelada; no se confirmó una importación parcial: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
