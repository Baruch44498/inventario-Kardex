<?php

namespace App\Http\Controllers;

use App\Models\Requisicion;
use App\Services\Compras\ExportarRequerimientoCompraExcelService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequerimientoCompraExcelController extends Controller
{
    public function __invoke(
        Request $request,
        Requisicion $requerimientoCompra,
        ExportarRequerimientoCompraExcelService $exportador
    ): StreamedResponse {
        $usuario = $request->user();
        abort_unless(
            $usuario->tieneRol('ALMACEN', 'COMERCIAL_LOGISTICA') || $usuario->esAdministrador(),
            403
        );
        abort_if(
            $requerimientoCompra->esBorrador()
                && ! $usuario->tieneRol('ALMACEN')
                && ! $usuario->esAdministrador(),
            403,
            'Este borrador todavía pertenece a Almacén.'
        );

        $nombre = 'REQUERIMIENTO_COMPRA_'.$requerimientoCompra->codigo.'.xlsx';

        return response()->streamDownload(function () use ($exportador, $requerimientoCompra): void {
            $libro = $exportador->libro($requerimientoCompra);
            (new Xlsx($libro))->save('php://output');
            $libro->disconnectWorksheets();
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
