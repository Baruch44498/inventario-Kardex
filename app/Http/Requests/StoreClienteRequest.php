<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tipoDocumento = strtoupper(
            trim((string) $this->input('tipo_documento'))
        );

        $numeroDocumento = strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                trim((string) $this->input('numero_documento'))
            )
        );

        $nombres = $this->nullableTexto('nombres');

        if ($tipoDocumento === 'SIN_DOCUMENTO' && ! $nombres) {
            $nombres = 'PÚBLICO GENERAL';
        }

        $razonSocial = match ($tipoDocumento) {
            'RUC' => $this->nullableTexto('razon_social'),

            'DNI' => $this->unirNombre([
                $nombres,
                $this->nullableTexto('apellido_paterno'),
                $this->nullableTexto('apellido_materno'),
            ]),

            'CE', 'SIN_DOCUMENTO' => $nombres,

            default => $this->nullableTexto('razon_social'),
        };

        $this->merge([
            'tipo_documento' => $tipoDocumento,
            'numero_documento' =>
            $tipoDocumento === 'SIN_DOCUMENTO'
                || $numeroDocumento === ''
                ? null
                : $numeroDocumento,
            'ruc' => $tipoDocumento === 'RUC'
                ? ($numeroDocumento ?: null)
                : null,
            'razon_social' => $razonSocial,
            'nombres' => $nombres,
            'apellido_paterno' =>
            $tipoDocumento === 'DNI'
                ? $this->nullableTexto('apellido_paterno')
                : null,
            'apellido_materno' =>
            $tipoDocumento === 'DNI'
                ? $this->nullableTexto('apellido_materno')
                : null,
            'nombre_comercial' =>
            $tipoDocumento === 'RUC'
                ? $this->nullableTexto('nombre_comercial')
                : null,
            'correo' => $this->nullableMinuscula('correo'),
            'telefono' => $this->normalizarTelefono(),
            'contacto' => $this->nullableTexto('contacto'),
            'estado' => $this->boolean('estado'),
        ]);
    }

    public function rules(): array
    {
        return [
            'tipo_cliente_id' => [
                'required',
                'integer',
                Rule::exists('tipos_cliente', 'id')
                    ->where(
                        fn($query) => $query->where('estado', true)
                    ),
            ],
            'tipo_documento' => [
                'required',
                Rule::in(['RUC', 'DNI', 'CE', 'SIN_DOCUMENTO']),
            ],
            'numero_documento' => [
                Rule::requiredIf(
                    fn() => $this->input('tipo_documento')
                        !== 'SIN_DOCUMENTO'
                ),
                'nullable',
                'string',
                'max:12',
                Rule::unique('clientes', 'numero_documento'),
                $this->reglaFormatoDocumento(),
            ],
            'ruc' => ['nullable', 'digits:11'],
            'razon_social' => ['required', 'string', 'max:250'],
            'nombres' => [
                Rule::requiredIf(
                    fn() => in_array(
                        $this->input('tipo_documento'),
                        ['DNI', 'CE', 'SIN_DOCUMENTO'],
                        true
                    )
                ),
                'nullable',
                'string',
                'max:250',
            ],
            'apellido_paterno' => [
                Rule::requiredIf(
                    fn() => $this->input('tipo_documento') === 'DNI'
                ),
                'nullable',
                'string',
                'max:100',
            ],
            'apellido_materno' => [
                Rule::requiredIf(
                    fn() => $this->input('tipo_documento') === 'DNI'
                ),
                'nullable',
                'string',
                'max:100',
            ],
            'nombre_comercial' => ['nullable', 'string', 'max:250'],
            'correo' => [
                'nullable',
                'email:rfc',
                'max:150',
            ],
            'telefono' => [
                'nullable',
                'regex:/^\d{1,9}$/',
            ],
            'contacto' => ['nullable', 'string', 'max:150'],
            'estado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_documento.required' =>
            'Ingresa el número de documento.',
            'numero_documento.unique' =>
            'Ese número de documento ya está registrado.',
            'nombres.required' =>
            'Ingresa los nombres del cliente.',
            'apellido_paterno.required' =>
            'Ingresa el apellido paterno.',
            'apellido_materno.required' =>
            'Ingresa el apellido materno.',
            'telefono.regex' =>
            'El teléfono debe contener únicamente números y un máximo de 9 dígitos.',
            'correo.email' =>
            'Ingresa un correo válido que incluya @ y un dominio.',
        ];
    }

    private function reglaFormatoDocumento(): \Closure
    {
        return function (
            string $attribute,
            mixed $value,
            \Closure $fail
        ): void {
            $tipo = $this->input('tipo_documento');
            $documento = (string) $value;

            if (
                $tipo === 'RUC'
                && ! preg_match('/^\d{11}$/', $documento)
            ) {
                $fail('El RUC debe contener exactamente 11 dígitos.');
            }

            if (
                $tipo === 'DNI'
                && ! preg_match('/^\d{8}$/', $documento)
            ) {
                $fail('El DNI debe contener exactamente 8 dígitos.');
            }

            if (
                $tipo === 'CE'
                && ! preg_match('/^[A-Z0-9]{9,12}$/', $documento)
            ) {
                $fail(
                    'El carné de extranjería debe contener entre 9 y 12 caracteres alfanuméricos.'
                );
            }

            if ($tipo === 'SIN_DOCUMENTO' && filled($value)) {
                $fail(
                    'Un cliente sin documento no debe tener número registrado.'
                );
            }
        };
    }

    private function unirNombre(array $partes): ?string
    {
        $nombre = collect($partes)
            ->filter(fn($valor) => filled($valor))
            ->implode(' ');

        return $nombre !== '' ? $nombre : null;
    }

    private function nullableTexto(string $campo): ?string
    {
        $valor = trim((string) $this->input($campo));

        return $valor === '' ? null : $valor;
    }

    private function normalizarTelefono(): ?string
    {
        $valorOriginal = trim((string) $this->input('telefono'));

        if ($valorOriginal === '') {
            return null;
        }

        $telefono = preg_replace('/\D+/', '', $valorOriginal);

        return $telefono === '' ? null : $telefono;
    }

    private function nullableMinuscula(string $campo): ?string
    {
        $valor = $this->nullableTexto($campo);

        return $valor ? Str::lower($valor) : null;
    }
}
