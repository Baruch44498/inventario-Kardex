<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarCotizacionComponenteRequest;
use App\Models\ClienteDireccion;
use App\Models\CotizacionCliente;
use App\Models\CotizacionComponente;
use App\Models\TipoOrden;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CotizacionComponenteController extends Controller
{
    public function show(CotizacionCliente $cotizacionCliente): View
    {
        abort_if($cotizacionCliente->proforma_id !== null, 404);

        $cotizacionCliente->load([
            'componentes.tipoOrden',
            'componentes.clienteDireccion',
            'componentes.vehiculo',
            'componentes.ordenOperacion',
            'detalles.producto',
            'detalles.componente.tipoOrden',
            'presupuestos' => fn($query) => $query
                ->where('estado', 'VIGENTE')
                ->with('componente.tipoOrden'),
        ]);

        return view('cotizaciones_cliente.componentes', [
            'cotizacion' => $cotizacionCliente,
            'tipos' => TipoOrden::query()
                ->where('estado', true)
                ->whereIn('codigo', ['OM', 'OS', 'OP'])
                ->orderBy('codigo')
                ->get(),
            'direcciones' => ClienteDireccion::query()
                ->where('cliente_id', $cotizacionCliente->cliente_id)
                ->where('estado', true)
                ->orderByDesc('es_principal')
                ->get(),
            'vehiculos' => Vehiculo::query()
                ->where('estado', true)
                ->where(fn($query) => $query
                    ->where('cliente_id', $cotizacionCliente->cliente_id)
                    ->orWhereNull('cliente_id'))
                ->orderBy('placa')
                ->get(),
        ]);
    }

    public function store(
        GuardarCotizacionComponenteRequest $request,
        CotizacionCliente $cotizacionCliente
    ): RedirectResponse {
        $this->validarEditable($cotizacionCliente);

        DB::transaction(function () use ($request, $cotizacionCliente): void {
            $cotizacion = CotizacionCliente::query()
                ->lockForUpdate()
                ->findOrFail($cotizacionCliente->id);
            $this->validarEditable($cotizacion);
            $secuencia = ((int) $cotizacion->componentes()->max('orden_secuencia')) + 1;
            $cotizacion->componentes()->create([
                ...$request->validated(),
                'orden_secuencia' => $secuencia,
            ]);
        });
        $this->sincronizarCompatibilidad($cotizacionCliente);

        return back()->with('success', 'Componente agregado. Ya puedes asignarle productos y costos internos.');
    }

    public function update(
        GuardarCotizacionComponenteRequest $request,
        CotizacionComponente $componente
    ): RedirectResponse {
        $componente->load('cotizacionCliente');
        $this->validarEditable($componente->cotizacionCliente);
        abort_if($componente->orden_operacion_id !== null, 422, 'El componente ya generó una orden.');

        $datos = $request->validated();
        if ((int) $datos['tipo_orden_id'] !== (int) $componente->tipo_orden_id) {
            throw ValidationException::withMessages([
                'tipo_orden_id' => 'El tipo OM, OS u OP queda definido al crear el componente y ya no puede modificarse.',
            ]);
        }

        // El tipo identifica la orden futura. Aunque el navegador envíe el
        // campo oculto, el servidor nunca permite cambiarlo al editar.
        unset($datos['tipo_orden_id']);
        $componente->update($datos);
        $this->sincronizarCompatibilidad($componente->cotizacionCliente);

        return back()->with('success', 'Componente actualizado.');
    }

    public function destroy(CotizacionComponente $componente): RedirectResponse
    {
        $componente->load('cotizacionCliente');
        $this->validarEditable($componente->cotizacionCliente);

        if ($componente->cotizacionCliente->componentes()->count() <= 1) {
            return back()->with('error', 'La cotización operativa debe conservar al menos un componente.');
        }
        if (
            $componente->orden_operacion_id !== null
            || $componente->detalles()->exists()
            || $componente->presupuestos()->where('estado', 'VIGENTE')->exists()
        ) {
            return back()->with(
                'error',
                'Reasigna primero los productos y costos de este componente antes de eliminarlo.'
            );
        }

        $cotizacion = $componente->cotizacionCliente;
        $componente->delete();
        $this->resecuenciar($cotizacion);
        $this->sincronizarCompatibilidad($cotizacion);

        return back()->with('success', 'Componente eliminado.');
    }

    public function asignar(Request $request, CotizacionCliente $cotizacionCliente): RedirectResponse
    {
        $this->validarEditable($cotizacionCliente);
        $datos = $request->validate([
            'detalles' => ['nullable', 'array'],
            'detalles.*' => ['required', 'array:detalle_id,componente_id'],
            'detalles.*.detalle_id' => [
                'required',
                'integer',
                'distinct',
                'exists:cotizacion_cliente_detalles,id',
            ],
            'detalles.*.componente_id' => [
                'required',
                'integer',
                'exists:cotizacion_componentes,id',
            ],
            'presupuestos' => ['nullable', 'array'],
            'presupuestos.*' => ['required', 'array:presupuesto_id,componente_id'],
            'presupuestos.*.presupuesto_id' => [
                'required',
                'integer',
                'distinct',
                'exists:cotizacion_presupuestos,id',
            ],
            'presupuestos.*.componente_id' => [
                'required',
                'integer',
                'exists:cotizacion_componentes,id',
            ],
        ]);

        $asignacionesDetalles = collect($datos['detalles'] ?? []);
        $asignacionesPresupuestos = collect($datos['presupuestos'] ?? []);

        DB::transaction(function () use (
            $cotizacionCliente,
            $asignacionesDetalles,
            $asignacionesPresupuestos
        ): void {
            $cotizacion = CotizacionCliente::query()
                ->with(['componentes', 'detalles', 'presupuestos'])
                ->lockForUpdate()
                ->findOrFail($cotizacionCliente->id);
            $idsComponentes = $cotizacion->componentes->pluck('id')->map(fn($id) => (int) $id);
            $idsDetalles = $cotizacion->detalles->pluck('id')->map(fn($id) => (int) $id)->sort()->values();
            $idsDetallesRecibidos = $asignacionesDetalles
                ->pluck('detalle_id')
                ->map(fn($id) => (int) $id)
                ->sort()
                ->values();
            $presupuestosVigentes = $cotizacion->presupuestos->where('estado', 'VIGENTE');
            $idsPresupuestos = $presupuestosVigentes->pluck('id')->map(fn($id) => (int) $id)->sort()->values();
            $idsPresupuestosRecibidos = $asignacionesPresupuestos
                ->pluck('presupuesto_id')
                ->map(fn($id) => (int) $id)
                ->sort()
                ->values();

            if ($idsDetalles->all() !== $idsDetallesRecibidos->all()) {
                throw ValidationException::withMessages([
                    'detalles' => 'La asignación debe incluir exactamente todos los productos de esta cotización.',
                ]);
            }
            if ($idsPresupuestos->all() !== $idsPresupuestosRecibidos->all()) {
                throw ValidationException::withMessages([
                    'presupuestos' => 'La asignación debe incluir exactamente todos los costos vigentes.',
                ]);
            }

            foreach ($asignacionesDetalles as $asignacion) {
                $detalleId = (int) $asignacion['detalle_id'];
                $componenteId = (int) $asignacion['componente_id'];
                if (! $idsComponentes->contains($componenteId)) {
                    throw ValidationException::withMessages([
                        'detalles' => 'Todos los productos deben asignarse a un componente de esta cotización.',
                    ]);
                }
                DB::table('cotizacion_cliente_detalles')
                    ->where('id', $detalleId)
                    ->where('cotizacion_cliente_id', $cotizacion->id)
                    ->update([
                        'componente_id' => $componenteId,
                        'updated_at' => now(),
                    ]);
            }

            foreach ($asignacionesPresupuestos as $asignacion) {
                $presupuestoId = (int) $asignacion['presupuesto_id'];
                $componenteId = (int) $asignacion['componente_id'];
                if (! $idsComponentes->contains($componenteId)) {
                    throw ValidationException::withMessages([
                        'presupuestos' => 'Todos los costos vigentes deben asignarse a un componente.',
                    ]);
                }
                DB::table('cotizacion_presupuestos')
                    ->where('id', $presupuestoId)
                    ->where('cotizacion_cliente_id', $cotizacion->id)
                    ->where('estado', 'VIGENTE')
                    ->update([
                        'componente_id' => $componenteId,
                        'updated_at' => now(),
                    ]);
            }

            $componentesPersistidos = DB::table('cotizacion_cliente_detalles')
                ->where('cotizacion_cliente_id', $cotizacion->id)
                ->pluck('componente_id', 'id');
            foreach ($asignacionesDetalles as $asignacion) {
                if (
                    (int) ($componentesPersistidos[(int) $asignacion['detalle_id']] ?? 0)
                    !== (int) $asignacion['componente_id']
                ) {
                    throw ValidationException::withMessages([
                        'detalles' => 'No se pudo confirmar la asignación de todos los productos.',
                    ]);
                }
            }

            $presupuestosPersistidos = DB::table('cotizacion_presupuestos')
                ->where('cotizacion_cliente_id', $cotizacion->id)
                ->where('estado', 'VIGENTE')
                ->pluck('componente_id', 'id');
            foreach ($asignacionesPresupuestos as $asignacion) {
                if (
                    (int) ($presupuestosPersistidos[(int) $asignacion['presupuesto_id']] ?? 0)
                    !== (int) $asignacion['componente_id']
                ) {
                    throw ValidationException::withMessages([
                        'presupuestos' => 'No se pudo confirmar la asignación de todos los costos vigentes.',
                    ]);
                }
            }
        });

        return back()->with('success', 'Productos y costos internos asignados por componente.');
    }

    private function validarEditable(CotizacionCliente $cotizacion): void
    {
        abort_if($cotizacion->proforma_id !== null, 404);
        if (! $cotizacion->esEditable()) {
            throw ValidationException::withMessages([
                'componentes' => 'Los componentes solo pueden cambiar mientras la cotización esté abierta.',
            ]);
        }
    }

    private function resecuenciar(CotizacionCliente $cotizacion): void
    {
        $cotizacion->componentes()->orderBy('orden_secuencia')->get()
            ->each(fn($componente, $indice) => $componente->update([
                'orden_secuencia' => $indice + 1,
            ]));
    }

    private function sincronizarCompatibilidad(CotizacionCliente $cotizacion): void
    {
        $principal = $cotizacion->componentes()->orderBy('orden_secuencia')->first();
        if ($principal) {
            $cotizacion->update([
                'tipo_orden_id' => $principal->tipo_orden_id,
                'descripcion_trabajo' => $principal->descripcion_componente,
                'cliente_direccion_id' => $principal->cliente_direccion_id,
                'vehiculo_id' => $principal->vehiculo_id,
            ]);
        }
    }
}
