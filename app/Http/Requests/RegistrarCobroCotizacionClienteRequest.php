<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarCobroCotizacionClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cotizacion = $this->route('cotizacionCliente');
        $inicio = $cotizacion->proforma_id === null
            ? ($cotizacion->ordenOperacion?->cerrado_en?->toDateString() ?? $cotizacion->fecha_emision->toDateString())
            : $cotizacion->fecha_emision->toDateString();

        return [
            'fecha_cobro' => ['required', 'date', 'after_or_equal:'.$inicio, 'before_or_equal:today'],
            'monto' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'medio_cobro' => ['required', 'in:TRANSFERENCIA,EFECTIVO,CHEQUE,TARJETA,OTRO'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
