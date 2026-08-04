<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProveedorController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'in:1,0'],
        ]);

        $query = Proveedor::query()->withCount([
            'cotizaciones as cotizaciones_vigentes_count' =>
            fn($q) => $q->where('estado', '!=', 'ANULADA'),
            'ordenesCompra',
        ]);

        if (! empty($filters['q'])) {
            $search = trim($filters['q']);

            $query->where(function ($q) use ($search): void {
                $q->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('nombre_comercial', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('contacto', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('estado', $filters) && $filters['estado'] !== null && $filters['estado'] !== '') {
            $query->where('estado', (bool) $filters['estado']);
        }

        $proveedores = $query
            ->orderByDesc('estado')
            ->orderBy('razon_social')
            ->paginate(15)
            ->withQueryString();

        $resumen = [
            'total' => Proveedor::query()->count(),
            'activos' => Proveedor::query()->where('estado', true)->count(),
            'con_cotizaciones' => Proveedor::query()
                ->whereHas('cotizaciones', fn($q) => $q->where('estado', '!=', 'ANULADA'))
                ->count(),
            'productos_cotizados' => CotizacionDetalle::query()
                ->whereHas('cotizacion', fn($q) => $q->where('estado', '!=', 'ANULADA'))
                ->distinct('producto_id')
                ->count('producto_id'),
        ];

        return view('proveedores.index', compact('proveedores', 'resumen'));
    }

    public function create(): View
    {
        return view('proveedores.create');
    }

    public function store(StoreProveedorRequest $request): RedirectResponse
    {
        $proveedor = Proveedor::query()->create($request->validated());

        return redirect()
            ->route('proveedores.show', $proveedor->id)
            ->with('success', 'Proveedor registrado correctamente.');
    }

    public function show(Proveedor $proveedor): View
    {
        $proveedor->loadCount([
            'cotizaciones as cotizaciones_vigentes_count' =>
            fn($q) => $q->where('estado', '!=', 'ANULADA'),
            'ordenesCompra',
            'facturasProveedor',
        ]);

        $cotizaciones = $proveedor->cotizaciones()
            ->withCount('detalles')
            ->latest('fecha_cotizacion')
            ->paginate(8, ['*'], 'cotizaciones');

        $precios = CotizacionDetalle::query()
            ->with(['producto.unidadMedida', 'cotizacion'])
            ->whereHas('cotizacion', fn($q) => $q
                ->where('proveedor_id', $proveedor->id)
                ->where('estado', '!=', 'ANULADA'))
            ->orderByDesc(
                Cotizacion::query()
                    ->select('fecha_cotizacion')
                    ->whereColumn('cotizaciones.id', 'cotizacion_detalles.cotizacion_id')
                    ->limit(1)
            )
            ->paginate(12, ['cotizacion_detalles.*'], 'precios');

        $resumen = [
            'productos' => CotizacionDetalle::query()
                ->whereHas('cotizacion', fn($q) => $q
                    ->where('proveedor_id', $proveedor->id)
                    ->where('estado', '!=', 'ANULADA'))
                ->distinct('producto_id')
                ->count('producto_id'),
            'ultima_cotizacion' => $proveedor->cotizaciones()
                ->where('estado', '!=', 'ANULADA')
                ->max('fecha_cotizacion'),
        ];

        return view('proveedores.show', compact(
            'proveedor',
            'cotizaciones',
            'precios',
            'resumen'
        ));
    }

    public function edit(Proveedor $proveedor): View
    {
        return view('proveedores.edit', compact('proveedor'));
    }

    public function update(
        UpdateProveedorRequest $request,
        Proveedor $proveedor
    ): RedirectResponse {
        $proveedor->update($request->validated());

        return redirect()
            ->route('proveedores.show', $proveedor->id)
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    public function toggle(Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update(['estado' => ! $proveedor->estado]);

        return back()->with(
            'success',
            $proveedor->estado
                ? 'Proveedor activado correctamente.'
                : 'Proveedor desactivado correctamente.'
        );
    }
}
