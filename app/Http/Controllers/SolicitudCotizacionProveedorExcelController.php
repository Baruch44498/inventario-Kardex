<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Services\Compras\ExportarSolicitudCotizacionProveedorExcelService;
use App\Services\Compras\SeguimientoAbastecimientoRequerimientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SolicitudCotizacionProveedorExcelController extends Controller
{
    public function __invoke(
        Request $request,
        Requisicion $requerimientoCompra,
        Proveedor $proveedor,
        ExportarSolicitudCotizacionProveedorExcelService $exportador,
        SeguimientoAbastecimientoRequerimientoService $seguimiento
    ): StreamedResponse {
        abort_if($requerimientoCompra->estaAnulada(), 422, 'No se puede solicitar precios desde un requerimiento anulado.');

        $data = $request->validate([
            'detalle_ids' => ['required', 'array', 'min:1', 'max:250'],
            'detalle_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('requisicion_detalles', 'id')
                    ->where('requisicion_id', $requerimientoCompra->id),
            ],
        ]);

        $requerimientoCompra->loadMissing('detalles.producto.unidadMedida');
        $ids = collect($data['detalle_ids'])->map(fn (mixed $id): int => (int) $id)->unique()->values();
        $detalles = $requerimientoCompra->detalles->whereIn('id', $ids)->values();

        if ($detalles->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'detalle_ids' => 'Una de las líneas no pertenece al requerimiento seleccionado.',
            ]);
        }

        $idsPendientes = $seguimiento->construir($requerimientoCompra)['lineas']
            ->where('estado', 'PENDIENTE_COTIZAR')
            ->pluck('requisicion_detalle_id')
            ->map(fn ($id): int => (int) $id);

        if ($ids->diff($idsPendientes)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'detalle_ids' => 'La solicitud solo puede incluir productos pendientes de cotizar.',
            ]);
        }

        $nombreProveedor = Str::upper(Str::slug($proveedor->nombreVisible(), '_'));
        $nombre = 'SOLICITUD_COTIZACION_'.$requerimientoCompra->codigo.'_'.$nombreProveedor.'.xlsx';

        return response()->streamDownload(function () use ($exportador, $requerimientoCompra, $proveedor, $detalles): void {
            $libro = $exportador->libro($requerimientoCompra, $proveedor, $detalles);
            (new Xlsx($libro))->save('php://output');
            $libro->disconnectWorksheets();
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
