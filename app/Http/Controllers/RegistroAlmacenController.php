<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Repisa;
use App\Services\Inventario\RegistrarOperacionAlmacenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegistroAlmacenController extends Controller
{
    public const MODOS_IGV = [
        'AGREGAR' => 'Agregar IGV',
        'INCLUIDO' => 'IGV incluido',
        'NO_APLICA' => 'No aplica',
    ];

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'producto' => ['nullable', 'integer', 'exists:productos,id'],
        ]);

        $busqueda = trim($filtros['q'] ?? '');
        $productos = $busqueda === '' ? null : Producto::query()
            ->where('estado', true)
            ->where(fn ($query) => $query
                ->where('codigo', 'like', "%{$busqueda}%")
                ->orWhere('descripcion', 'like', "%{$busqueda}%")
                ->orWhereHas('inventarios', fn ($inventarios) => $inventarios
                    ->whereHas('repisa', fn ($repisas) => $repisas->where('codigo', 'like', "%{$busqueda}%"))))
            ->with('unidadMedida')
            ->orderBy('codigo')->paginate(12)->withQueryString();

        $producto = isset($filtros['producto']) ? Producto::query()->where('estado', true)
            ->with(['unidadMedida', 'inventarios.repisa'])
            ->findOrFail($filtros['producto']) : null;
        $operaciones = $producto ? DB::table('operaciones_almacen as o')
            ->leftJoin('repisas as origen', 'o.origen_id', '=', 'origen.id')
            ->join('repisas as destino', 'o.destino_id', '=', 'destino.id')
            ->join('users as usuario', 'o.registrado_por', '=', 'usuario.id')
            ->where('o.producto_id', $producto->id)->orderByDesc('o.id')->limit(10)
            ->get(['o.*', 'origen.codigo as origen_codigo', 'destino.codigo as destino_codigo', 'usuario.username']) : collect();
        $precios = $producto ? DB::table('precios_referencia_almacen')->where('producto_id', $producto->id)
            ->orderByDesc('fecha_referencia')->orderByDesc('id')->limit(10)->get() : collect();
        $referenciasImportadas = $producto ? DB::table('producto_referencias_costeo')->where('producto_id', $producto->id)
            ->orderByDesc('id')->limit(5)->get() : collect();
        $destinoAnterior = $request->old('destino_id');
        $destino = is_numeric($destinoAnterior) ? Repisa::query()->find($destinoAnterior) : null;

        return view('registro_almacen.index', [
            'busqueda' => $busqueda,
            'productos' => $productos,
            'producto' => $producto,
            'operaciones' => $operaciones,
            'precios' => $precios,
            'referenciasImportadas' => $referenciasImportadas,
            'destinoAnterior' => $destino,
            'modosIgv' => self::MODOS_IGV,
        ]);
    }

    public function registrar(Request $request, Producto $producto, RegistrarOperacionAlmacenService $servicio): RedirectResponse
    {
        $datos = $request->validate([
            'solicitud' => ['required', 'uuid'],
            'tipo' => ['required', 'in:TRASLADO,SALDO_INICIAL'],
            'origen_id' => ['exclude_unless:tipo,TRASLADO', 'required', 'integer', 'exists:repisas,id', 'different:destino_id'],
            'destino_id' => ['required', 'integer', 'exists:repisas,id'],
            'cantidad' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:99999999999.999'],
            'costo_inicial' => ['exclude_unless:tipo,SALDO_INICIAL', 'nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999.9999'],
            'observacion' => ['required', 'string', 'max:240'],
            'confirmar_compartida' => ['sometimes', 'accepted'],
        ], [], [
            'destino_id' => 'repisa de destino',
            'origen_id' => 'repisa de origen',
            'costo_inicial' => 'costo unitario inicial',
        ]);

        $id = $servicio->registrar($producto, $datos, $request->user());

        return redirect()->route('registro-almacen.index', ['producto' => $producto->id], 303)
            ->with('success', "Operación de almacén #{$id} registrada.");
    }

    public function guardarPrecio(Request $request, Producto $producto): RedirectResponse
    {
        abort_unless($producto->estado, 404);
        $datos = $request->validate([
            'precio_soles' => ['required', 'numeric', 'decimal:0,4', 'min:0.0001', 'max:9999999999.9999'],
            'igv_modo' => ['required', 'in:'.implode(',', array_keys(self::MODOS_IGV))],
            'fecha_referencia' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fuente' => ['required', 'string', 'max:150'],
        ], [], ['igv_modo' => 'tratamiento del IGV']);

        DB::table('precios_referencia_almacen')->insert($datos + [
            'producto_id' => $producto->id,
            'registrado_por' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('registro-almacen.index', ['producto' => $producto->id], 303)
            ->with('success', 'Precio de referencia guardado.');
    }
}
