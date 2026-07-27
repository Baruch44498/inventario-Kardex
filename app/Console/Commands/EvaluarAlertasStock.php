<?php

namespace App\Console\Commands;

use App\Models\AlertaStock;
use App\Services\Inventario\EvaluarAlertasStockService;
use Illuminate\Console\Command;

class EvaluarAlertasStock extends Command
{
    protected $signature = 'inventario:evaluar-alertas';

    protected $description =
        'Evalúa el stock de todos los inventarios y actualiza sus alertas';

    public function handle(
        EvaluarAlertasStockService $servicio
    ): int {
        $procesados = $servicio->evaluarTodos();

        $activas = AlertaStock::query()
            ->whereIn('estado', ['ACTIVA', 'ATENDIDA'])
            ->count();

        $criticas = AlertaStock::query()
            ->whereIn('estado', ['ACTIVA', 'ATENDIDA'])
            ->where('nivel', 'CRITICA')
            ->count();

        $this->info("Inventarios evaluados: {$procesados}");
        $this->info("Alertas abiertas: {$activas}");
        $this->info("Alertas críticas: {$criticas}");

        return self::SUCCESS;
    }
}
