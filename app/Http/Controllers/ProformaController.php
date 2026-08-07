<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnularDocumentoComercialRequest;
use App\Http\Requests\GuardarProformaRequest;
use App\Models\Cliente;
use App\Models\CotizacionCliente;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Services\Ventas\CalcularProformaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProformaController extends Controller
{
    public function __construct(
        private CalcularProformaService $calculador
    ) {}

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => [
                'nullable',
                'in:BORRADOR,ENVIADA_A_LOGISTICA,COTIZADA,SIN_COBRO,CONVERTIDA_EN_ORDEN,ANULADA',
            ],
        ]);

        $consulta = Proforma::query()
            ->with(['cliente.tipoCliente', 'registrador'])
            ->withCount(['detalles', 'cotizacionesCliente']);

        if (! empty($filtros['q'])) {
            $termino = trim($filtros['q']);

            $consulta->where(function ($query) use ($termino): void {
                $query->where('codigo', 'like', "%{$termino}%")
                    ->orWhereHas('cliente', fn($cliente) => $cliente
                        ->where('numero_documento', 'like', "%{$termino}%")
                        ->orWhere('ruc', 'like', "%{$termino}%")
                        ->orWhere('razon_social', 'like', "%{$termino}%")
                        ->orWhere('nombre_comercial', 'like', "%{$termino}%"));
            });
        }

        if (! empty($filtros['estado'])) {
            $consulta->where('estado', $filtros['estado']);
        }

        $proformas = $consulta
            ->latest('fecha_emision')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $resumen = [
            'pendientes' => Proforma::query()
                ->where('estado', 'ENVIADA_A_LOGISTICA')
                ->count(),
            'abiertas' => CotizacionCliente::query()
                ->whereNotNull('proforma_id')
                ->where('estado', 'ABIERTA')
                ->count(),
            'cerradas' => CotizacionCliente::query()
                ->whereNotNull('proforma_id')
                ->where('estado', 'CERRADA')
                ->count(),
            'anuladas' => Proforma::query()
                ->where('estado', 'ANULADA')
                ->count(),
        ];

        return view('proformas.index', compact('proformas', 'resumen'));
    }

    public function create(Request $request): View
    {
        return view('proformas.create', $this->catalogos(
            $request,
            (int) $request->old('cliente_id') ?: null,
        ));
    }

    public function store(GuardarProformaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $detalles = $datos['detalles'];
        unset($datos['detalles']);

        $proforma = DB::transaction(function () use ($datos, $detalles, $request): Proforma {
            [$lineas, $totales, $margen] = $this->prepararDetalles(
                $detalles,
                isset($datos['cliente_id']) ? (int) $datos['cliente_id'] : null,
                $datos['moneda'],
                isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null
            );

            $proforma = Proforma::query()->create([
                ...$datos,
                ...$totales,
                'codigo' => $this->siguienteCodigo(),
                'margen_cliente_porcentaje' => $margen,
                'estado' => 'BORRADOR',
                'registrado_por' => $request->user()->id,
            ]);

            $proforma->detalles()->createMany($lineas);

            return $proforma;
        });

        return redirect()
            ->route('proformas.show', $proforma)
            ->with('success', 'Proforma guardada como borrador. Aún no fue enviada a Logística.');
    }

    public function show(Proforma $proforma): View
    {
        $proforma->load([
            'cliente.tipoCliente',
            'registrador',
            'enviador',
            'anulador',
            'detalles.producto',
            'detalles.reposiciones.registrador',
            'cotizacionesCliente' => fn($query) => $query
                ->with(['cotizador', 'cerrador', 'anulador'])
                ->orderBy('version'),
        ]);

        return view('proformas.show', compact('proforma'));
    }

    public function edit(Request $request, Proforma $proforma): View|RedirectResponse
    {
        $proforma->load('detalles');

        if (! $proforma->esEditable()) {
            return redirect()
                ->route('proformas.show', $proforma)
                ->with('error', 'Solo una proforma en borrador puede editarse.');
        }

        return view('proformas.edit', [
            'proforma' => $proforma,
            ...$this->catalogos(
                $request,
                (int) $request->old('cliente_id', $proforma->cliente_id) ?: null,
                $proforma->detalles->pluck('producto_id')->all()
            ),
        ]);
    }

    public function update(
        GuardarProformaRequest $request,
        Proforma $proforma
    ): RedirectResponse {
        if (! $proforma->esEditable()) {
            return redirect()
                ->route('proformas.show', $proforma)
                ->with('error', 'Solo una proforma en borrador puede editarse.');
        }

        $datos = $request->validated();
        $detalles = $datos['detalles'];
        unset($datos['detalles']);

        DB::transaction(function () use ($proforma, $datos, $detalles): void {
            [$lineas, $totales, $margen] = $this->prepararDetalles(
                $detalles,
                isset($datos['cliente_id']) ? (int) $datos['cliente_id'] : null,
                $datos['moneda'],
                isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null
            );

            $proforma->update([
                ...$datos,
                ...$totales,
                'margen_cliente_porcentaje' => $margen,
            ]);
            $proforma->detalles()->delete();
            $proforma->detalles()->createMany($lineas);
        });

        return redirect()
            ->route('proformas.show', $proforma)
            ->with('success', 'Borrador actualizado correctamente.');
    }

    public function enviar(Request $request, Proforma $proforma): RedirectResponse
    {
        if (! $proforma->puedeEnviarse()) {
            return back()->with('error', 'La proforma no está disponible para enviarse.');
        }

        $proforma->update([
            'estado' => 'ENVIADA_A_LOGISTICA',
            'enviado_por' => $request->user()->id,
            'enviado_en' => now(),
        ]);

        return redirect()
            ->route('proformas.show', $proforma)
            ->with('success', 'Proforma enviada a Logística para su revisión y valorización.');
    }

    public function confirmarSinCobro(
        Request $request,
        Proforma $proforma
    ): RedirectResponse {
        if (! $proforma->puedeConfirmarseSinCobro()) {
            return back()->with(
                'error',
                'Solo una proforma compuesta exclusivamente por préstamos puede cerrarse sin cobro.'
            );
        }

        $proforma->update(['estado' => 'SIN_COBRO']);

        return redirect()
            ->route('proformas.show', $proforma)
            ->with(
                'success',
                'Proforma registrada sin cobro. Los préstamos permanecerán pendientes hasta su reposición.'
            );
    }

    public function registrarReposicion(
        Request $request,
        Proforma $proforma,
        ProformaDetalle $detalle
    ): RedirectResponse {
        abort_unless(
            (int) $detalle->proforma_id === (int) $proforma->id,
            404
        );

        if (! $detalle->esPrestamo()) {
            return back()->with('error', 'Solo los productos prestados admiten reposición.');
        }

        if ($proforma->estaAnulada()) {
            return back()->with('error', 'No se registran reposiciones sobre una proforma anulada.');
        }

        if ($detalle->cantidadPrestadaFisicamente() <= 0.0001) {
            return back()->with(
                'error',
                'Primero debe existir una Nota de Salida confirmada del préstamo.'
            );
        }

        return redirect()
            ->route('notas-ingreso.create', [
                'motivo_ingreso' => 'REPOSICION_PRESTAMO',
                'proforma_id' => $proforma->id,
            ])
            ->with(
                'info',
                'La reposición física se registra mediante Nota de Ingreso para actualizar inventario y Kardex.'
            );
    }

    public function anular(
        AnularDocumentoComercialRequest $request,
        Proforma $proforma
    ): RedirectResponse {
        $proforma->load('cotizacionesCliente');

        if ($proforma->estaAnulada()) {
            return back()->with('error', 'La proforma ya está anulada.');
        }

        if (
            $proforma->estado === 'CONVERTIDA_EN_ORDEN'
            || $proforma->cotizacionesCliente->contains('estado', 'CONVERTIDA_EN_ORDEN')
        ) {
            return back()->with(
                'error',
                'No se puede anular una proforma que ya originó una orden de operación histórica.'
            );
        }

        DB::transaction(function () use ($proforma, $request): void {
            $auditoria = [
                'anulado_por' => $request->user()->id,
                'anulado_en' => now(),
                'motivo_anulacion' => $request->validated('motivo_anulacion'),
            ];

            $proforma->cotizacionesCliente()
                ->where('estado', 'ABIERTA')
                ->update(['estado' => 'ANULADA', ...$auditoria]);

            $proforma->update(['estado' => 'ANULADA', ...$auditoria]);
        });

        return redirect()
            ->route('proformas.show', $proforma)
            ->with('success', 'Proforma anulada. El documento y su motivo permanecen en el historial.');
    }

    private function prepararDetalles(
        array $detalles,
        ?int $clienteId,
        string $moneda,
        ?float $tipoCambio
    ): array {
        $cliente = $clienteId
            ? Cliente::query()->with('tipoCliente')->findOrFail($clienteId)
            : null;
        $margen = (float) ($cliente?->tipoCliente?->porcentaje_ganancia ?? 0);
        $productos = Producto::query()
            ->with(['unidadMedida', 'inventarios'])
            ->whereIn('id', collect($detalles)->pluck('producto_id'))
            ->get()
            ->keyBy('id');

        $entradas = collect($detalles)->map(function (array $detalle) use (
            $productos,
            $margen,
            $moneda,
            $tipoCambio
        ): array {
            $producto = $productos->get((int) $detalle['producto_id']);
            abort_unless($producto, 422, 'Uno de los productos ya no está disponible.');

            $costoPen = $producto->costoPromedioActual();
            $costo = $moneda === 'USD' && (float) $tipoCambio > 0
                ? round($costoPen / (float) $tipoCambio, 4)
                : $costoPen;
            $unidad = $producto->unidadMedida;
            $tratamiento = $detalle['tratamiento'] ?? 'VENTA';

            return [
                'producto_id' => $producto->id,
                'codigo_producto' => $producto->codigo,
                'descripcion' => $producto->descripcion,
                'unidad_medida' => $unidad?->abreviatura
                    ?? $unidad?->codigo
                    ?? $unidad?->nombre,
                'cantidad' => $detalle['cantidad'],
                'tratamiento' => $tratamiento,
                'costo_referencia' => $costo,
                'margen_sugerido' => $margen,
                'igv_modo' => $detalle['igv_modo'],
                'observacion' => $detalle['observacion'] ?? null,
            ];
        });

        $ventas = $entradas
            ->where('tratamiento', 'VENTA')
            ->values()
            ->all();
        $prestamos = $entradas
            ->where('tratamiento', 'PRESTAMO')
            ->map(fn(array $linea): array => [
                ...$linea,
                'precio_sugerido' => null,
                'precio_unitario' => 0,
                'igv_modo' => 'NO_APLICA',
                'igv_porcentaje' => 0,
                'subtotal' => 0,
                'impuesto' => 0,
                'total' => 0,
            ])
            ->values()
            ->all();

        $resultadoVentas = $ventas === []
            ? [
                'detalles' => [],
                'totales' => ['subtotal' => 0, 'impuesto' => 0, 'total' => 0],
            ]
            : $this->calculador->calcular($ventas, true);

        $lineas = collect($resultadoVentas['detalles'])
            ->concat($prestamos)
            ->values()
            ->all();

        return [
            $lineas,
            $resultadoVentas['totales'],
            $margen,
        ];
    }

    private function catalogos(
        Request $request,
        ?int $clienteId = null,
        array $productosFallback = []
    ): array {
        $productoIds = $this->productosDelFormulario($request, $productosFallback);

        return [
            'clienteSeleccionado' => $clienteId
                ? Cliente::query()->with('tipoCliente')->find($clienteId)
                : null,
            'productosSeleccionados' => Producto::query()
                ->with(['unidadMedida', 'inventarios'])
                ->whereIn('id', $productoIds)
                ->get()
                ->keyBy('id'),
        ];
    }

    private function productosDelFormulario(
        Request $request,
        array $fallback = []
    ): array {
        $detalles = $request->old('detalles');

        return collect(is_array($detalles) ? $detalles : $fallback)
            ->map(fn($detalle) => is_array($detalle)
                ? ($detalle['producto_id'] ?? null)
                : $detalle)
            ->filter(fn($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function siguienteCodigo(): string
    {
        $ultimo = Proforma::query()
            ->where('codigo', 'like', 'PRF-%')
            ->latest('id')
            ->value('codigo');
        $secuencia = is_string($ultimo)
            && preg_match('/^PRF-(\d{6})$/', $ultimo, $coincidencias)
            ? (int) $coincidencias[1] + 1
            : 1;

        do {
            $codigo = sprintf('PRF-%06d', $secuencia++);
        } while (Proforma::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
