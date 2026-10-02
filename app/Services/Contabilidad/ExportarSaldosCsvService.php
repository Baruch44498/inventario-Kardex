<?php

namespace App\Services\Contabilidad;

use App\Models\CotizacionCliente;
use App\Models\FacturaProveedor;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportarSaldosCsvService
{
    /** @param Builder<FacturaProveedor> $consulta */
    public function cuentasPagar(Builder $consulta): StreamedResponse
    {
        return $this->descargar('CUENTAS_POR_PAGAR', [
            'ID factura', 'Tipo', 'Documento', 'Proveedor', 'RUC', 'Orden de compra',
            'Emisión', 'Vencimiento', 'Moneda', 'Total', 'Pagado', 'Saldo', 'Estado',
        ], function ($salida) use ($consulta): void {
            foreach ($consulta->lazy(500) as $factura) {
                $estado = $factura->saldoPendiente() > 0 && $factura->fecha_vencimiento?->isBefore(today())
                    ? 'VENCIDA' : $factura->estado;
                $this->fila($salida, [
                    $factura->id,
                    $this->texto($factura->tipo_documento),
                    $this->texto($factura->numeroVisible()),
                    $this->texto($factura->proveedor?->nombreVisible()),
                    $this->texto($factura->proveedor?->ruc),
                    $this->texto($factura->ordenCompra?->codigo),
                    $factura->fecha_emision?->format('Y-m-d'),
                    $factura->fecha_vencimiento?->format('Y-m-d'),
                    $factura->moneda,
                    $this->importe($factura->total),
                    $this->importe($factura->montoPagado()),
                    $this->importe($factura->saldoPendiente()),
                    $estado,
                ]);
            }
        });
    }

    /** @param Builder<CotizacionCliente> $consulta */
    public function cuentasCobrar(Builder $consulta): StreamedResponse
    {
        return $this->descargar('CUENTAS_POR_COBRAR', [
            'ID cotización', 'Cotización', 'Cliente', 'Documento cliente', 'Origen', 'Orden',
            'Emisión', 'Moneda', 'Total', 'Cobrado', 'Saldo', 'Estado',
        ], function ($salida) use ($consulta): void {
            foreach ($consulta->lazy(500) as $cotizacion) {
                $cobrado = $cotizacion->montoCobrado();
                $saldo = $cotizacion->saldoPorCobrar();
                $this->fila($salida, [
                    $cotizacion->id,
                    $this->texto($cotizacion->codigo),
                    $this->texto($cotizacion->cliente_nombre),
                    $this->texto($cotizacion->cliente_documento),
                    $cotizacion->proforma_id ? 'VENTA_DIRECTA' : 'ORDEN',
                    $this->texto($cotizacion->ordenOperacion?->codigo_orden),
                    $cotizacion->fecha_emision?->format('Y-m-d'),
                    $cotizacion->moneda,
                    $this->importe($cotizacion->total),
                    $this->importe($cobrado),
                    $this->importe($saldo),
                    $saldo === 0.0 ? 'COBRADA' : ($cobrado > 0 ? 'PARCIAL' : 'PENDIENTE'),
                ]);
            }
        });
    }

    /** @param list<string> $cabeceras */
    private function descargar(string $nombre, array $cabeceras, callable $escribir): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabeceras, $escribir): void {
            $salida = fopen('php://output', 'wb');
            if ($salida === false) {
                return;
            }
            try {
                fwrite($salida, "\xEF\xBB\xBF");
                $this->fila($salida, $cabeceras);
                $escribir($salida);
            } finally {
                fclose($salida);
            }
        }, $nombre.'_'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @param resource $salida @param list<string|int|null> $columnas */
    private function fila($salida, array $columnas): void
    {
        fputcsv($salida, $columnas, ';', '"', '');
    }

    private function importe(string|float|int $monto): string
    {
        return number_format((float) $monto, 4, ',', '');
    }

    private function texto(?string $valor): string
    {
        $valor = str_replace(["\r", "\n", "\t"], ' ', (string) $valor);

        return preg_match('/^\s*[=+\-@]/u', $valor) ? "'".$valor : $valor;
    }
}
