<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarPagoFacturaProveedorRequest;
use App\Models\FacturaProveedor;
use App\Models\PagoFacturaProveedor;
use App\Services\Contabilidad\RegistrarPagoFacturaProveedorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PagoFacturaProveedorController extends Controller
{
    public function store(
        RegistrarPagoFacturaProveedorRequest $request,
        FacturaProveedor $facturaProveedor,
        RegistrarPagoFacturaProveedorService $servicio
    ): RedirectResponse {
        $servicio->registrar($facturaProveedor, $request->validated(), $request->user());

        return redirect()->route('facturas-proveedor.show', $facturaProveedor)
            ->with('success', 'Pago registrado. El saldo de la factura fue actualizado.');
    }

    public function anular(
        Request $request,
        FacturaProveedor $facturaProveedor,
        PagoFacturaProveedor $pagoFacturaProveedor,
        RegistrarPagoFacturaProveedorService $servicio
    ): RedirectResponse {
        $datos = $request->validate(['motivo_anulacion' => ['required', 'string', 'min:5', 'max:500']]);
        $servicio->anular($facturaProveedor, $pagoFacturaProveedor, $datos['motivo_anulacion'], $request->user());

        return redirect()->route('facturas-proveedor.show', $facturaProveedor)
            ->with('success', 'Pago anulado. El registro y su motivo permanecen en el historial.');
    }
}
