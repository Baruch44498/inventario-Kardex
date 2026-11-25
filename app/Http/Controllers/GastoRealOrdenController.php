<?php

namespace App\Http\Controllers;

use App\Models\OrdenOperacion;
use App\Services\Ordenes\ExportarGastoRealOrdenService;
use App\Services\Ordenes\GastoRealOrdenService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GastoRealOrdenController extends Controller
{
    public function show(OrdenOperacion $ordenOperacion, GastoRealOrdenService $servicio, ExportarGastoRealOrdenService $exportador)
    {
        abort_unless(in_array($ordenOperacion->tipoOrden?->codigo, ['OP', 'OM', 'OS'], true), 404);
        $reporte = $servicio->construir($ordenOperacion);
        return response()->view('ordenes_operacion.gasto_real', [
            'orden' => $ordenOperacion, 'reporte' => $reporte, 'secciones' => $exportador->secciones($reporte),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function excel(OrdenOperacion $ordenOperacion, GastoRealOrdenService $servicio, ExportarGastoRealOrdenService $exportador)
    {
        abort_unless(in_array($ordenOperacion->tipoOrden?->codigo, ['OP', 'OM', 'OS'], true), 404);
        $libro = $exportador->libro($servicio->construir($ordenOperacion));
        return response()->streamDownload(function () use ($libro) {
            try {
                (new Xlsx($libro))->save('php://output');
            } finally {
                $libro->disconnectWorksheets();
            }
        }, 'GASTO_REAL_ORDEN_'.$ordenOperacion->id.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
