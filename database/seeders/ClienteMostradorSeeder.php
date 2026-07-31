<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\TipoCliente;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class ClienteMostradorSeeder extends Seeder
{
    public function run(): void
    {
        $tipoFinal = TipoCliente::query()
            ->where('codigo', 'FINAL')
            ->firstOrFail();

        $cliente = Cliente::query()->updateOrCreate(
            ['es_mostrador' => true],
            [
                'tipo_cliente_id' => $tipoFinal->id,
                'tipo_documento' => 'SIN_DOCUMENTO',
                'numero_documento' => null,
                'ruc' => null,
                'razon_social' => 'PÚBLICO GENERAL',
                'nombres' => 'PÚBLICO GENERAL',
                'apellido_paterno' => null,
                'apellido_materno' => null,
                'nombre_comercial' => 'Venta directa',
                'correo' => null,
                'telefono' => null,
                'contacto' => null,
                'estado' => true,
            ]
        );

        $vehiculo = Vehiculo::query()
            ->where('es_comodin', true)
            ->orWhere('placa', 'SIN-PLACA')
            ->orWhere('codigo_interno', 'MOSTRADOR')
            ->first();

        if (! $vehiculo) {
            $vehiculo = new Vehiculo();
        }

        $vehiculo->forceFill([
            'cliente_id' => $cliente->id,
            'placa' => 'SIN-PLACA',
            'codigo_interno' => null,
            'vin' => null,
            'marca' => null,
            'modelo' => null,
            'anio' => null,
            'color' => null,
            'descripcion' =>
            'Venta directa sin vehículo asociado',
            'procedencia' => 'MOSTRADOR',
            'es_comodin' => true,
            'estado' => true,
        ])->save();
    }
}
