<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('salidas', function (Blueprint $table) {
        $table->id();
        
        // 1. Relación con el Producto
        $table->foreignId('producto_id')->constrained('productos')->onDelete('restrict');
        
        // 2. Relación con el Cliente o Destino Interno
        $table->foreignId('destino_id')->constrained('cliente_destinos')->onDelete('restrict');
        
        // 3. Los datos transaccionales que mencionaste
        $table->string('tipo_orden', 5); // Aquí guardaremos si es OP, OS, OM, OV
        $table->string('numero_orden', 50); // El código exacto, ej: FS01 o OM-0171-26
        
        // 4. El movimiento numérico
        $table->integer('cantidad');
        
        // 5. Datos adicionales
        $table->string('responsable')->nullable(); // Quien autoriza o retira
        $table->date('fecha_salida');
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas');
    }
};
