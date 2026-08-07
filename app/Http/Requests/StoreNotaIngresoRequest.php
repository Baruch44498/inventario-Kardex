<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNotaIngresoRequest extends FormRequest
{
    public const MOTIVOS = [
        'COMPRA',
        'DEVOLUCION_HERRAMIENTA',
        'RETORNO_MATERIAL',
        'REPOSICION_PRESTAMO',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_guia_remision' => $this->filled('numero_guia_remision')
                ? trim((string) $this->input('numero_guia_remision'))
                : null,
            'observacion' => $this->filled('observacion')
                ? trim((string) $this->input('observacion'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'motivo_ingreso' => ['required', Rule::in(self::MOTIVOS)],
            'orden_compra_id' => ['nullable', 'integer', 'exists:ordenes_compra,id'],
            'factura_proveedor_id' => ['nullable', 'integer', 'exists:facturas_proveedor,id'],
            'nota_salida_id' => ['nullable', 'integer', 'exists:notas_salida,id'],
            'proforma_id' => ['nullable', 'integer', 'exists:proformas,id'],
            'fecha_ingreso' => ['required', 'date', 'before_or_equal:today'],
            'numero_guia_remision' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.orden_compra_detalle_id' => ['nullable', 'integer', 'exists:orden_compra_detalles,id'],
            'detalles.*.nota_salida_detalle_id' => ['nullable', 'integer', 'exists:nota_salida_detalles,id'],
            'detalles.*.proforma_detalle_id' => ['nullable', 'integer', 'exists:proforma_detalles,id'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.repisa_id' => ['nullable', 'integer', 'exists:repisas,id'],
            'detalles.*.cantidad' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999'],
            'detalles.*.costo_unitario' => ['nullable', 'numeric', 'min:0', 'max:9999999999.9999'],
            'detalles.*.lote' => ['nullable', 'string', 'max:80'],
            'detalles.*.fecha_vencimiento' => ['nullable', 'date'],
            'detalles.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $motivo = (string) $this->input('motivo_ingreso');
                $ordenId = (int) $this->input('orden_compra_id');
                $notaSalidaId = (int) $this->input('nota_salida_id');
                $proformaId = (int) $this->input('proforma_id');

                if ($motivo === 'COMPRA') {
                    $this->validarCompra($validator, $ordenId);
                    return;
                }

                if (in_array($motivo, ['DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL'], true)) {
                    $this->validarRetornoSalida($validator, $motivo, $notaSalidaId);
                    return;
                }

                if ($motivo === 'REPOSICION_PRESTAMO') {
                    $this->validarReposicionPrestamo($validator, $proformaId);
                }
            },
        ];
    }

    private function validarCompra(Validator $validator, int $ordenId): void
    {
        $orden = DB::table('ordenes_compra')
            ->where('id', $ordenId)
            ->first(['id', 'estado']);

        if (! $orden || ! in_array($orden->estado, ['APROBADA', 'PARCIALMENTE_RECIBIDA'], true)) {
            $validator->errors()->add(
                'orden_compra_id',
                'La orden seleccionada no está disponible para recepción.'
            );
            return;
        }

        $facturaId = $this->input('factura_proveedor_id');
        if ($facturaId) {
            $facturaValida = DB::table('facturas_proveedor')
                ->where('id', $facturaId)
                ->where('orden_compra_id', $ordenId)
                ->where('estado', '!=', 'ANULADA')
                ->exists();

            if (! $facturaValida) {
                $validator->errors()->add(
                    'factura_proveedor_id',
                    'La factura no pertenece a la orden de compra seleccionada.'
                );
            }
        }

        $filas = 0;
        foreach ($this->input('detalles', []) as $indice => $detalle) {
            $cantidad = round((float) ($detalle['cantidad'] ?? 0), 3);
            if ($cantidad <= 0) {
                continue;
            }

            $filas++;
            $ruta = "detalles.{$indice}";
            $detalleId = (int) ($detalle['orden_compra_detalle_id'] ?? 0);
            $productoId = (int) ($detalle['producto_id'] ?? 0);

            $ordenDetalle = DB::table('orden_compra_detalles')
                ->where('id', $detalleId)
                ->where('orden_compra_id', $ordenId)
                ->where('producto_id', $productoId)
                ->first(['cantidad_ordenada', 'cantidad_recibida']);

            if (! $ordenDetalle) {
                $validator->errors()->add(
                    "{$ruta}.producto_id",
                    'El producto no pertenece a la orden seleccionada.'
                );
                continue;
            }

            $pendiente = round(
                (float) $ordenDetalle->cantidad_ordenada - (float) $ordenDetalle->cantidad_recibida,
                3
            );

            if ($cantidad > $pendiente) {
                $validator->errors()->add(
                    "{$ruta}.cantidad",
                    "La cantidad supera el pendiente de {$pendiente}."
                );
            }

            $this->validarRepisa($validator, $ruta, $detalle['repisa_id'] ?? null);

            if (! isset($detalle['costo_unitario']) || (float) $detalle['costo_unitario'] <= 0) {
                $validator->errors()->add(
                    "{$ruta}.costo_unitario",
                    'El costo unitario debe ser mayor que cero.'
                );
            }

            if (
                ! empty($detalle['fecha_vencimiento'])
                && $detalle['fecha_vencimiento'] < $this->input('fecha_ingreso')
            ) {
                $validator->errors()->add(
                    "{$ruta}.fecha_vencimiento",
                    'La fecha de vencimiento no puede ser anterior al ingreso.'
                );
            }
        }

        if ($filas === 0) {
            $validator->errors()->add(
                'detalles',
                'Ingresa una cantidad mayor que cero en al menos un producto.'
            );
        }
    }

    private function validarRetornoSalida(
        Validator $validator,
        string $motivo,
        int $notaSalidaId
    ): void {
        $nota = DB::table('notas_salida')
            ->where('id', $notaSalidaId)
            ->where('estado', 'CONFIRMADA')
            ->first(['id']);

        if (! $nota) {
            $validator->errors()->add(
                'nota_salida_id',
                'Selecciona una Nota de Salida confirmada.'
            );
            return;
        }

        $tratamientoEsperado = $motivo === 'DEVOLUCION_HERRAMIENTA'
            ? 'USO_TEMPORAL'
            : 'CONSUMO';

        $filas = 0;
        $acumulado = [];

        foreach ($this->input('detalles', []) as $indice => $detalle) {
            $cantidad = round((float) ($detalle['cantidad'] ?? 0), 3);
            if ($cantidad <= 0) {
                continue;
            }

            $filas++;
            $ruta = "detalles.{$indice}";
            $salidaDetalleId = (int) ($detalle['nota_salida_detalle_id'] ?? 0);
            $productoId = (int) ($detalle['producto_id'] ?? 0);

            $salidaDetalle = DB::table('nota_salida_detalles')
                ->where('id', $salidaDetalleId)
                ->where('nota_salida_id', $notaSalidaId)
                ->where('producto_id', $productoId)
                ->where('tratamiento', $tratamientoEsperado)
                ->first(['id', 'cantidad']);

            if (! $salidaDetalle) {
                $validator->errors()->add(
                    "{$ruta}.nota_salida_detalle_id",
                    'El producto no corresponde al tipo de devolución seleccionado.'
                );
                continue;
            }

            $this->validarRepisa($validator, $ruta, $detalle['repisa_id'] ?? null);
            $acumulado[$salidaDetalleId] = round(
                ($acumulado[$salidaDetalleId] ?? 0) + $cantidad,
                3
            );
        }

        foreach ($acumulado as $salidaDetalleId => $cantidadNueva) {
            $salidaDetalle = DB::table('nota_salida_detalles')
                ->where('id', $salidaDetalleId)
                ->first(['cantidad']);

            $yaRetornado = (float) DB::table('nota_ingreso_detalles as d')
                ->join('notas_ingreso as n', 'n.id', '=', 'd.nota_ingreso_id')
                ->where('d.nota_salida_detalle_id', $salidaDetalleId)
                ->where('n.estado', 'CONFIRMADA')
                ->sum('d.cantidad');

            $pendiente = max(0, round((float) $salidaDetalle->cantidad - $yaRetornado, 3));
            if ($cantidadNueva > $pendiente + 0.0001) {
                $validator->errors()->add(
                    'detalles',
                    "La devolución supera el pendiente de {$pendiente}."
                );
            }
        }

        if ($filas === 0) {
            $validator->errors()->add(
                'detalles',
                'Ingresa una cantidad mayor que cero en al menos un producto.'
            );
        }
    }

    private function validarReposicionPrestamo(Validator $validator, int $proformaId): void
    {
        $proforma = DB::table('proformas')
            ->where('id', $proformaId)
            ->first(['id']);

        if (! $proforma) {
            $validator->errors()->add(
                'proforma_id',
                'Selecciona la Proforma que originó el préstamo.'
            );
            return;
        }

        $filas = 0;
        $acumulado = [];

        foreach ($this->input('detalles', []) as $indice => $detalle) {
            $cantidad = round((float) ($detalle['cantidad'] ?? 0), 3);
            if ($cantidad <= 0) {
                continue;
            }

            $filas++;
            $ruta = "detalles.{$indice}";
            $proformaDetalleId = (int) ($detalle['proforma_detalle_id'] ?? 0);
            $productoId = (int) ($detalle['producto_id'] ?? 0);

            $linea = DB::table('proforma_detalles')
                ->where('id', $proformaDetalleId)
                ->where('proforma_id', $proformaId)
                ->where('producto_id', $productoId)
                ->where('tratamiento', 'PRESTAMO')
                ->first(['id']);

            if (! $linea) {
                $validator->errors()->add(
                    "{$ruta}.proforma_detalle_id",
                    'El producto no corresponde a un préstamo de la Proforma.'
                );
                continue;
            }

            $this->validarRepisa($validator, $ruta, $detalle['repisa_id'] ?? null);
            $acumulado[$proformaDetalleId] = round(
                ($acumulado[$proformaDetalleId] ?? 0) + $cantidad,
                3
            );
        }

        foreach ($acumulado as $proformaDetalleId => $cantidadNueva) {
            $prestadoFisicamente = (float) DB::table('nota_salida_detalles as d')
                ->join('notas_salida as n', 'n.id', '=', 'd.nota_salida_id')
                ->where('d.proforma_detalle_id', $proformaDetalleId)
                ->where('d.tratamiento', 'PRESTAMO_EXTERNO')
                ->where('n.estado', 'CONFIRMADA')
                ->sum('d.cantidad');

            $yaRepuesto = (float) DB::table('proforma_prestamo_reposiciones')
                ->where('proforma_detalle_id', $proformaDetalleId)
                ->sum('cantidad');

            $pendiente = max(0, round($prestadoFisicamente - $yaRepuesto, 3));
            if ($pendiente <= 0) {
                $validator->errors()->add(
                    'detalles',
                    'Ese préstamo todavía no tiene una salida física pendiente de reposición.'
                );
            } elseif ($cantidadNueva > $pendiente + 0.0001) {
                $validator->errors()->add(
                    'detalles',
                    "La reposición supera el pendiente físico de {$pendiente}."
                );
            }
        }

        if ($filas === 0) {
            $validator->errors()->add(
                'detalles',
                'Ingresa una cantidad mayor que cero en al menos un producto.'
            );
        }
    }

    private function validarRepisa(Validator $validator, string $ruta, mixed $repisaId): void
    {
        if (empty($repisaId)) {
            $validator->errors()->add(
                "{$ruta}.repisa_id",
                'Selecciona la repisa donde se almacenará el producto.'
            );
            return;
        }

        if (! DB::table('repisas')
            ->where('id', $repisaId)
            ->where('estado', true)
            ->exists()) {
            $validator->errors()->add(
                "{$ruta}.repisa_id",
                'La repisa seleccionada no está activa.'
            );
        }
    }

    public function messages(): array
    {
        return [
            'motivo_ingreso.required' => 'Selecciona el motivo del ingreso.',
            'fecha_ingreso.required' => 'Selecciona la fecha de ingreso.',
            'fecha_ingreso.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'detalles.required' => 'Selecciona al menos un producto para ingresar.',
        ];
    }
}
