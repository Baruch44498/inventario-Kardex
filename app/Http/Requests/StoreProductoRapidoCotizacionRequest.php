<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductoRapidoCotizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->input('codigo'))),
            'descripcion' => trim((string) $this->input('descripcion')),
            'unidad_medida_id' => $this->input('unidad_medida_id') ?: null,
            'marca_principal_id' => $this->input('marca_principal_id') ?: null,
            'confirmar_similitud' => $this->boolean('confirmar_similitud'),
        ]);
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'required',
                'string',
                'max:50',
                Rule::unique('productos', 'codigo'),
            ],
            'descripcion' => ['required', 'string', 'max:500'],
            'unidad_medida_id' => [
                'required',
                'integer',
                Rule::exists('unidades_medida', 'id')->where('estado', true),
            ],
            'marca_principal_id' => [
                'nullable',
                'integer',
                Rule::exists('marcas', 'id')->where('estado', true),
            ],
            'confirmar_similitud' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'Ingresa el código del producto.',
            'codigo.unique' => 'Este código ya pertenece a otro producto del catálogo.',
            'descripcion.required' => 'Ingresa la descripción del producto.',
            'unidad_medida_id.required' => 'Selecciona la unidad de medida.',
            'unidad_medida_id.exists' => 'La unidad de medida seleccionada no está disponible.',
            'marca_principal_id.exists' => 'La marca seleccionada no está disponible.',
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'unidad_medida_id' => 'unidad de medida',
            'marca_principal_id' => 'marca principal',
        ];
    }
}
