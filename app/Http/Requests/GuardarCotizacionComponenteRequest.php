<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GuardarCotizacionComponenteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cliente_direccion_id' => $this->filled('cliente_direccion_id')
                ? $this->input('cliente_direccion_id')
                : null,
            'vehiculo_id' => $this->filled('vehiculo_id')
                ? $this->input('vehiculo_id')
                : null,
            'tipo_cambio_comparacion' => $this->filled('tipo_cambio_comparacion')
                ? $this->input('tipo_cambio_comparacion')
                : null,
            'descripcion_componente' => trim((string) $this->input('descripcion_componente')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_orden_id' => [
                'required',
                'integer',
                Rule::exists('tipos_orden', 'id')->where(
                    fn($query) => $query
                        ->where('estado', true)
                        ->whereIn('codigo', ['OM', 'OS', 'OP'])
                ),
            ],
            'descripcion_componente' => ['required', 'string', 'min:5', 'max:500'],
            'cliente_direccion_id' => ['nullable', 'integer', 'exists:cliente_direcciones,id'],
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
            'tipo_cambio_comparacion' => ['nullable', 'numeric', 'gte:0.1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $cotizacion = $this->route('cotizacionCliente')
                ?: $this->route('componente')?->cotizacionCliente;
            if (! $cotizacion) {
                return;
            }

            $tipo = DB::table('tipos_orden')
                ->where('id', $this->integer('tipo_orden_id'))
                ->value('codigo');
            $vehiculoId = $this->integer('vehiculo_id') ?: null;
            $direccionId = $this->integer('cliente_direccion_id') ?: null;

            if ($tipo === 'OM' && ! $vehiculoId) {
                $validator->errors()->add('vehiculo_id', 'El mantenimiento requiere un vehículo.');
            }
            if ($tipo === 'OP' && $vehiculoId) {
                $validator->errors()->add('vehiculo_id', 'Producción no se vincula con un vehículo existente.');
            }
            if ($direccionId) {
                $valida = DB::table('cliente_direcciones')
                    ->where('id', $direccionId)
                    ->where('cliente_id', $cotizacion->cliente_id)
                    ->where('estado', true)
                    ->exists();
                if (! $valida) {
                    $validator->errors()->add('cliente_direccion_id', 'La ubicación no pertenece al cliente.');
                }
            }

            if ($vehiculoId) {
                $vehiculo = DB::table('vehiculos')->where('id', $vehiculoId)->first();
                if (
                    ! $vehiculo || ! $vehiculo->estado
                    || ($vehiculo->cliente_id !== null
                        && (int) $vehiculo->cliente_id !== (int) $cotizacion->cliente_id)
                ) {
                    $validator->errors()->add('vehiculo_id', 'El vehículo no pertenece al cliente.');
                }
            }
        }];
    }
}
