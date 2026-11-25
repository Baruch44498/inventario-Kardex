<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use Illuminate\Http\Response;

class OrdenCompraDocumentoController extends Controller
{
    public function show(OrdenCompra $ordenCompra): Response
    {
        $ordenCompra->load(['proveedor', 'detalles.producto.unidadMedida']);

        return response()->view('ordenes_compra.documento', ['orden' => $ordenCompra])
            ->header('Cache-Control', 'private, no-store');
    }
}
