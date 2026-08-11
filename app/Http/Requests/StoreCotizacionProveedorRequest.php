<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCotizacionProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $detalles = collect($this->input('detalles', []))
            ->map(function ($detalle): array {
                $modo = strtoupper(trim((string) ($detalle['descuento_modo'] ?? 'SIN_DESCUENTO')));
                $tipo = $this->nullableValor($detalle['descuento_tipo'] ?? null);
                $valor = $detalle['descuento_valor'] ?? null;

                // Si el proveedor no detalla el descuento, el precio informado
                // ya es el único dato confiable. No conservamos porcentajes ni
                // montos estimados y nunca descontamos dos veces.
                if (in_array($modo, ['SIN_DESCUENTO', 'INCLUIDO'], true)) {
                    $modo = 'SIN_DESCUENTO';
                    $tipo = null;
                    $valor = null;
                }

                return [
                    'requisicion_detalle_id' => $detalle['requisicion_detalle_id'] ?? null,
                    'producto_id' => $detalle['producto_id'] ?? null,
                    'cantidad' => $detalle['cantidad'] ?? null,
                    'precio_unitario' => $detalle['precio_unitario'] ?? null,
                    'descuento_modo' => $modo,
                    'descuento_tipo' => $tipo ? strtoupper($tipo) : null,
                    'descuento_valor' => $valor === '' ? null : $valor,
                    'igv_modo' => strtoupper(trim((string) ($detalle['igv_modo'] ?? 'AGREGAR'))),
                    'marca_ofertada' => $this->nullableValor($detalle['marca_ofertada'] ?? null),
                    'observacion' => $this->nullableValor($detalle['observacion'] ?? null),
                ];
            })
            ->values()
            ->all();

        $moneda = strtoupper(trim((string) $this->input('moneda', 'PEN')));
        $modoGlobal = strtoupper(trim((string) $this->input(
            'descuento_global_modo',
            'SIN_DESCUENTO'
        )));
        $tipoGlobal = $this->nullableCampo('descuento_global_tipo');
        $valorGlobal = $this->input('descuento_global_valor');

        if (in_array($modoGlobal, ['SIN_DESCUENTO', 'INCLUIDO'], true)) {
            $modoGlobal = 'SIN_DESCUENTO';
            $tipoGlobal = null;
            $valorGlobal = null;
        }

        $this->merge([
            'importacion_cotizacion_id' => $this->input('importacion_cotizacion_id') ?: null,
            'requisicion_id' => $this->input('requisicion_id') ?: null,
            'numero_documento' => $this->nullableCampo('numero_documento'),
            'moneda' => $moneda,
            'tipo_cambio' => $moneda === 'USD' ? $this->input('tipo_cambio') : null,
            'descuento_global_modo' => $modoGlobal,
            'descuento_global_tipo' => $tipoGlobal ? strtoupper($tipoGlobal) : null,
            'descuento_global_valor' => $valorGlobal === '' ? null : $valorGlobal,
            'condiciones_pago' => $this->nullableCampo('condiciones_pago'),
            'condiciones_entrega' => $this->nullableCampo('condiciones_entrega'),
            'observacion' => $this->nullableCampo('observacion'),
            'detalles' => $detalles,
        ]);
    }

    public function rules(): array
    {
        return [
            'importacion_cotizacion_id' => ['nullable', 'integer', Rule::exists('importaciones_cotizacion_proveedor', 'id')],
            'proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')],
            'requisicion_id' => [
                'nullable',
                'integer',
                Rule::exists('requisiciones', 'id')->where(
                    fn($query) => $query->whereIn('estado', ['ENVIADA', 'EN_REVISION', 'COTIZANDO', 'ATENDIDA'])
                ),
            ],
            'numero_documento' => ['nullable', 'string', 'max:60'],
            'fecha_cotizacion' => ['required', 'date'],
            'fecha_validez' => ['nullable', 'date', 'after_or_equal:fecha_cotizacion'],
            'moneda' => ['required', Rule::in(['PEN', 'USD'])],
            'tipo_cambio' => [
                'nullable',
                'required_if:moneda,USD',
                'numeric',
                'gt:0',
                'max:999.999999',
            ],
            'descuento_global_modo' => [
                'required',
                Rule::in(['SIN_DESCUENTO', 'APLICAR']),
            ],
            'descuento_global_tipo' => [
                'nullable',
                Rule::in(['PORCENTAJE', 'MONTO']),
            ],
            'descuento_global_valor' => [
                'nullable',
                'numeric',
                'gte:0',
                'max:9999999999.9999',
            ],
            'condiciones_pago' => ['nullable', 'string', 'max:500'],
            'condiciones_entrega' => ['nullable', 'string', 'max:500'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.requisicion_detalle_id' => [
                'nullable',
                'integer',
                Rule::exists('requisicion_detalles', 'id'),
            ],
            'detalles.*.producto_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('productos', 'id'),
            ],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:99999999999.999'],
            'detalles.*.precio_unitario' => [
                'required',
                'numeric',
                'gte:0',
                'max:9999999999.9999',
            ],
            'detalles.*.descuento_modo' => [
                'required',
                Rule::in(['SIN_DESCUENTO', 'APLICAR']),
            ],
            'detalles.*.descuento_tipo' => [
                'nullable',
                Rule::in(['PORCENTAJE', 'MONTO']),
            ],
            'detalles.*.descuento_valor' => [
                'nullable',
                'numeric',
                'gte:0',
                'max:9999999999.9999',
            ],
            'detalles.*.igv_modo' => [
                'required',
                Rule::in(['INCLUIDO', 'AGREGAR', 'NO_APLICA']),
            ],
            'detalles.*.marca_ofertada' => ['nullable', 'string', 'max:120'],
            'detalles.*.observacion' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $detalles = $this->input('detalles', []);

            foreach ($detalles as $indice => $detalle) {
                $modo = $detalle['descuento_modo'] ?? 'SIN_DESCUENTO';
                $tipo = $detalle['descuento_tipo'] ?? null;
                $valor = $detalle['descuento_valor'] ?? null;
                $precio = (float) ($detalle['precio_unitario'] ?? 0);

                $this->validarReferenciaDescuento(
                    $validator,
                    "detalles.{$indice}.descuento",
                    $modo,
                    $tipo,
                    $valor,
                    $precio
                );
            }

            $modoGlobal = $this->input('descuento_global_modo');
            $tipoGlobal = $this->input('descuento_global_tipo');
            $valorGlobal = $this->input('descuento_global_valor');

            $this->validarReferenciaDescuento(
                $validator,
                'descuento_global',
                $modoGlobal,
                $tipoGlobal,
                $valorGlobal
            );

            if (
                $modoGlobal === 'APLICAR'
                && $tipoGlobal === 'MONTO'
                && is_numeric($valorGlobal)
                && (float) $valorGlobal > $this->estimarSubtotal($detalles)
            ) {
                $validator->errors()->add(
                    'descuento_global_valor',
                    'El descuento general no puede superar el subtotal sin IGV.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'proveedor_id.required' => 'Selecciona el proveedor.',
            'tipo_cambio.required_if' => 'Ingresa el tipo de cambio para una cotización en dólares.',
            'detalles.required' => 'Registra al menos un producto cotizado.',
            'detalles.min' => 'Registra al menos un producto cotizado.',
            'detalles.*.producto_id.required' => 'Selecciona el producto de cada fila.',
            'detalles.*.producto_id.distinct' => 'Un producto no puede repetirse dentro de la misma cotización.',
            'detalles.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'detalles.*.precio_unitario.required' => 'Ingresa el precio unitario.',
            'detalles.*.igv_modo.required' => 'Indica cómo se presenta el IGV en cada producto.',
        ];
    }

    private function validarReferenciaDescuento(
        Validator $validator,
        string $campo,
        ?string $modo,
        ?string $tipo,
        mixed $valor,
        ?float $precioUnitario = null
    ): void {
        if ($modo === 'APLICAR' && ! $tipo) {
            $validator->errors()->add(
                "{$campo}_tipo",
                'Selecciona si el descuento es porcentaje o monto.'
            );
        }

        if ($modo === 'APLICAR' && ($valor === null || $valor === '')) {
            $validator->errors()->add(
                "{$campo}_valor",
                'Ingresa el valor del descuento que debe aplicarse.'
            );
        }

        if ($tipo === 'PORCENTAJE' && is_numeric($valor) && (float) $valor > 100) {
            $validator->errors()->add(
                "{$campo}_valor",
                'El porcentaje de descuento no puede superar 100 %.'
            );
        }

        if (
            $modo === 'APLICAR'
            && $tipo === 'MONTO'
            && $precioUnitario !== null
            && is_numeric($valor)
            && (float) $valor > $precioUnitario
        ) {
            $validator->errors()->add(
                "{$campo}_valor",
                'El descuento por unidad no puede superar el precio unitario.'
            );
        }
    }

    private function estimarSubtotal(array $detalles): float
    {
        $subtotal = 0.0;

        foreach ($detalles as $detalle) {
            $cantidad = (float) ($detalle['cantidad'] ?? 0);
            $precio = (float) ($detalle['precio_unitario'] ?? 0);

            if (($detalle['descuento_modo'] ?? null) === 'APLICAR') {
                $valor = (float) ($detalle['descuento_valor'] ?? 0);
                $precio -= ($detalle['descuento_tipo'] ?? null) === 'PORCENTAJE'
                    ? $precio * ($valor / 100)
                    : $valor;
            }

            $precio = max(0, $precio);

            if (($detalle['igv_modo'] ?? null) === 'INCLUIDO') {
                $precio /= 1.18;
            }

            $subtotal += $cantidad * $precio;
        }

        return round($subtotal, 4);
    }

    private function nullableCampo(string $campo): ?string
    {
        return $this->nullableValor($this->input($campo));
    }

    private function nullableValor(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
