<?php

namespace App\Http\Controllers;

use App\Models\CotizacionCliente;
use App\Services\Ventas\ExportarCotizacionClienteExcelService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CotizacionExcelClienteController extends Controller
{
    public function descargar(
        CotizacionCliente $cotizacionCliente,
        string $modo,
        ExportarCotizacionClienteExcelService $exportador
    ) {
        $libro = $exportador->libro($cotizacionCliente, $modo);

        return response()->streamDownload(function () use ($libro): void {
            try {
                (new Xlsx($libro))->save('php://output');
            } finally {
                $libro->disconnectWorksheets();
            }
        }, 'COTIZACION_CLIENTE_'.$cotizacionCliente->codigo.'_'.$modo.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
