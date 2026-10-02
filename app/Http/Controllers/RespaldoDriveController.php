<?php

namespace App\Http\Controllers;

use App\Models\DocumentoDrive;
use App\Models\DriveConexion;
use App\Models\FacturaProveedor;
use App\Models\Cotizacion;
use App\Models\ImportacionCotizacionProveedor;
use App\Models\OrdenOperacion;
use App\Services\Documentos\GoogleDriveRespaldoService;
use App\Services\Ordenes\ExportarGastoRealOrdenService;
use App\Services\Ordenes\GastoRealOrdenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RespaldoDriveController extends Controller
{
    public function index(GoogleDriveRespaldoService $drive): View
    {
        $facturas = FacturaProveedor::query()->whereNotNull('archivo_original_path')
            ->with('proveedor')->latest('id')->paginate(15)->withQueryString();
        $respaldos = DocumentoDrive::query()->where('tipo', 'FACTURA_PROVEEDOR')
            ->whereIn('origen_id', $facturas->getCollection()->pluck('id'))
            ->get()->keyBy('origen_id');
        $cotizaciones = Cotizacion::query()->where(function ($query): void {
            $query->whereNotNull('archivo_original_path')
                ->orWhereHas('importacionAsistida', fn ($importacion) => $importacion
                    ->where('estado', 'CONFIRMADA')->whereNotNull('ruta_archivo'));
        })->with('proveedor')->latest('id')->paginate(15, ['*'], 'cotizaciones_page')->withQueryString();
        $respaldosCotizaciones = DocumentoDrive::query()->where('tipo', 'COTIZACION_PROVEEDOR')
            ->whereIn('origen_id', $cotizaciones->getCollection()->pluck('id'))
            ->get()->keyBy('origen_id');
        $ordenes = OrdenOperacion::query()->whereNull('orden_padre_id')->where('estado', 'CERRADA')
            ->whereHas('tipoOrden', fn ($tipo) => $tipo->whereIn('codigo', ['OM', 'OP', 'OS']))
            ->latest('id')->paginate(15, ['*'], 'ordenes_page')->withQueryString();
        $respaldosOrdenes = DocumentoDrive::query()->where('tipo', 'GASTO_REAL_ORDEN')
            ->whereIn('origen_id', $ordenes->getCollection()->pluck('id'))
            ->where('estado', 'COMPLETO')->latest('id')->get()->unique('origen_id')->keyBy('origen_id');

        return view('integraciones.drive', [
            'configurado' => $drive->configurado(),
            'conectado' => DriveConexion::query()->exists(),
            'facturas' => $facturas,
            'respaldos' => $respaldos,
            'cotizaciones' => $cotizaciones,
            'respaldosCotizaciones' => $respaldosCotizaciones,
            'ordenes' => $ordenes,
            'respaldosOrdenes' => $respaldosOrdenes,
        ]);
    }

    public function conectar(Request $request, GoogleDriveRespaldoService $drive): RedirectResponse
    {
        if (! $drive->configurado()) {
            return back()->with('error', 'Configura primero las credenciales OAuth de Google Drive.');
        }

        $estado = Str::random(48);
        $request->session()->put('hidroil_drive_estado', $estado);

        return redirect()->away($drive->urlAutorizacion($estado, route('drive.callback')));
    }

    public function callback(Request $request, GoogleDriveRespaldoService $drive): RedirectResponse
    {
        $esperado = $request->session()->pull('hidroil_drive_estado');
        $recibido = $request->query('state');
        if (! is_string($esperado) || ! is_string($recibido) || ! hash_equals($esperado, $recibido)) {
            abort(403, 'La autorización de Google Drive no coincide con la sesión.');
        }

        if ($request->filled('error')) {
            return redirect()->route('drive.index')->with('error', 'No se autorizó la conexión con Google Drive.');
        }

        $codigo = $request->query('code');
        if (! is_string($codigo) || $codigo === '') {
            return redirect()->route('drive.index')->with('error', 'Google no entregó un código de autorización.');
        }

        try {
            $token = $drive->intercambiarCodigo($codigo, route('drive.callback'));
            DriveConexion::query()->updateOrCreate(['id' => 1], [
                'refresh_token' => $token,
                'conectado_por' => $request->user()->id,
            ]);
        } catch (Throwable $error) {
            report($error);

            return redirect()->route('drive.index')->with('error', $error instanceof RuntimeException
                ? $error->getMessage() : 'No se pudo guardar la conexión con Google Drive.');
        }

        return redirect()->route('drive.index')->with('success', 'Google Drive conectado. Ya puedes respaldar comprobantes.');
    }

    public function subirFactura(
        Request $request,
        FacturaProveedor $facturaProveedor,
        GoogleDriveRespaldoService $drive
    ): RedirectResponse {
        abort_unless($facturaProveedor->tieneArchivoOriginal(), 404);

        return $this->subirArchivo($request, $drive, 'FACTURA_PROVEEDOR', $facturaProveedor->id,
            $facturaProveedor->archivo_original_path,
            'HIDROIL-'.$facturaProveedor->id.'-'.$facturaProveedor->archivo_original_nombre,
            $facturaProveedor->archivo_original_mime ?: 'application/pdf',
            $facturaProveedor->archivo_original_hash);
    }

    public function subirCotizacion(Request $request, Cotizacion $cotizacion, GoogleDriveRespaldoService $drive): RedirectResponse
    {
        $ruta = $cotizacion->archivo_original_path;
        $nombre = $cotizacion->archivo_original_nombre;
        $mime = null;
        if (! $ruta) {
            $importacion = ImportacionCotizacionProveedor::query()
                ->where('cotizacion_id', $cotizacion->id)->where('estado', 'CONFIRMADA')
                ->latest('id')->first();
            $ruta = $importacion?->ruta_archivo;
            $nombre = $importacion?->nombre_original;
            $mime = $importacion?->mime_type;
        }
        abort_unless($ruta, 404);

        $extension = strtolower(pathinfo($nombre ?: $ruta, PATHINFO_EXTENSION));
        $mime ??= match ($extension) {
            'pdf' => 'application/pdf', 'csv' => 'text/csv',
            'xls' => 'application/vnd.ms-excel',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        return $this->subirArchivo($request, $drive, 'COTIZACION_PROVEEDOR', $cotizacion->id,
            $ruta, 'HIDROIL-'.$cotizacion->codigo.'-'.($nombre ?: basename($ruta)), $mime);
    }

    public function subirGastoReal(
        Request $request, OrdenOperacion $ordenOperacion, GoogleDriveRespaldoService $drive,
        GastoRealOrdenService $servicio, ExportarGastoRealOrdenService $exportador
    ): RedirectResponse {
        abort_unless($ordenOperacion->orden_padre_id === null && $ordenOperacion->estado === 'CERRADA'
            && in_array($ordenOperacion->tipoOrden?->codigo, ['OM', 'OP', 'OS'], true), 404);
        $temporal = tempnam(sys_get_temp_dir(), 'hidroil_drive_');
        if ($temporal === false) {
            return redirect()->route('drive.index')->with('error', 'No se pudo preparar el Excel para respaldo.');
        }

        try {
            $libro = $exportador->libro($servicio->construir($ordenOperacion));
            try {
                (new Xlsx($libro))->save($temporal);
            } finally {
                $libro->disconnectWorksheets();
            }

            return $this->subirArchivo($request, $drive, 'GASTO_REAL_ORDEN', $ordenOperacion->id,
                $temporal, 'HIDROIL-GASTO-REAL-'.$ordenOperacion->codigo_orden.'-'.now()->format('Ymd-His').'.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        } catch (Throwable $error) {
            report($error);

            return redirect()->route('drive.index')->with('error', 'No se pudo generar el Excel de gasto real.');
        } finally {
            @unlink($temporal);
        }
    }

    public function descargarDocumento(
        DocumentoDrive $documentoDrive, GoogleDriveRespaldoService $drive
    ): StreamedResponse|RedirectResponse {
        abort_unless($documentoDrive->estado === 'COMPLETO' && filled($documentoDrive->drive_id), 404);

        try {
            $archivo = $drive->descargar($documentoDrive->drive_id);
            if (! hash_equals(strtolower($documentoDrive->hash_sha256), hash('sha256', $archivo['contenido']))) {
                throw new RuntimeException('La copia de Drive no coincide con el archivo respaldado. No se entregó.');
            }
        } catch (Throwable $error) {
            report($error);

            return redirect()->route('drive.index')->with('error', $error instanceof RuntimeException
                ? $error->getMessage() : 'No se pudo recuperar la copia de Google Drive.');
        }

        return response()->streamDownload(static function () use ($archivo): void {
            echo $archivo['contenido'];
        }, $archivo['nombre'], [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function subirArchivo(
        Request $request, GoogleDriveRespaldoService $drive, string $tipo, int $origenId,
        string $rutaLocal, string $nombre, string $mime, ?string $hashEsperado = null
    ): RedirectResponse {
        $ruta = in_array($tipo, ['FACTURA_PROVEEDOR', 'COTIZACION_PROVEEDOR'], true)
            ? (Storage::disk('local')->exists($rutaLocal) ? Storage::disk('local')->path($rutaLocal) : null)
            : $rutaLocal;
        if (! $ruta || ! is_file($ruta)) {
            return redirect()->route('drive.index')->with('error', 'El archivo local no está disponible.');
        }
        $hash = hash_file('sha256', $ruta);
        if (! $hash || ($hashEsperado && ! hash_equals(strtolower($hashEsperado), $hash))) {
            return redirect()->route('drive.index')->with('error', 'El archivo local no coincide con su registro.');
        }
        if (! $drive->configurado() || ! DriveConexion::query()->exists()) {
            return redirect()->route('drive.index')->with('error', 'Conecta Google Drive antes de subir documentos.');
        }

        $registro = DocumentoDrive::query()->firstOrCreate([
            'tipo' => $tipo, 'origen_id' => $origenId, 'hash_sha256' => $hash,
        ], ['estado' => 'PENDIENTE', 'registrado_por' => $request->user()->id]);
        $reservado = DB::transaction(function () use ($registro): bool {
            $registro = DocumentoDrive::query()->lockForUpdate()->findOrFail($registro->id);
            if ($registro->estado === 'COMPLETO'
                || ($registro->estado === 'SUBIENDO' && $registro->updated_at->gt(now()->subMinutes(10)))) {
                return false;
            }
            $registro->update(['estado' => 'SUBIENDO']);

            return true;
        });
        if (! $reservado) {
            return redirect()->route('drive.index')->with('info', 'El archivo ya está respaldado o se está subiendo.');
        }

        try {
            $resultado = $drive->subir($ruta, $nombre, $mime);
            $registro->update([
                'estado' => 'COMPLETO', 'drive_id' => $resultado['id'], 'drive_url' => $resultado['url'],
            ]);
        } catch (Throwable $error) {
            $registro->update(['estado' => 'ERROR']);
            report($error);

            return redirect()->route('drive.index')->with('error', $error instanceof RuntimeException
                ? $error->getMessage() : 'No se pudo subir el archivo a Google Drive.');
        }

        return redirect()->route('drive.index')->with('success', 'Archivo respaldado en Google Drive.');
    }
}
