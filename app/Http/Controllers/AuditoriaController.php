<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriaController extends Controller
{
    private const ENTIDADES = [
        'User' => 'Usuarios', 'Producto' => 'Productos', 'Proforma' => 'Proformas',
        'CotizacionCliente' => 'Cotizaciones cliente', 'OrdenOperacion' => 'Órdenes de operación',
        'Requisicion' => 'Requerimientos', 'OrdenCompra' => 'Órdenes de compra',
        'NotaIngreso' => 'Notas de ingreso', 'NotaSalida' => 'Notas de salida',
        'InventarioPeriodico' => 'Inventarios físicos', 'MovimientoInventario' => 'Kardex',
        'CostoDirectoOrden' => 'Costos directos', 'FacturaProveedor' => 'Facturas proveedor',
        'PagoFacturaProveedor' => 'Pagos', 'CobroCotizacionCliente' => 'Cobros',
    ];

    public function index(Request $request): View
    {
        return view('auditoria.index', [
            'eventos' => $this->consulta($this->filtros($request))->paginate(30)->withQueryString(),
            'entidades' => self::ENTIDADES,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $eventos = $this->consulta($this->filtros($request));

        return response()->streamDownload(function () use ($eventos): void {
            $salida = fopen('php://output', 'wb');
            if ($salida === false) {
                return;
            }
            try {
                fwrite($salida, "\xEF\xBB\xBF");
                fputcsv($salida, [
                    'Fecha y hora', 'Usuario', 'Módulo', 'Entidad', 'ID registro',
                    'Registro', 'Acción', 'Estado anterior', 'Estado nuevo', 'Campos modificados',
                ], ';', '"', '');
                foreach ($eventos->lazy(500) as $evento) {
                    fputcsv($salida, [
                        $evento->created_at?->format('Y-m-d H:i:s'),
                        $this->textoSeguro($evento->usuario?->username ?? 'Sistema o usuario retirado'),
                        $this->textoSeguro(self::ENTIDADES[$evento->entidad] ?? $evento->entidad),
                        $this->textoSeguro($evento->entidad),
                        $evento->entidad_id,
                        $this->textoSeguro($evento->etiqueta ?: '#'.$evento->entidad_id),
                        $this->textoSeguro($evento->accion),
                        $this->textoSeguro($evento->estado_anterior),
                        $this->textoSeguro($evento->estado_nuevo),
                        $this->textoSeguro(implode(', ', $evento->campos ?? [])),
                    ], ';', '"', '');
                }
            } finally {
                fclose($salida);
            }
        }, 'AUDITORIA_OPERACIONES_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, string> */
    private function filtros(Request $request): array
    {
        $filtros = $request->validate([
            'entidad' => ['nullable', 'in:'.implode(',', array_keys(self::ENTIDADES))],
            'accion' => ['nullable', 'in:CREADO,ACTUALIZADO,ELIMINADO'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (filled($filtros['desde'] ?? null) && filled($filtros['hasta'] ?? null)
            && $filtros['desde'] > $filtros['hasta']) {
            throw ValidationException::withMessages(['hasta' => 'La fecha final debe ser igual o posterior a la inicial.']);
        }

        return $filtros;
    }

    /** @param array<string, string> $filtros @return Builder<AuditoriaEvento> */
    private function consulta(array $filtros): Builder
    {
        $query = AuditoriaEvento::query()->with('usuario:id,username');
        if (filled($filtros['entidad'] ?? null)) {
            $query->where('entidad', $filtros['entidad']);
        }
        if (filled($filtros['accion'] ?? null)) {
            $query->where('accion', $filtros['accion']);
        }
        if (filled($filtros['desde'] ?? null)) {
            $query->whereDate('created_at', '>=', $filtros['desde']);
        }
        if (filled($filtros['hasta'] ?? null)) {
            $query->whereDate('created_at', '<=', $filtros['hasta']);
        }

        return $query->latest('id');
    }

    private function textoSeguro(?string $texto): string
    {
        $texto = str_replace(["\r", "\n", "\t"], ' ', (string) $texto);

        return preg_match('/^\s*[=+\-@]/u', $texto) ? "'".$texto : $texto;
    }
}
