<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarPagoFacturaProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $emision = $this->route('facturaProveedor')->fecha_emision->toDateString();

        return [
            'fecha_pago' => ['required', 'date', 'after_or_equal:'.$emision, 'before_or_equal:today'],
            'monto' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'medio_pago' => ['required', 'in:TRANSFERENCIA,EFECTIVO,CHEQUE,TARJETA,OTRO'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
