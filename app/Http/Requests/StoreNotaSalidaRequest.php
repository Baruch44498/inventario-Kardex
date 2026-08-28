<?php

namespace App\Http\Requests;

use App\Models\OrdenOperacion;
use App\Services\Ordenes\AreasTrabajoOrdenService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNotaSalidaRequest extends FormRequest
{
    public const MOTIVOS = ['ORDEN_OPERACION', 'PROFORMA', 'USO_INTERNO', 'OTRO'];
    public const TRATAMIENTOS = ['CONSUMO', 'USO_TEMPORAL', 'VENTA_DIRECTA', 'PRESTAMO_EXTERNO'];
    public const MOTIVOS_EXCEDENTE = ['NECESIDAD_OPERATIVA', 'REPOSICION_MALOGRADO'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'entregado_a' => trim((string) $this->input('entregado_a')),
            'area_trabajo' => $this->filled('area_trabajo')
                ? trim((string) $this->input('area_trabajo'))
                : null,
            'observacion' => $this->filled('observacion')
                ? trim((string) $this->input('observacion'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'motivo_salida' => ['required', Rule::in(self::MOTIVOS)],
            'orden_operacion_id' => ['nullable', 'integer', 'exists:ordenes_operacion,id'],
            'proforma_id' => ['nullable', 'integer', 'exists:proformas,id'],
            'area_trabajo' => ['nullable', 'string', 'max:150'],
            'fecha_salida' => ['required', 'date', 'before_or_equal:today'],
            'recibido_por_empleado_id' => [
                'nullable',
                'integer',
                Rule::exists('empleados', 'id')->where(fn($query) => $query->where('estado', true)),
            ],
            'entregado_a' => ['nullable', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.inventario_id' => ['required', 'integer', 'exists:inventarios,id'],
            'detalles.*.proforma_detalle_id' => ['nullable', 'integer', 'exists:proforma_detalles,id'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.repisa_id' => ['required', 'integer', 'exists:repisas,id'],
            'detalles.*.tratamiento' => ['required', Rule::in(self::TRATAMIENTOS)],
            'detalles.*.cantidad' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'detalles.*.motivo_excedente' => ['nullable', Rule::in(self::MOTIVOS_EXCEDENTE)],
            'detalles.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $motivo = (string) $this->input('motivo_salida');
                $ordenId = (int) $this->input('orden_operacion_id');
                $proformaId = (int) $this->input('proforma_id');
                $this->validarFraccionamiento($validator);

                if ($motivo === 'ORDEN_OPERACION') {
                    $orden = OrdenOperacion::query()->find($ordenId);

                    if (! $orden || $orden->estado !== 'EN_PROCESO') {
                        $validator->errors()->add(
                            'orden_operacion_id',
                            'Selecciona una orden activa (EN_PROCESO). Activa la orden antes de registrar una Nota de Salida.'
                        );
                    } else {
                        $areas = app(AreasTrabajoOrdenService::class)->areas($orden);
                        $area = app(AreasTrabajoOrdenService::class)->resolver(
                            $orden,
                            $this->input('area_trabajo')
                        );

                        if (! $area) {
                            $validator->errors()->add(
                                'area_trabajo',
                                $areas->count() > 1
                                    ? 'Selecciona el área de la orden que recibirá los materiales.'
                                    : 'La orden no tiene un área válida para registrar la salida.'
                            );
                        } else {
                            $this->merge(['area_trabajo' => $area]);
                        }

                        $hayEmpleadosActivos = DB::table('empleados')->where('estado', true)->exists();
                        if ($hayEmpleadosActivos && ! $this->filled('recibido_por_empleado_id')) {
                            $validator->errors()->add(
                                'recibido_por_empleado_id',
                                'Selecciona al empleado que recibe los productos.'
                            );
                        }
                    }
                }

                if ($motivo !== 'ORDEN_OPERACION' && ! $this->filled('entregado_a')) {
                    $validator->errors()->add('entregado_a', 'Indica quién recibe los productos.');
                }

                if ($motivo === 'PROFORMA') {
                    $proforma = DB::table('proformas')
                        ->where('id', $proformaId)
                        ->first(['id', 'estado']);

                    if (! $proforma || $proforma->estado === 'ANULADA') {
                        $validator->errors()->add(
                            'proforma_id',
                            'Selecciona una Proforma de Almacén vigente.'
                        );
                    }
                }

                $filasSeleccionadas = 0;
                $combinaciones = [];
                $cantidadPorDetalleProforma = [];

                foreach ($this->input('detalles', []) as $indice => $detalle) {
                    $cantidad = round((float) ($detalle['cantidad'] ?? 0), 3);
                    if ($cantidad <= 0) {
                        continue;
                    }

                    $filasSeleccionadas++;
                    $ruta = "detalles.{$indice}";
                    $inventarioId = (int) ($detalle['inventario_id'] ?? 0);
                    $productoId = (int) ($detalle['producto_id'] ?? 0);
                    $repisaId = (int) ($detalle['repisa_id'] ?? 0);
                    $tratamiento = (string) ($detalle['tratamiento'] ?? '');
                    $proformaDetalleId = (int) ($detalle['proforma_detalle_id'] ?? 0);
                    $clave = implode(':', [$productoId, $repisaId, $tratamiento, $proformaDetalleId]);

                    if (isset($combinaciones[$clave])) {
                        $validator->errors()->add(
                            "{$ruta}.cantidad",
                            'La misma existencia está repetida para el mismo tratamiento.'
                        );
                    }
                    $combinaciones[$clave] = true;

                    $inventario = DB::table('inventarios as i')
                        ->join('productos as p', 'p.id', '=', 'i.producto_id')
                        ->join('repisas as r', 'r.id', '=', 'i.repisa_id')
                        ->where('i.id', $inventarioId)
                        ->where('i.producto_id', $productoId)
                        ->where('i.repisa_id', $repisaId)
                        ->first([
                            'i.stock_actual',
                            'p.estado as producto_activo',
                            'r.estado as repisa_activa',
                        ]);

                    if (! $inventario) {
                        $validator->errors()->add(
                            "{$ruta}.inventario_id",
                            'La existencia seleccionada ya no está disponible.'
                        );
                        continue;
                    }

                    if (! $inventario->producto_activo) {
                        $validator->errors()->add("{$ruta}.producto_id", 'El producto está inactivo.');
                    }
                    if (! $inventario->repisa_activa) {
                        $validator->errors()->add("{$ruta}.repisa_id", 'La repisa está inactiva.');
                    }

                    $disponible = round((float) $inventario->stock_actual, 3);
                    if ($cantidad > $disponible) {
                        $validator->errors()->add(
                            "{$ruta}.cantidad",
                            "La cantidad supera el stock físico de {$disponible}."
                        );
                    }

                    if ($motivo === 'PROFORMA') {
                        if ($proformaDetalleId <= 0) {
                            $validator->errors()->add(
                                "{$ruta}.proforma_detalle_id",
                                'La línea debe estar vinculada a la Proforma.'
                            );
                            continue;
                        }

                        $linea = DB::table('proforma_detalles')
                            ->where('id', $proformaDetalleId)
                            ->where('proforma_id', $proformaId)
                            ->where('producto_id', $productoId)
                            ->first(['id', 'cantidad', 'tratamiento']);

                        if (! $linea) {
                            $validator->errors()->add(
                                "{$ruta}.proforma_detalle_id",
                                'La línea no pertenece a la Proforma seleccionada.'
                            );
                            continue;
                        }

                        $esperado = $linea->tratamiento === 'PRESTAMO'
                            ? 'PRESTAMO_EXTERNO'
                            : 'VENTA_DIRECTA';

                        if ($tratamiento !== $esperado) {
                            $validator->errors()->add(
                                "{$ruta}.tratamiento",
                                'El tratamiento físico no coincide con la línea de la Proforma.'
                            );
                        }

                        $cantidadPorDetalleProforma[$proformaDetalleId] = round(
                            ($cantidadPorDetalleProforma[$proformaDetalleId] ?? 0) + $cantidad,
                            3
                        );
                    } elseif (! in_array($tratamiento, ['CONSUMO', 'USO_TEMPORAL'], true)) {
                        $validator->errors()->add(
                            "{$ruta}.tratamiento",
                            'Para una salida interna selecciona Consumo o Uso temporal.'
                        );
                    }
                }

                if ($motivo === 'PROFORMA') {
                    foreach ($cantidadPorDetalleProforma as $detalleId => $cantidadNueva) {
                        $linea = DB::table('proforma_detalles')->where('id', $detalleId)->first();
                        if (! $linea) {
                            continue;
                        }

                        $yaDespachado = (float) DB::table('nota_salida_detalles as d')
                            ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
                            ->where('d.proforma_detalle_id', $detalleId)
                            ->where('n.estado', 'CONFIRMADA')
                            ->sum('d.cantidad');

                        $pendiente = max(0, round((float) $linea->cantidad - $yaDespachado, 3));
                        if ($cantidadNueva > $pendiente + 0.0001) {
                            $validator->errors()->add(
                                'detalles',
                                "La salida supera el pendiente de {$pendiente} de una línea de la Proforma."
                            );
                        }
                    }
                }

                if ($filasSeleccionadas === 0) {
                    $validator->errors()->add(
                        'detalles',
                        'Ingresa una cantidad mayor que cero en al menos un producto.'
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_salida.required' => 'Selecciona el motivo de la salida.',
            'fecha_salida.required' => 'Selecciona la fecha de salida.',
            'fecha_salida.before_or_equal' => 'La fecha de salida no puede ser futura.',
            'recibido_por_empleado_id.exists' => 'El empleado seleccionado no está activo o ya no existe.',
            'detalles.required' => 'Selecciona al menos una existencia para la salida.',
        ];
    }

    private function validarFraccionamiento(Validator $validator): void
    {
        $productos = DB::table('productos')
            ->whereIn(
                'id',
                collect($this->input('detalles', []))->pluck('producto_id')->filter()->unique()
            )
            ->pluck('permite_fraccionamiento', 'id');

        foreach ($this->input('detalles', []) as $indice => $detalle) {
            $cantidad = (float) ($detalle['cantidad'] ?? 0);
            $productoId = (int) ($detalle['producto_id'] ?? 0);

            if ($cantidad <= 0 || (bool) ($productos[$productoId] ?? false)) {
                continue;
            }

            if (abs($cantidad - round($cantidad)) > 0.0001) {
                $validator->errors()->add(
                    "detalles.{$indice}.cantidad",
                    'Este producto no admite cantidades fraccionarias; ingresa un número entero.'
                );
            }
        }
    }
}
