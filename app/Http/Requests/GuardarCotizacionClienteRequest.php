<?php

namespace App\Http\Requests;

use App\Models\CotizacionCliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarCotizacionClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $moneda = strtoupper(trim((string) $this->input('moneda')));
        $cotizacion = $this->cotizacionDeRuta();
        $esProformaAlmacen = $cotizacion?->proforma_id !== null;
        $descripcionTrabajo = trim((string) $this->input('descripcion_trabajo'));
        $tipoOrdenVentaId = DB::table('tipos_orden')
            ->where('codigo', 'OV')
            ->where('estado', true)
            ->value('id');

        if ($esProformaAlmacen && $descripcionTrabajo === '') {
            $descripcionTrabajo = trim((string) (
                $cotizacion?->descripcion_trabajo
                ?: $cotizacion?->observacion
                ?: 'Productos retirados directamente de Almacén'
            ));
        }

        $this->merge([
            'moneda' => $moneda,
            'tipo_cambio' => $moneda === 'USD'
                ? $this->input('tipo_cambio')
                : null,
            'tipo_orden_id' => $esProformaAlmacen
                ? null
                : $tipoOrdenVentaId,
            'cliente_direccion_id' => $this->filled('cliente_direccion_id')
                ? $this->input('cliente_direccion_id')
                : null,
            'vehiculo_id' => null,
            'descripcion_trabajo' => $descripcionTrabajo,
            'tipo_cambio_comparacion' => $this->filled('tipo_cambio_comparacion')
                ? $this->input('tipo_cambio_comparacion')
                : null,
        ]);
    }

    public function rules(): array
    {
        $esProformaAlmacen = $this->cotizacionDeRuta()?->proforma_id !== null;
        return [
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('clientes', 'id')->where('estado', true),
            ],
            'fecha_emision' => ['required', 'date'],
            'fecha_validez' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'tipo_orden_id' => $esProformaAlmacen
                ? ['nullable']
                : [
                    'required',
                    'integer',
                    Rule::exists('tipos_orden', 'id')->where(
                        fn($query) => $query
                            ->where('estado', true)
                            ->where('codigo', 'OV')
                    ),
                ],
            'cliente_direccion_id' => [
                'nullable',
                'integer',
                'exists:cliente_direcciones,id',
            ],
            'vehiculo_id' => ['nullable'],
            'descripcion_trabajo' => $esProformaAlmacen
                ? ['nullable', 'string', 'max:500']
                : ['required', 'string', 'min:5', 'max:500'],
            'moneda' => ['required', Rule::in(['PEN', 'USD'])],
            'tipo_cambio' => ['nullable', 'numeric', 'gt:0', 'required_if:moneda,USD'],
            'tipo_cambio_comparacion' => ['nullable', 'numeric', 'gte:0.1', 'max:100'],
            'condiciones_pago' => ['nullable', 'string', 'max:500'],
            'condiciones_entrega' => ['nullable', 'string', 'max:500'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('productos', 'id')->where('estado', true),
            ],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
            'detalles.*.igv_modo' => [
                'required',
                Rule::in(['INCLUIDO', 'AGREGAR', 'NO_APLICA']),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->cotizacionDeRuta()?->proforma_id !== null) {
                    return;
                }

                $clienteId = $this->integer('cliente_id') ?: null;
                $direccionId = $this->integer('cliente_direccion_id') ?: null;

                if ($direccionId) {
                    $direccion = DB::table('cliente_direcciones')
                        ->where('id', $direccionId)
                        ->first(['cliente_id', 'estado']);

                    if (
                        ! $direccion || ! $direccion->estado
                        || (int) $direccion->cliente_id !== (int) $clienteId
                    ) {
                        $validator->errors()->add(
                            'cliente_direccion_id',
                            'La ubicación debe estar activa y pertenecer al cliente seleccionado.'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'detalles.required' => 'La cotización debe conservar al menos un producto de venta.',
            'detalles.*.producto_id.distinct' => 'Un producto no puede repetirse en la cotización.',
            'detalles.*.precio_unitario.gt' => 'Todos los productos de venta necesitan un precio mayor que cero antes de guardar.',
            'tipo_cambio.required_if' => 'Registra el tipo de cambio cuando la moneda sea USD.',
            'tipo_orden_id.required' => 'No existe un tipo de Orden de Venta activo.',
            'tipo_orden_id.exists' => 'El tipo Orden de Venta no está disponible.',
            'descripcion_trabajo.required' => 'Describe la venta o el pedido del cliente.',
            'descripcion_trabajo.min' => 'La descripción debe tener al menos 5 caracteres.',
        ];
    }

    private function cotizacionDeRuta(): ?CotizacionCliente
    {
        $cotizacion = $this->route('cotizacionCliente');

        return $cotizacion instanceof CotizacionCliente ? $cotizacion : null;
    }
}
