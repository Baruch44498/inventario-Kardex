<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrdenOperacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cliente_id' => $this->filled('cliente_id') ? $this->input('cliente_id') : null,
            'cliente_direccion_id' => $this->filled('cliente_direccion_id') ? $this->input('cliente_direccion_id') : null,
            'vehiculo_id' => $this->filled('vehiculo_id') ? $this->input('vehiculo_id') : null,
            'descripcion' => trim((string) $this->input('descripcion')),
        ]);
    }

    public function rules(): array
    {
        return [
            'tipo_orden_id' => [
                'required',
                'integer',
                Rule::exists('tipos_orden', 'id')->where(
                    fn ($query) => $query->where('estado', true)
                ),
            ],
            'fecha_apertura' => ['required', 'date', 'before_or_equal:today'],
            'cliente_id' => [
                'nullable',
                'integer',
                Rule::exists('clientes', 'id')->where(
                    fn ($query) => $query->where('estado', true)
                ),
            ],
            'cliente_direccion_id' => ['nullable', 'integer', 'exists:cliente_direcciones,id'],
            'vehiculo_id' => ['nullable', 'integer', 'exists:vehiculos,id'],
            'descripcion' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $clienteId = $this->integer('cliente_id') ?: null;
                $direccionId = $this->integer('cliente_direccion_id') ?: null;
                $vehiculoId = $this->integer('vehiculo_id') ?: null;

                if ($direccionId) {
                    $direccion = DB::table('cliente_direcciones')
                        ->where('id', $direccionId)
                        ->first(['cliente_id', 'estado']);

                    if (! $clienteId || ! $direccion || ! $direccion->estado
                        || (int) $direccion->cliente_id !== (int) $clienteId) {
                        $validator->errors()->add(
                            'cliente_direccion_id',
                            'La dirección debe estar activa y pertenecer al cliente seleccionado.'
                        );
                    }
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
                    } elseif ($vehiculo->cliente_id !== null
                        && (int) $vehiculo->cliente_id !== (int) $clienteId) {
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
            'tipo_orden_id.required' => 'Selecciona el tipo de orden.',
            'fecha_apertura.required' => 'Selecciona la fecha de apertura.',
            'fecha_apertura.before_or_equal' => 'La fecha de apertura no puede ser futura.',
            'descripcion.required' => 'Describe el trabajo u operación.',
            'descripcion.min' => 'La descripción debe tener al menos 5 caracteres.',
        ];
    }
}
