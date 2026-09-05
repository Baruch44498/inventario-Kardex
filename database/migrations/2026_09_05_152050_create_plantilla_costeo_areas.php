<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantilla_costeo_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plantilla_costeo_id')->constrained('plantillas_costeo')->cascadeOnDelete();
            $table->foreignId('area_padre_id')->nullable()->constrained('plantilla_costeo_areas')->nullOnDelete();
            $table->string('nombre', 150);
            $table->unsignedInteger('orden_secuencia');
            $table->timestamps();
        });
        Schema::table('plantilla_costeo_partidas', function (Blueprint $table): void {
            $table->foreignId('plantilla_area_id')->nullable()->constrained('plantilla_costeo_areas')->nullOnDelete();
        });
        DB::table('plantillas_costeo')->orderBy('id')->chunkById(100, function ($plantillas): void {
            foreach ($plantillas as $plantilla) {
                $areas = [];
                $partidas = DB::table('plantilla_costeo_partidas')
                    ->where('plantilla_costeo_id', $plantilla->id)
                    ->whereIn('tipo_costo', ['MATERIAL', 'SERVICIO_TERCERO'])
                    ->orderBy('orden_secuencia')->orderBy('id')->get();
                foreach ($partidas as $partida) {
                    $nombre = trim((string) $partida->grupo_costo);
                    if ($nombre === '' && $partida->tipo_costo !== 'MATERIAL') {
                        continue;
                    }
                    $nombre = $nombre !== '' ? $nombre : 'GENERAL';
                    $clave = Str::upper(Str::ascii(preg_replace('/\s+/u', ' ', $nombre)));
                    if (! isset($areas[$clave])) {
                        $areas[$clave] = DB::table('plantilla_costeo_areas')->insertGetId([
                            'plantilla_costeo_id' => $plantilla->id,
                            'nombre' => $nombre,
                            'orden_secuencia' => count($areas) + 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    DB::table('plantilla_costeo_partidas')->where('id', $partida->id)
                        ->update(['plantilla_area_id' => $areas[$clave]]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('plantilla_costeo_partidas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plantilla_area_id');
        });
        Schema::dropIfExists('plantilla_costeo_areas');
    }
};
