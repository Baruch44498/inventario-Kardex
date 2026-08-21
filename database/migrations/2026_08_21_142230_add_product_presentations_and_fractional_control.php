<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->boolean('permite_fraccionamiento')
                ->default(false)
                ->after('descripcion');
        });

        DB::table('productos')
            ->whereIn('unidad_medida_id', function ($query): void {
                $query->select('id')
                    ->from('unidades_medida')
                    ->whereIn('codigo', ['M', 'KG', 'LT']);
            })
            ->update(['permite_fraccionamiento' => true]);

        Schema::create('producto_presentaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->cascadeOnDelete();
            $table->string('nombre', 80);
            $table->decimal('factor_conversion', 14, 3);
            $table->boolean('es_predeterminada')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['producto_id', 'nombre'], 'uq_producto_presentacion_nombre');
            $table->index(['producto_id', 'estado']);
        });

        Schema::table('cotizacion_detalles', function (Blueprint $table): void {
            $table->foreignId('producto_presentacion_id')
                ->nullable()
                ->after('producto_id')
                ->constrained('producto_presentaciones')
                ->nullOnDelete();
            $table->string('presentacion_nombre', 80)
                ->nullable()
                ->after('producto_presentacion_id');
            $table->decimal('cantidad_presentacion', 14, 3)
                ->nullable()
                ->after('presentacion_nombre');
            $table->decimal('factor_conversion', 14, 3)
                ->default(1)
                ->after('cantidad_presentacion');
            $table->decimal('precio_presentacion', 14, 4)
                ->nullable()
                ->after('factor_conversion');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_detalles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('producto_presentacion_id');
            $table->dropColumn([
                'presentacion_nombre',
                'cantidad_presentacion',
                'factor_conversion',
                'precio_presentacion',
            ]);
        });

        Schema::dropIfExists('producto_presentaciones');

        Schema::table('productos', function (Blueprint $table): void {
            $table->dropColumn('permite_fraccionamiento');
        });
    }
};
