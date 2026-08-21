<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $presentaciones = collect($this->input('presentaciones', []))
            ->map(fn($item): array => [
                'id' => ! empty($item['id']) ? (int) $item['id'] : null,
                'nombre' => trim((string) ($item['nombre'] ?? '')),
                'factor_conversion' => $item['factor_conversion'] ?? null,
                'es_predeterminada' => filter_var(
                    $item['es_predeterminada'] ?? false,
                    FILTER_VALIDATE_BOOL
                ),
                'estado' => filter_var($item['estado'] ?? true, FILTER_VALIDATE_BOOL),
            ])
            ->filter(fn(array $item): bool =>
            $item['nombre'] !== '' || $item['factor_conversion'] !== null)
            ->values()
            ->all();

        $this->merge([
            'codigo' => mb_strtoupper(trim((string) $this->codigo)),
            'descripcion' => trim((string) $this->descripcion),
            'activo' => $this->boolean('activo'),
            'permite_fraccionamiento' => $this->boolean('permite_fraccionamiento'),
            'id_marca_principal' => $this->filled('id_marca_principal')
                ? $this->id_marca_principal
                : null,
            'presentaciones' => $presentaciones,
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
            'permite_fraccionamiento' => ['required', 'boolean'],
            'presentaciones' => ['nullable', 'array', 'max:20'],
            'presentaciones.*.id' => ['prohibited'],
            'presentaciones.*.nombre' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'presentaciones.*.factor_conversion' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999999.999',
            ],
            'presentaciones.*.es_predeterminada' => ['required', 'boolean'],
            'presentaciones.*.estado' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $predeterminadas = collect($this->input('presentaciones', []))
                ->filter(fn(array $item): bool =>
                (bool) ($item['estado'] ?? false)
                    && (bool) ($item['es_predeterminada'] ?? false))
                ->count();

            if ($predeterminadas > 1) {
                $validator->errors()->add(
                    'presentaciones',
                    'Solo una presentación activa puede ser predeterminada.'
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código',
            'descripcion' => 'descripción',
            'id_unidad_medida' => 'unidad de medida',
            'id_marca_principal' => 'marca principal',
            'activo' => 'estado',
            'permite_fraccionamiento' => 'control de fraccionamiento',
            'presentaciones.*.nombre' => 'nombre de presentación',
            'presentaciones.*.factor_conversion' => 'factor de conversión',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'Este código ya fue utilizado. Recarga el formulario para obtener una nueva sugerencia o ingresa otro código.',
        ];
    }
}
