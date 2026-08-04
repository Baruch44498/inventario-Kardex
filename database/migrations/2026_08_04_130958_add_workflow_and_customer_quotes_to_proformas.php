<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->cambiarNulabilidadCliente(true);

        Schema::table('proformas', function (Blueprint $table): void {
            $table->string('tipo_origen', 30)
                ->default('VENTA_DIRECTA')
                ->after('codigo');
            $table->foreignId('enviado_por')
                ->nullable()
                ->after('emitido_en')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('enviado_en')
                ->nullable()
                ->after('enviado_por');
            $table->foreignId('anulado_por')
                ->nullable()
                ->after('enviado_en')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('anulado_en')
                ->nullable()
                ->after('anulado_por');
            $table->string('motivo_anulacion', 500)
                ->nullable()
                ->after('anulado_en');
            $table->index(['tipo_origen', 'estado']);
        });

        DB::table('proformas')
            ->whereNotIn('estado', [
                'BORRADOR',
                'ENVIADA_A_LOGISTICA',
                'COTIZADA',
                'CONVERTIDA_EN_ORDEN',
                'ANULADA',
            ])
            ->update(['estado' => 'BORRADOR']);

        Schema::create('cotizaciones_cliente', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('proforma_id')
                ->constrained('proformas')
                ->restrictOnDelete();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete();
            $table->string('codigo_base', 30);
            $table->unsignedInteger('version')->default(1);
            $table->string('codigo', 45)->unique();
            $table->string('cliente_documento', 40)->nullable();
            $table->string('cliente_nombre', 250);
            $table->date('fecha_emision');
            $table->date('fecha_validez')->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 14, 6)->nullable();
            $table->decimal('margen_cliente_porcentaje', 7, 4)->default(0);
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('impuesto', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->string('condiciones_pago', 500)->nullable();
            $table->string('condiciones_entrega', 500)->nullable();
            $table->string('observacion', 500)->nullable();
            $table->string('estado', 30)->default('ABIERTA');
            $table->foreignId('cotizado_por')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('cerrado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cerrado_en')->nullable();
            $table->foreignId('anulado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->foreignId('orden_operacion_id')
                ->nullable()
                ->constrained('ordenes_operacion')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['proforma_id', 'version'],
                'uq_cotizacion_cliente_proforma_version'
            );
            $table->index(['codigo_base', 'version']);
            $table->index(['estado', 'fecha_emision']);
            $table->index(['cliente_id', 'fecha_emision']);
        });

        Schema::create('cotizacion_cliente_detalles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cotizacion_cliente_id')
                ->constrained('cotizaciones_cliente')
                ->cascadeOnDelete();
            $table->foreignId('proforma_detalle_id')
                ->nullable()
                ->constrained('proforma_detalles')
                ->nullOnDelete();
            $table->foreignId('producto_id')
                ->constrained('productos')
                ->restrictOnDelete();
            $table->string('codigo_producto', 80);
            $table->string('descripcion', 500);
            $table->string('unidad_medida', 30)->nullable();
            $table->decimal('cantidad', 14, 3);
            $table->decimal('costo_referencia', 14, 4)->nullable();
            $table->decimal('margen_sugerido', 7, 4)->default(0);
            $table->decimal('precio_sugerido', 14, 4)->nullable();
            $table->decimal('precio_unitario', 14, 4)->default(0);
            $table->string('igv_modo', 20)->default('AGREGAR');
            $table->decimal('igv_porcentaje', 7, 4)->default(18);
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('impuesto', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->string('observacion', 300)->nullable();
            $table->timestamps();

            $table->unique(
                ['cotizacion_cliente_id', 'producto_id'],
                'uq_cotizacion_cliente_producto'
            );
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_cliente_detalles');
        Schema::dropIfExists('cotizaciones_cliente');

        Schema::table('proformas', function (Blueprint $table): void {
            $table->dropIndex(['tipo_origen', 'estado']);
            $table->dropConstrainedForeignId('enviado_por');
            $table->dropConstrainedForeignId('anulado_por');
            $table->dropColumn([
                'tipo_origen',
                'enviado_en',
                'anulado_en',
                'motivo_anulacion',
            ]);
        });

        if (DB::table('proformas')->whereNull('cliente_id')->exists()) {
            throw new RuntimeException(
                'No se puede revertir mientras existan proformas sin cliente.'
            );
        }

        $this->cambiarNulabilidadCliente(false);
    }

    private function cambiarNulabilidadCliente(bool $nullable): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';

            DB::statement(
                "ALTER TABLE `proformas` MODIFY `cliente_id` BIGINT UNSIGNED {$nullSql}"
            );

            return;
        }

        Schema::table('proformas', function (Blueprint $table) use ($nullable): void {
            $column = $table->unsignedBigInteger('cliente_id');

            $nullable
                ? $column->nullable()->change()
                : $column->nullable(false)->change();
        });
    }
};
