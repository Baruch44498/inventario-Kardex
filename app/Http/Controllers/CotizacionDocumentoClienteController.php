<?php

namespace App\Http\Controllers;

use App\Models\CotizacionCliente;
use Illuminate\Http\Response;

class CotizacionDocumentoClienteController extends Controller
{
    public function show(CotizacionCliente $cotizacionCliente): Response
    {
        $cotizacionCliente->load([
            'tipoOrden', 'clienteDireccion', 'detalles', 'componentes.tipoOrden',
        ]);
        $codigoTipo = $cotizacionCliente->tipoOrden?->codigo
            ?: $cotizacionCliente->componentes->first()?->tipoOrden?->codigo;

        return response()->view('cotizaciones_cliente.documento', [
            'cotizacion' => $cotizacionCliente,
            'mostrarDetalle' => $cotizacionCliente->proforma_id !== null || $codigoTipo === 'OM',
            'codigoTipo' => $codigoTipo,
        ])->header('Cache-Control', 'private, no-store');
    }
}
