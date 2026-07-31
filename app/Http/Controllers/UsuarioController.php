<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'estado' => ['nullable', 'in:1,0'],
        ]);

        $query = User::query()->with('role');

        if (! empty($filtros['q'])) {
            $busqueda = trim($filtros['q']);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('username', 'like', "%{$busqueda}%")
                    ->orWhere('email', 'like', "%{$busqueda}%");
            });
        }

        if (! empty($filtros['role_id'])) {
            $query->where('role_id', $filtros['role_id']);
        }

        if (
            array_key_exists('estado', $filtros)
            && $filtros['estado'] !== null
            && $filtros['estado'] !== ''
        ) {
            $query->where('estado', (bool) $filtros['estado']);
        }

        $usuarios = $query
            ->orderByDesc('estado')
            ->orderBy('username')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::query()
            ->activos()
            ->withCount('users')
            ->orderBy('nombre')
            ->get();

        $resumen = User::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as activos')
            ->selectRaw('SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) as inactivos')
            ->first();

        $matriz = collect(config('hidroil_permisos.roles', []))
            ->map(function (array $configuracion, string $codigo): array {
                return [
                    'codigo' => $codigo,
                    'nombre' => $configuracion['nombre'],
                    'perfil' => $configuracion['perfil'],
                    'descripcion' => $configuracion['descripcion'],
                    'modulos' => $configuracion['modulos'],
                ];
            })
            ->values();

        return view('usuarios.index', compact(
            'usuarios',
            'roles',
            'resumen',
            'matriz'
        ));
    }

    public function create(): View
    {
        return view('usuarios.create', [
            'roles' => $this->rolesActivos(),
        ]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $usuario = User::query()->create([
            ...$request->validated(),
            'fecha_creacion' => now(),
        ]);

        return redirect()
            ->route('usuarios.edit', $usuario->id)
            ->with('success', "Usuario {$usuario->username} creado correctamente.");
    }

    public function edit(User $usuario): View
    {
        $usuario->load('role');

        return view('usuarios.edit', [
            'usuario' => $usuario,
            'roles' => $this->rolesActivos(),
        ]);
    }

    public function update(
        UpdateUsuarioRequest $request,
        User $usuario
    ): RedirectResponse {
        $datos = $request->validated();

        if ($usuario->is($request->user())) {
            if (! $datos['estado']) {
                return back()
                    ->withInput()
                    ->with('error', 'No puedes desactivar tu propia cuenta.');
            }

            $rolSeleccionado = Role::query()->find($datos['role_id']);

            if (
                $usuario->esAdministrador()
                && $rolSeleccionado?->codigo !== 'ADMINISTRADOR'
            ) {
                return back()
                    ->withInput()
                    ->with('error', 'No puedes retirar tu propio rol de administrador.');
            }
        }

        if ($this->dejariaSinAdministrador($usuario, $datos)) {
            return back()
                ->withInput()
                ->with('error', 'Debe permanecer al menos un administrador activo.');
        }

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        $usuario->update($datos);

        return redirect()
            ->route('usuarios.index')
            ->with('success', "Usuario {$usuario->username} actualizado.");
    }

    public function toggle(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $nuevoEstado = ! $usuario->estado;

        if (
            ! $nuevoEstado
            && $usuario->esAdministrador()
            && User::query()
            ->where('estado', true)
            ->whereHas(
                'role',
                fn($query) => $query->where('codigo', 'ADMINISTRADOR')
            )
            ->count() <= 1
        ) {
            return back()->with(
                'error',
                'Debe permanecer al menos un administrador activo.'
            );
        }

        $usuario->update(['estado' => $nuevoEstado]);

        return back()->with(
            'success',
            $nuevoEstado
                ? "Usuario {$usuario->username} activado."
                : "Usuario {$usuario->username} desactivado."
        );
    }

    private function rolesActivos()
    {
        return Role::query()
            ->activos()
            ->orderBy('nombre')
            ->get();
    }

    private function dejariaSinAdministrador(
        User $usuario,
        array $datos
    ): bool {
        if (! $usuario->esAdministrador()) {
            return false;
        }

        $rolNuevo = Role::query()->find($datos['role_id']);
        $seguiraActivoComoAdmin = (bool) $datos['estado']
            && $rolNuevo?->codigo === 'ADMINISTRADOR';

        if ($seguiraActivoComoAdmin) {
            return false;
        }

        return User::query()
            ->where('estado', true)
            ->whereKeyNot($usuario->id)
            ->whereHas(
                'role',
                fn($query) => $query->where('codigo', 'ADMINISTRADOR')
            )
            ->doesntExist();
    }
}
