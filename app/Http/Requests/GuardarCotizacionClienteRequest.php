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
        $esVentaDirecta = $cotizacion?->proforma_id !== null;
        $tipoOrdenId = $this->input('tipo_orden_id');

        if ($esVentaDirecta) {
            $tipoOrdenId = DB::table('tipos_orden')
                ->where('codigo', 'OV')
                ->where('estado', true)
                ->value('id');
        }

        $descripcionTrabajo = trim((string) $this->input('descripcion_trabajo'));

        if ($esVentaDirecta && $descripcionTrabajo === '') {
            $descripcionTrabajo = trim((string) (
                $cotizacion?->descripcion_trabajo
                ?: $cotizacion?->observacion
                ?: 'Venta directa de productos de Almacén'
            ));
        }

        $this->merge([
            'moneda' => $moneda,
            'tipo_cambio' => $moneda === 'USD'
                ? $this->input('tipo_cambio')
                : null,
            'tipo_orden_id' => $tipoOrdenId,
            'cliente_direccion_id' => $this->filled('cliente_direccion_id')
                ? $this->input('cliente_direccion_id')
                : null,
            'vehiculo_id' => $this->filled('vehiculo_id')
                ? $this->input('vehiculo_id')
                : null,
            'descripcion_trabajo' => $descripcionTrabajo,
        ]);
    }

    public function rules(): array
    {
        $codigosPermitidos = $this->cotizacionDeRuta()?->proforma_id !== null
            ? ['OV']
            : ['OM', 'OS', 'OP'];

        return [
            'cliente_id' => [
                'required',
                'integer',
                Rule::exists('clientes', 'id')->where('estado', true),
            ],
            'fecha_emision' => ['required', 'date'],
            'fecha_validez' => ['nullable', 'date', 'after_or_equal:fecha_emision'],
            'tipo_orden_id' => [
                'required',
                'integer',
                Rule::exists('tipos_orden', 'id')->where(
                    fn($query) => $query
                        ->where('estado', true)
                        ->whereIn('codigo', $codigosPermitidos)
                ),
            ],
            'cliente_direccion_id' => [
                'nullable',
                'integer',
                'exists:cliente_direcciones,id',
            ],
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
            'descripcion_trabajo' => ['required', 'string', 'min:5', 'max:500'],
            'moneda' => ['required', Rule::in(['PEN', 'USD'])],
            'tipo_cambio' => ['nullable', 'numeric', 'gt:0', 'required_if:moneda,USD'],
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
                $clienteId = $this->integer('cliente_id') ?: null;
                $direccionId = $this->integer('cliente_direccion_id') ?: null;
                $vehiculoId = $this->integer('vehiculo_id') ?: null;
                $tipoCodigo = DB::table('tipos_orden')
                    ->where('id', $this->integer('tipo_orden_id'))
                    ->value('codigo');

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

                if ($tipoCodigo === 'OM' && ! $vehiculoId) {
                    $validator->errors()->add(
                        'vehiculo_id',
                        'Selecciona el vehículo que recibirá el mantenimiento.'
                    );
                }

                if (in_array($tipoCodigo, ['OP', 'OV'], true) && $vehiculoId) {
                    $validator->errors()->add(
                        'vehiculo_id',
                        $tipoCodigo === 'OP'
                            ? 'Una orden de producción crea una unidad nueva y no se vincula con un vehículo existente.'
                            : 'Una orden de venta no necesita vehículo.'
                    );
                }

                if ($vehiculoId) {
                    $vehiculo = DB::table('vehiculos')
                        ->where('id', $vehiculoId)
                        ->first(['cliente_id', 'estado']);

                    if (! $vehiculo || ! $vehiculo->estado) {
                        $validator->errors()->add(
                            'vehiculo_id',
                            'El vehículo seleccionado no está disponible.'
                        );
                    } elseif (
                        $vehiculo->cliente_id !== null
                        && (int) $vehiculo->cliente_id !== (int) $clienteId
                    ) {
                        $validator->errors()->add(
                            'vehiculo_id',
                            'El vehículo pertenece a otro cliente.'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'detalles.required' => 'La cotización debe conservar al menos un producto.',
            'detalles.*.producto_id.distinct' => 'Un producto no puede repetirse en la cotización.',
            'detalles.*.precio_unitario.gt' => 'Todos los productos necesitan un precio mayor que cero antes de guardar.',
            'tipo_cambio.required_if' => 'Registra el tipo de cambio cuando la moneda sea USD.',
            'tipo_orden_id.required' => 'Selecciona si cotizarás mantenimiento, servicio o producción.',
            'tipo_orden_id.exists' => 'El tipo de trabajo no corresponde al flujo de esta cotización.',
            'descripcion_trabajo.required' => 'Describe el trabajo que se realizará.',
            'descripcion_trabajo.min' => 'La descripción del trabajo debe tener al menos 5 caracteres.',
        ];
    }

    private function cotizacionDeRuta(): ?CotizacionCliente
    {
        $cotizacion = $this->route('cotizacionCliente');

        return $cotizacion instanceof CotizacionCliente ? $cotizacion : null;
    }
}
