<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarEmpleadoRequest;
use App\Models\Empleado;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmpleadoController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', 'in:1,0'],
        ]);

        $query = Empleado::query()->with(['usuario.role']);

        if (! empty($filtros['q'])) {
            $busqueda = trim($filtros['q']);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('nombre_completo', 'like', "%{$busqueda}%")
                    ->orWhere('dni', 'like', "%{$busqueda}%");
            });
        }

        if (
            array_key_exists('estado', $filtros)
            && $filtros['estado'] !== null
            && $filtros['estado'] !== ''
        ) {
            $query->where('estado', (bool) $filtros['estado']);
        }

        $empleados = $query
            ->orderByDesc('estado')
            ->orderBy('nombre_completo')
            ->paginate(15)
            ->withQueryString();

        $resumen = Empleado::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as activos')
            ->selectRaw('SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) as inactivos')
            ->first();

        return view('empleados.index', compact('empleados', 'resumen'));
    }

    public function create(): View
    {
        return view('empleados.create');
    }

    public function store(GuardarEmpleadoRequest $request): RedirectResponse
    {
        $empleado = Empleado::query()->create([
            ...$request->validated(),
            'registrado_por' => $request->user()->id,
            'actualizado_por' => $request->user()->id,
        ]);

        return redirect()
            ->route('empleados.edit', $empleado)
            ->with('success', "Empleado {$empleado->nombre_completo} registrado correctamente.");
    }

    public function edit(Request $request, Empleado $empleado): View
    {
        $empleado->load(['usuario.role']);
        $this->autorizarEmpleadoAdministrativo($request->user(), $empleado);
        $empleadoProtegido = $empleado->usuario?->esAdministradorPrincipal() ?? false;

        return view('empleados.edit', compact('empleado', 'empleadoProtegido'));
    }

    public function update(
        GuardarEmpleadoRequest $request,
        Empleado $empleado
    ): RedirectResponse {
        $empleado->load(['usuario.role']);
        $actor = $request->user();
        $this->autorizarEmpleadoAdministrativo($actor, $empleado);
        $datos = $request->validated();

        if ($empleado->usuario?->esAdministradorPrincipal() && ! $datos['estado']) {
            return back()
                ->withInput()
                ->with('error', 'El empleado del administrador principal no puede desactivarse.');
        }

        DB::transaction(function () use ($empleado, $datos, $actor): void {
            $registro = Empleado::query()
                ->with(['usuario.role'])
                ->lockForUpdate()
                ->findOrFail($empleado->id);

            $estabaActivo = $registro->estado;
            $registro->update([
                ...$datos,
                'actualizado_por' => $actor->id,
            ]);

            if ($estabaActivo && ! $registro->estado && $registro->usuario) {
                $registro->usuario->update(['estado' => false]);
            }
        });

        return redirect()
            ->route('empleados.index')
            ->with('success', "Empleado {$empleado->nombre_completo} actualizado.");
    }

    public function toggle(Request $request, Empleado $empleado): RedirectResponse
    {
        $empleado->load(['usuario.role']);
        $actor = $request->user();
        $this->autorizarEmpleadoAdministrativo($actor, $empleado);

        if ($empleado->usuario?->esAdministradorPrincipal() && $empleado->estado) {
            return back()->with(
                'error',
                'El empleado del administrador principal no puede desactivarse.'
            );
        }

        $tieneUsuarioVinculado = (bool) $empleado->usuario;
        $nuevoEstado = DB::transaction(function () use ($empleado, $actor): bool {
            $registro = Empleado::query()
                ->with(['usuario.role'])
                ->lockForUpdate()
                ->findOrFail($empleado->id);
            $nuevoEstado = ! $registro->estado;

            if ($registro->usuario?->esAdministradorPrincipal() && ! $nuevoEstado) {
                abort(403, 'El empleado del administrador principal no puede desactivarse.');
            }

            $registro->update([
                'estado' => $nuevoEstado,
                'actualizado_por' => $actor->id,
            ]);

            if (! $nuevoEstado && $registro->usuario) {
                $registro->usuario->update(['estado' => false]);
            }

            return $nuevoEstado;
        });

        return back()->with(
            'success',
            match (true) {
                $nuevoEstado && $tieneUsuarioVinculado => "Empleado {$empleado->nombre_completo} activado. Su usuario permanece inactivo hasta una reactivación manual.",
                $nuevoEstado => "Empleado {$empleado->nombre_completo} activado.",
                $tieneUsuarioVinculado => "Empleado {$empleado->nombre_completo} y su acceso vinculado fueron desactivados.",
                default => "Empleado {$empleado->nombre_completo} desactivado.",
            }
        );
    }

    private function autorizarEmpleadoAdministrativo(
        User $actor,
        Empleado $empleado
    ): void {
        $usuarioVinculado = $empleado->usuario;

        if (! $usuarioVinculado?->esAdministrador()) {
            return;
        }

        if ($actor->esAdministradorPrincipal()) {
            return;
        }

        abort(
            403,
            'Solo el administrador principal puede modificar empleados vinculados con administradores.'
        );
    }
}
