<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->decimal('precio_final_pactado', 14, 2)->nullable();
            $table->foreignId('precio_final_ajustado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('precio_final_ajustado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones_cliente', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('precio_final_ajustado_por');
            $table->dropColumn(['precio_final_pactado', 'precio_final_ajustado_en']);
        });
    }
};
