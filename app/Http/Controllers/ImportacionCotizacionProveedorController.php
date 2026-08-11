<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\ImportacionCotizacionProveedor;
use App\Models\Proveedor;
use App\Models\Requisicion;
use App\Services\Compras\Importacion\ImportarCotizacionProveedorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportacionCotizacionProveedorController extends Controller
{
    public function create(Request $request): View
    {
        $data = $request->validate([
            'requisicion_id' => [
                'required',
                'integer',
                Rule::exists('requisiciones', 'id')->where(
                    fn($query) => $query->whereIn('estado', ['ENVIADA', 'EN_REVISION', 'COTIZANDO'])
                ),
            ],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')],
        ]);

        $requisicion = Requisicion::query()
            ->with(['detalles.producto.unidadMedida'])
            ->findOrFail($data['requisicion_id']);
        $proveedor = ! empty($data['proveedor_id'])
            ? Proveedor::query()->find($data['proveedor_id'])
            : null;

        return view('cotizaciones_proveedor.importacion.create', compact('requisicion', 'proveedor'));
    }

    public function store(
        Request $request,
        ImportarCotizacionProveedorService $importador
    ): RedirectResponse {
        $data = $request->validate([
            'requisicion_id' => [
                'required',
                'integer',
                Rule::exists('requisiciones', 'id')->where(
                    fn($query) => $query->whereIn('estado', ['ENVIADA', 'EN_REVISION', 'COTIZANDO'])
                ),
            ],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')],
            'documento' => [
                'required',
                'file',
                'max:15360',
                'mimes:xlsx,xls,csv,pdf',
            ],
        ], [
            'documento.required' => 'Selecciona el documento enviado por el proveedor.',
            'documento.mimes' => 'Usa un archivo Excel (.xlsx, .xls, .csv) o un PDF digital.',
            'documento.max' => 'El documento no puede superar 15 MB.',
        ]);

        $requisicion = Requisicion::query()
            ->with(['detalles.producto.unidadMedida'])
            ->findOrFail($data['requisicion_id']);
        $archivo = $request->file('documento');
        $extension = mb_strtolower($archivo->getClientOriginalExtension());
        $nombre = Str::uuid()->toString() . '.' . $extension;
        $ruta = $archivo->storeAs(
            'cotizaciones-proveedor/importaciones/' . now()->format('Y/m'),
            $nombre,
            'local'
        );

        try {
            $resultado = $importador->procesar(
                Storage::disk('local')->path($ruta),
                $extension,
                $requisicion
            );
        } catch (RuntimeException $exception) {
            Storage::disk('local')->delete($ruta);
            throw ValidationException::withMessages([
                'documento' => $exception->getMessage(),
            ]);
        }

        $proveedorDetectadoId = (int) ($resultado['cabecera']['proveedor_id_detectado'] ?? 0);
        $proveedorId = ! empty($data['proveedor_id'])
            ? (int) $data['proveedor_id']
            : ($proveedorDetectadoId > 0 ? $proveedorDetectadoId : null);

        $importacion = ImportacionCotizacionProveedor::query()->create([
            'requisicion_id' => $requisicion->id,
            'proveedor_id' => $proveedorId,
            'tipo_archivo' => $extension === 'pdf' ? 'PDF' : 'EXCEL',
            'nombre_original' => $archivo->getClientOriginalName(),
            'ruta_archivo' => $ruta,
            'mime_type' => $archivo->getClientMimeType(),
            'datos_extraidos' => $resultado,
            'advertencias' => $resultado['advertencias'] ?? [],
            'estado' => 'BORRADOR',
            'creado_por' => $request->user()->id,
        ]);

        return redirect()
            ->route('cotizaciones-proveedor.create', [
                'requisicion_id' => $requisicion->id,
                'proveedor_id' => $proveedorId,
                'importacion_id' => $importacion->id,
            ])
            ->withInput($this->prefill($importacion))
            ->with('success', 'Documento procesado. Revisa y corrige los datos antes de confirmar la cotización.');
    }

    public function destroy(Request $request, ImportacionCotizacionProveedor $importacion): RedirectResponse
    {
        // Primero verificamos que el usuario pertenezca al ámbito general de
        // Compras. Esto evita revelar información del documento a usuarios
        // que no deberían poder interactuar con importaciones de proveedor.
        $this->autorizarAcceso($request, $importacion);

        // Después aplicamos la regla de negocio del estado. Un usuario de
        // Compras autorizado debe recibir una explicación correcta cuando la
        // importación ya fue confirmada o descartada, aunque no sea su creador.
        if (! $importacion->esBorrador()) {
            $mensaje = match ($importacion->estado) {
                'CONFIRMADA' => 'La importación ya fue confirmada y está vinculada a una cotización. No puede descartarse.',
                'DESCARTADA' => 'La importación ya fue descartada anteriormente.',
                default => 'Solo las importaciones en borrador pueden descartarse.',
            };

            return redirect()
                ->route('requerimientos-compra.show', $importacion->requisicion_id)
                ->with('error', $mensaje);
        }

        // Solo para un BORRADOR tiene sentido validar quién puede modificarlo
        // o descartarlo. El creador y el Administrador conservan esa facultad.
        $this->autorizarBorrador($request, $importacion);

        if ($importacion->ruta_archivo) {
            Storage::disk('local')->delete($importacion->ruta_archivo);
        }

        $importacion->update(['estado' => 'DESCARTADA']);

        return redirect()
            ->route('requerimientos-compra.show', $importacion->requisicion_id)
            ->with('success', 'Importación descartada. No se creó ninguna cotización.');
    }

    public function descargarOriginal(Cotizacion $cotizacion): StreamedResponse|RedirectResponse
    {
        $ruta = $cotizacion->archivo_original_path;
        $nombre = $cotizacion->archivo_original_nombre;

        // Compatibilidad con cotizaciones importadas que pudieron quedar
        // confirmadas antes de persistir la referencia del archivo en la
        // cabecera de la cotización. La importación es la fuente original
        // y mantiene la ruta privada del documento recibido.
        if (! $ruta) {
            $importacion = ImportacionCotizacionProveedor::query()
                ->where('cotizacion_id', $cotizacion->id)
                ->where('estado', 'CONFIRMADA')
                ->latest('id')
                ->first();

            if ($importacion) {
                $ruta = $importacion->ruta_archivo;
                $nombre = $importacion->nombre_original;
            }
        }

        if (! $ruta || ! Storage::disk('local')->exists($ruta)) {
            return back()->with('error', 'El documento original no está disponible.');
        }

        return Storage::disk('local')->download(
            $ruta,
            $nombre ?: basename($ruta)
        );
    }

    private function prefill(ImportacionCotizacionProveedor $importacion): array
    {
        $datos = $importacion->datos_extraidos;
        $cabecera = $datos['cabecera'] ?? [];

        return [
            'importacion_cotizacion_id' => $importacion->id,
            'requisicion_id' => $importacion->requisicion_id,
            'proveedor_id' => $importacion->proveedor_id,
            'numero_documento' => $cabecera['numero_documento'] ?? null,
            'fecha_cotizacion' => $cabecera['fecha_cotizacion'] ?? now()->toDateString(),
            'fecha_validez' => null,
            'moneda' => $cabecera['moneda'] ?? 'PEN',
            'tipo_cambio' => null,
            'descuento_global_modo' => 'SIN_DESCUENTO',
            'descuento_global_tipo' => null,
            'descuento_global_valor' => null,
            'condiciones_pago' => $cabecera['condiciones_pago'] ?? null,
            'condiciones_entrega' => $cabecera['condiciones_entrega'] ?? null,
            'observacion' => 'Importación asistida desde ' . $importacion->nombre_original . '. Revisada por el usuario antes del registro.',
            'detalles' => collect($datos['detalles'] ?? [])->map(fn(array $linea): array => [
                'requisicion_detalle_id' => $linea['requisicion_detalle_id'] ?? null,
                'producto_id' => $linea['producto_id'] ?? null,
                'cantidad' => $linea['cantidad'] ?? 1,
                'precio_unitario' => $linea['precio_unitario'] ?? '',
                'descuento_modo' => $linea['descuento_modo'] ?? 'SIN_DESCUENTO',
                'descuento_tipo' => $linea['descuento_tipo'] ?? '',
                'descuento_valor' => $linea['descuento_valor'] ?? '',
                'igv_modo' => $linea['igv_modo'] ?? ($cabecera['igv_modo_sugerido'] ?? 'AGREGAR'),
                'marca_ofertada' => $linea['marca_ofertada'] ?? '',
                'observacion' => $linea['observacion'] ?? '',
                'codigo_importado' => $linea['codigo_documento'] ?? null,
                'descripcion_importada' => $linea['descripcion_documento'] ?? null,
                'coincidencia_importada' => $linea['coincidencia'] ?? null,
            ])->values()->all(),
        ];
    }

    private function autorizarAcceso(Request $request, ImportacionCotizacionProveedor $importacion): void
    {
        $usuario = $request->user();

        if ($usuario && ($usuario->esAdministrador() || $usuario->puede('compras.gestionar'))) {
            return;
        }

        abort(403);
    }

    private function autorizarBorrador(Request $request, ImportacionCotizacionProveedor $importacion): void
    {
        if ($importacion->creado_por === $request->user()->id || $request->user()->esAdministrador()) {
            return;
        }

        abort(403);
    }
}
