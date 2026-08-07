<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->expandirNotasSalida();
        $this->expandirDetallesSalida();
        $this->expandirNotasIngreso();
        $this->expandirDetallesIngreso();
        $this->vincularReposicionesConIngreso();
    }

    private function expandirNotasSalida(): void
    {
        if (! Schema::hasColumn('notas_salida', 'motivo_salida')) {
            Schema::table('notas_salida', function (Blueprint $table): void {
                $table->foreignId('orden_operacion_id')->nullable()->change();
                $table->string('motivo_salida', 30)
                    ->default('ORDEN_OPERACION')
                    ->after('orden_operacion_id');
            });
        }

        if (! Schema::hasColumn('notas_salida', 'proforma_id')) {
            Schema::table('notas_salida', function (Blueprint $table): void {
                $table->foreignId('proforma_id')
                    ->nullable()
                    ->after('motivo_salida')
                    ->constrained('proformas')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasIndex('notas_salida', 'idx_notas_salida_motivo_estado')) {
            Schema::table('notas_salida', function (Blueprint $table): void {
                $table->index(['motivo_salida', 'estado'], 'idx_notas_salida_motivo_estado');
            });
        }
    }

    private function expandirDetallesSalida(): void
    {
        // MySQL necesita un índice cuyo primer campo sea nota_salida_id para
        // respaldar la FK. El UNIQUE antiguo era el único que cumplía esa función.
        // Creamos primero un índice de soporte y recién después soltamos el UNIQUE.
        if (Schema::hasIndex('nota_salida_detalles', 'uq_nota_salida_producto_repisa')) {
            if (! Schema::hasIndex('nota_salida_detalles', 'idx_nota_salida_id_fk_support')) {
                Schema::table('nota_salida_detalles', function (Blueprint $table): void {
                    $table->index('nota_salida_id', 'idx_nota_salida_id_fk_support');
                });
            }

            Schema::table('nota_salida_detalles', function (Blueprint $table): void {
                $table->dropUnique('uq_nota_salida_producto_repisa');
            });
        }

        if (! Schema::hasColumn('nota_salida_detalles', 'proforma_detalle_id')) {
            Schema::table('nota_salida_detalles', function (Blueprint $table): void {
                $table->foreignId('proforma_detalle_id')
                    ->nullable()
                    ->after('nota_salida_id')
                    ->constrained('proforma_detalles')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('nota_salida_detalles', 'tratamiento')) {
            Schema::table('nota_salida_detalles', function (Blueprint $table): void {
                $table->string('tratamiento', 30)
                    ->default('CONSUMO')
                    ->after('cantidad');
            });
        }

        if (! Schema::hasIndex('nota_salida_detalles', 'idx_nota_salida_producto_repisa')) {
            Schema::table('nota_salida_detalles', function (Blueprint $table): void {
                $table->index(
                    ['nota_salida_id', 'producto_id', 'repisa_id'],
                    'idx_nota_salida_producto_repisa'
                );
            });
        }

        if (! Schema::hasIndex('nota_salida_detalles', 'idx_salida_proforma_tratamiento')) {
            Schema::table('nota_salida_detalles', function (Blueprint $table): void {
                $table->index(
                    ['proforma_detalle_id', 'tratamiento'],
                    'idx_salida_proforma_tratamiento'
                );
            });
        }
    }

    private function expandirNotasIngreso(): void
    {
        if (! Schema::hasColumn('notas_ingreso', 'motivo_ingreso')) {
            Schema::table('notas_ingreso', function (Blueprint $table): void {
                $table->foreignId('orden_compra_id')->nullable()->change();
                $table->string('motivo_ingreso', 30)
                    ->default('COMPRA')
                    ->after('factura_proveedor_id');
            });
        }

        if (! Schema::hasColumn('notas_ingreso', 'nota_salida_id')) {
            Schema::table('notas_ingreso', function (Blueprint $table): void {
                $table->foreignId('nota_salida_id')
                    ->nullable()
                    ->after('motivo_ingreso')
                    ->constrained('notas_salida')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('notas_ingreso', 'proforma_id')) {
            Schema::table('notas_ingreso', function (Blueprint $table): void {
                $table->foreignId('proforma_id')
                    ->nullable()
                    ->after('nota_salida_id')
                    ->constrained('proformas')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasIndex('notas_ingreso', 'idx_notas_ingreso_motivo_estado')) {
            Schema::table('notas_ingreso', function (Blueprint $table): void {
                $table->index(['motivo_ingreso', 'estado'], 'idx_notas_ingreso_motivo_estado');
            });
        }
    }

    private function expandirDetallesIngreso(): void
    {
        if (! Schema::hasColumn('nota_ingreso_detalles', 'nota_salida_detalle_id')) {
            Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
                $table->foreignId('nota_salida_detalle_id')
                    ->nullable()
                    ->after('orden_compra_detalle_id')
                    ->constrained('nota_salida_detalles')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('nota_ingreso_detalles', 'proforma_detalle_id')) {
            Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
                $table->foreignId('proforma_detalle_id')
                    ->nullable()
                    ->after('nota_salida_detalle_id')
                    ->constrained('proforma_detalles')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasIndex('nota_ingreso_detalles', 'idx_ingreso_salida_detalle')) {
            Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
                $table->index('nota_salida_detalle_id', 'idx_ingreso_salida_detalle');
            });
        }

        if (! Schema::hasIndex('nota_ingreso_detalles', 'idx_ingreso_proforma_detalle')) {
            Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
                $table->index('proforma_detalle_id', 'idx_ingreso_proforma_detalle');
            });
        }
    }

    private function vincularReposicionesConIngreso(): void
    {
        if (! Schema::hasColumn('proforma_prestamo_reposiciones', 'nota_ingreso_detalle_id')) {
            Schema::table('proforma_prestamo_reposiciones', function (Blueprint $table): void {
                $table->foreignId('nota_ingreso_detalle_id')
                    ->nullable()
                    ->after('nota_ingreso_id')
                    ->constrained('nota_ingreso_detalles')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('proforma_prestamo_reposiciones', 'nota_ingreso_detalle_id')) {
            Schema::table('proforma_prestamo_reposiciones', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('nota_ingreso_detalle_id');
            });
        }

        Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
            if (Schema::hasIndex('nota_ingreso_detalles', 'idx_ingreso_salida_detalle')) {
                $table->dropIndex('idx_ingreso_salida_detalle');
            }
            if (Schema::hasIndex('nota_ingreso_detalles', 'idx_ingreso_proforma_detalle')) {
                $table->dropIndex('idx_ingreso_proforma_detalle');
            }
        });

        if (Schema::hasColumn('nota_ingreso_detalles', 'nota_salida_detalle_id')) {
            Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('nota_salida_detalle_id');
            });
        }
        if (Schema::hasColumn('nota_ingreso_detalles', 'proforma_detalle_id')) {
            Schema::table('nota_ingreso_detalles', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('proforma_detalle_id');
            });
        }

        Schema::table('notas_ingreso', function (Blueprint $table): void {
            if (Schema::hasIndex('notas_ingreso', 'idx_notas_ingreso_motivo_estado')) {
                $table->dropIndex('idx_notas_ingreso_motivo_estado');
            }
        });
        if (Schema::hasColumn('notas_ingreso', 'nota_salida_id')) {
            Schema::table('notas_ingreso', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('nota_salida_id');
            });
        }
        if (Schema::hasColumn('notas_ingreso', 'proforma_id')) {
            Schema::table('notas_ingreso', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('proforma_id');
            });
        }
        if (Schema::hasColumn('notas_ingreso', 'motivo_ingreso')) {
            Schema::table('notas_ingreso', function (Blueprint $table): void {
                $table->dropColumn('motivo_ingreso');
                $table->foreignId('orden_compra_id')->nullable(false)->change();
            });
        }

        Schema::table('nota_salida_detalles', function (Blueprint $table): void {
            if (Schema::hasIndex('nota_salida_detalles', 'idx_salida_proforma_tratamiento')) {
                $table->dropIndex('idx_salida_proforma_tratamiento');
            }
        });
        if (Schema::hasColumn('nota_salida_detalles', 'proforma_detalle_id')) {
            Schema::table('nota_salida_detalles', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('proforma_detalle_id');
            });
        }
        if (Schema::hasColumn('nota_salida_detalles', 'tratamiento')) {
            Schema::table('nota_salida_detalles', function (Blueprint $table): void {
                $table->dropColumn('tratamiento');
            });
        }

        if (! Schema::hasIndex('nota_salida_detalles', 'uq_nota_salida_producto_repisa')) {
            Schema::table('nota_salida_detalles', function (Blueprint $table): void {
                $table->unique(
                    ['nota_salida_id', 'producto_id', 'repisa_id'],
                    'uq_nota_salida_producto_repisa'
                );
            });
        }
        Schema::table('nota_salida_detalles', function (Blueprint $table): void {
            if (Schema::hasIndex('nota_salida_detalles', 'idx_nota_salida_producto_repisa')) {
                $table->dropIndex('idx_nota_salida_producto_repisa');
            }
            if (Schema::hasIndex('nota_salida_detalles', 'idx_nota_salida_id_fk_support')) {
                $table->dropIndex('idx_nota_salida_id_fk_support');
            }
        });

        Schema::table('notas_salida', function (Blueprint $table): void {
            if (Schema::hasIndex('notas_salida', 'idx_notas_salida_motivo_estado')) {
                $table->dropIndex('idx_notas_salida_motivo_estado');
            }
        });
        if (Schema::hasColumn('notas_salida', 'proforma_id')) {
            Schema::table('notas_salida', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('proforma_id');
            });
        }
        if (Schema::hasColumn('notas_salida', 'motivo_salida')) {
            Schema::table('notas_salida', function (Blueprint $table): void {
                $table->dropColumn('motivo_salida');
                $table->foreignId('orden_operacion_id')->nullable(false)->change();
            });
        }
    }
};
