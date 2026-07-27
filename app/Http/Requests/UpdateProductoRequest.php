<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->codigo)),
            'descripcion' => trim((string) $this->descripcion),
            'activo' => $this->boolean('activo'),
            'id_marca_principal' => $this->filled('id_marca_principal')
                ? $this->id_marca_principal
                : null,
        ]);
    }

    public function rules(): array
    {
        $productoId = $this->route('producto');

        return [
            'codigo' => [
                'required',
                'string',
                'max:50',
                Rule::unique('productos', 'codigo')
                    ->ignore($productoId, 'id'),
            ],
            'descripcion' => ['required', 'string', 'max:500'],
            'id_unidad_medida' => [
                'required',
                'integer',
                Rule::exists('unidades_medida', 'id')
                    ->where('estado', true),
            ],
            'id_marca_principal' => [
                'nullable',
                'integer',
                Rule::exists('marcas', 'id')
                    ->where('estado', true),
            ],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'id_unidad_medida' => 'unidad de medida',
            'id_marca_principal' => 'marca principal',
            'activo' => 'estado',
        ];
    }
}
