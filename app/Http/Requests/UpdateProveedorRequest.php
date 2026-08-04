<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ruc' => preg_replace('/\D+/', '', trim((string) $this->input('ruc'))),
            'razon_social' => $this->texto('razon_social'),
            'nombre_comercial' => $this->nullableTexto('nombre_comercial'),
            'correo' => $this->nullableMinuscula('correo'),
            'telefono' => $this->normalizarTelefono(),
            'contacto' => $this->nullableTexto('contacto'),
            'ciudad' => $this->nullableTexto('ciudad'),
            'departamento' => $this->nullableTexto('departamento'),
            'direccion' => $this->nullableTexto('direccion'),
            'estado' => $this->boolean('estado'),
        ]);
    }

    public function rules(): array
    {
        $proveedor = $this->route('proveedor');
        $proveedorId = is_object($proveedor) ? $proveedor->id : $proveedor;

        return [
            'ruc' => ['required', 'digits:11', Rule::unique('proveedores', 'ruc')->ignore($proveedorId)],
            'razon_social' => ['required', 'string', 'max:250'],
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'correo' => ['nullable', 'email:rfc', 'max:150'],
            'telefono' => ['nullable', 'regex:/^\d{1,9}$/'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'direccion' => ['nullable', 'string', 'max:350'],
            'estado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'ruc.required' => 'Ingresa el RUC del proveedor.',
            'ruc.digits' => 'El RUC debe contener exactamente 11 dígitos.',
            'ruc.unique' => 'Ese RUC ya pertenece a otro proveedor.',
            'correo.email' => 'Ingresa un correo válido con @ y dominio.',
            'telefono.regex' => 'El teléfono debe contener solo números y un máximo de 9 dígitos.',
        ];
    }

    private function texto(string $campo): string
    {
        return trim((string) $this->input($campo));
    }

    private function nullableTexto(string $campo): ?string
    {
        $valor = $this->texto($campo);

        return $valor === '' ? null : $valor;
    }

    private function nullableMinuscula(string $campo): ?string
    {
        $valor = $this->nullableTexto($campo);

        return $valor ? Str::lower($valor) : null;
    }

    private function normalizarTelefono(): ?string
    {
        $valor = preg_replace('/\D+/', '', (string) $this->input('telefono'));

        return $valor === '' ? null : $valor;
    }
}
