<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Models\Empleado;
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

        $query = User::query()->with(['role', 'empleado']);

        if (! empty($filtros['q'])) {
            $busqueda = trim($filtros['q']);

            $query->where(function ($subquery) use ($busqueda): void {
                $subquery
                    ->where('username', 'like', "%{$busqueda}%")
                    ->orWhere('email', 'like', "%{$busqueda}%")
                    ->orWhereHas('empleado', function ($empleadoQuery) use ($busqueda): void {
                        $empleadoQuery
                            ->where('nombre_completo', 'like', "%{$busqueda}%")
                            ->orWhere('dni', 'like', "%{$busqueda}%");
                    });
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
            ->orderByDesc('es_administrador_principal')
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

        $usuariosPendientes = User::query()
            ->whereNull('empleado_id')
            ->count();
        $hayAdministradorPrincipal = User::query()
            ->where('es_administrador_principal', true)
            ->exists();

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
            'usuariosPendientes',
            'hayAdministradorPrincipal',
            'matriz'
        ));
    }

    public function create(Request $request): View
    {
        return view('usuarios.create', [
            'roles' => $this->rolesActivosPara($request->user()),
            'empleados' => $this->empleadosDisponibles(),
            'autoridadBloqueada' => false,
            'principalProtegido' => false,
        ]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $rolSeleccionado = Role::query()->findOrFail($datos['role_id']);

        if (
            $rolSeleccionado->codigo === 'ADMINISTRADOR'
            && ! $request->user()->esAdministradorPrincipal()
        ) {
            abort(403, 'Solo el administrador principal puede crear administradores.');
        }

        $usuario = User::query()->create([
            ...$datos,
            'fecha_creacion' => now(),
        ]);

        return redirect()
            ->route('usuarios.edit', $usuario->id)
            ->with('success', "Usuario {$usuario->username} creado correctamente.");
    }

    public function edit(Request $request, User $usuario): View
    {
        $usuario->load(['role', 'empleado']);
        $this->autorizarAccesoAAdministrador($request->user(), $usuario);

        $autoridadBloqueada = $usuario->esAdministrador()
            && ! $request->user()->esAdministradorPrincipal();
        $principalProtegido = $usuario->esAdministradorPrincipal();

        return view('usuarios.edit', [
            'usuario' => $usuario,
            'roles' => $this->rolesActivosPara($request->user(), $usuario),
            'empleados' => $this->empleadosDisponibles($usuario),
            'autoridadBloqueada' => $autoridadBloqueada,
            'principalProtegido' => $principalProtegido,
        ]);
    }

    public function update(
        UpdateUsuarioRequest $request,
        User $usuario
    ): RedirectResponse {
        $usuario->load(['role', 'empleado']);
        $actor = $request->user();
        $this->autorizarAccesoAAdministrador($actor, $usuario);

        $datos = $request->validated();
        $rolSeleccionado = Role::query()->findOrFail($datos['role_id']);

        if (
            $rolSeleccionado->codigo === 'ADMINISTRADOR'
            && ! $actor->esAdministradorPrincipal()
            && ! $usuario->esAdministrador()
        ) {
            abort(403, 'Solo el administrador principal puede otorgar el rol Administrador.');
        }

        if ($usuario->esAdministradorPrincipal()) {
            $error = $this->validarProteccionPrincipal(
                $usuario,
                $datos,
                $rolSeleccionado
            );

            if ($error) {
                return back()->withInput()->with('error', $error);
            }
        }

        if ($usuario->esAdministrador() && ! $actor->esAdministradorPrincipal()) {
            $error = $this->validarAutoridadDelegada(
                $usuario,
                $datos,
                $rolSeleccionado
            );

            if ($error) {
                return back()->withInput()->with('error', $error);
            }
        }

        if ($usuario->is($actor) && ! $datos['estado']) {
            return back()
                ->withInput()
                ->with('error', 'No puedes desactivar tu propia cuenta.');
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
        $usuario->load(['role', 'empleado']);
        $actor = $request->user();

        if ($usuario->esAdministradorPrincipal()) {
            return back()->with(
                'error',
                'La cuenta del administrador principal no puede desactivarse.'
            );
        }

        if ($usuario->esAdministrador() && ! $actor->esAdministradorPrincipal()) {
            abort(403, 'Solo el administrador principal puede cambiar el estado de otro administrador.');
        }

        if ($usuario->is($actor)) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $nuevoEstado = ! $usuario->estado;

        if ($nuevoEstado && $usuario->empleado && ! $usuario->empleado->estado) {
            return back()->with(
                'error',
                'Primero debes reactivar al empleado vinculado.'
            );
        }

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

    public function establecerAdministradorPrincipal(Request $request): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor->esAdministrador()) {
            abort(403);
        }

        $resultado = DB::transaction(function () use ($actor): bool {
            // El bloqueo del rol serializa la configuración inicial incluso
            // cuando todavía no existe ninguna fila marcada como principal.
            Role::query()
                ->where('codigo', 'ADMINISTRADOR')
                ->lockForUpdate()
                ->firstOrFail();

            $yaExiste = User::query()
                ->where('es_administrador_principal', true)
                ->lockForUpdate()
                ->first();

            if ($yaExiste) {
                return false;
            }

            User::query()
                ->whereKey($actor->id)
                ->update(['es_administrador_principal' => true]);

            return true;
        });

        return back()->with(
            $resultado ? 'success' : 'error',
            $resultado
                ? 'Tu cuenta quedó establecida como administrador principal.'
                : 'Ya existe un administrador principal en el sistema.'
        );
    }

    private function autorizarAccesoAAdministrador(User $actor, User $objetivo): void
    {
        if (! $objetivo->esAdministrador()) {
            return;
        }

        if ($actor->esAdministradorPrincipal() || $objetivo->is($actor)) {
            return;
        }

        abort(403, 'Un administrador delegado no puede modificar a otro administrador.');
    }

    private function validarProteccionPrincipal(
        User $usuario,
        array $datos,
        Role $rolSeleccionado
    ): ?string {
        if ($rolSeleccionado->codigo !== 'ADMINISTRADOR') {
            return 'El administrador principal no puede perder su rol.';
        }

        if (! $datos['estado']) {
            return 'El administrador principal no puede desactivarse.';
        }

        if (
            $usuario->empleado_id
            && (int) $datos['empleado_id'] !== (int) $usuario->empleado_id
        ) {
            return 'El administrador principal no puede desvincularse de su empleado.';
        }

        return null;
    }

    private function validarAutoridadDelegada(
        User $usuario,
        array $datos,
        Role $rolSeleccionado
    ): ?string {
        if ($rolSeleccionado->codigo !== 'ADMINISTRADOR') {
            return 'Solo el administrador principal puede retirar el rol Administrador.';
        }

        if (! $datos['estado']) {
            return 'Solo el administrador principal puede desactivar a un administrador.';
        }

        if ((int) $datos['empleado_id'] !== (int) $usuario->empleado_id) {
            return 'Solo el administrador principal puede cambiar el empleado de un administrador.';
        }

        return null;
    }

    private function rolesActivosPara(User $actor, ?User $objetivo = null)
    {
        return Role::query()
            ->activos()
            ->when(
                ! $actor->esAdministradorPrincipal()
                    && ! ($objetivo?->is($actor) && $objetivo->esAdministrador()),
                fn($query) => $query->where('codigo', '!=', 'ADMINISTRADOR')
            )
            ->orderBy('nombre')
            ->get();
    }

    private function empleadosDisponibles(?User $usuario = null)
    {
        return Empleado::query()
            ->activos()
            ->where(function ($query) use ($usuario): void {
                $query->whereDoesntHave('usuario');

                if ($usuario?->empleado_id) {
                    $query->orWhere('empleados.id', $usuario->empleado_id);
                }
            })
            ->orderBy('nombre_completo')
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
