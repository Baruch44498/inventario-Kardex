<?php

namespace App\Http\Controllers;

use App\Models\CotizacionCliente;
use App\Services\Ventas\ExportarCotizacionCosteoExcelService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CotizacionCosteoExcelController extends Controller
{
    public function descargar(CotizacionCliente $cotizacionCliente, ExportarCotizacionCosteoExcelService $exportador)
    {
        abort_unless(in_array($cotizacionCliente->tipoOrden?->codigo, ['OM', 'OS', 'OP'], true), 404);
        $libro = $exportador->libro($cotizacionCliente);
        return response()->streamDownload(function () use ($libro) {
            try {
                (new Xlsx($libro))->save('php://output');
            } finally {
                $libro->disconnectWorksheets();
            }
        }, 'COSTEO_COTIZACION_'.$cotizacionCliente->id.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
