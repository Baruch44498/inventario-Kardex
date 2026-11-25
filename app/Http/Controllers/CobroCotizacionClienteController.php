<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarCobroCotizacionClienteRequest;
use App\Models\CobroCotizacionCliente;
use App\Models\CotizacionCliente;
use App\Services\Contabilidad\RegistrarCobroCotizacionClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CobroCotizacionClienteController extends Controller
{
    public function store(
        RegistrarCobroCotizacionClienteRequest $request,
        CotizacionCliente $cotizacionCliente,
        RegistrarCobroCotizacionClienteService $servicio
    ): RedirectResponse {
        $servicio->registrar($cotizacionCliente, $request->validated(), $request->user());

        return redirect()->route('cuentas-cobrar.show', $cotizacionCliente)
            ->with('success', 'Cobro registrado. El saldo quedó actualizado.');
    }

    public function anular(
        Request $request,
        CotizacionCliente $cotizacionCliente,
        CobroCotizacionCliente $cobroCotizacionCliente,
        RegistrarCobroCotizacionClienteService $servicio
    ): RedirectResponse {
        $datos = $request->validate(['motivo_anulacion' => ['required', 'string', 'min:5', 'max:500']]);
        $servicio->anular($cotizacionCliente, $cobroCotizacionCliente, $datos['motivo_anulacion'], $request->user());

        return redirect()->route('cuentas-cobrar.show', $cotizacionCliente)
            ->with('success', 'Cobro anulado. Se conserva el historial y el motivo.');
    }
}
