@extends('layouts.app')

@section('title', 'Usuarios y permisos')
@section('page-kicker', 'Administración')
@section('page-title', 'Usuarios y permisos')

@section('content')
    @if (! $hayAdministradorPrincipal)
        <section class="notice notice--warning notice--block">
            <x-ui.icon name="warning" :size="20" />
            <div>
                <strong>Falta establecer al administrador principal</strong>
                <p>
                    Si esta es la cuenta del dueño, confírmala una sola vez. Después
                    ningún administrador delegado podrá cambiar su autoridad.
                </p>
            </div>
            <form
                method="POST"
                action="{{ route('usuarios.establecer-principal') }}"
                data-confirm="¿Confirmas que esta cuenta corresponde al dueño y será el administrador principal?"
            >
                @csrf
                <button type="submit" class="button button--primary">
                    Establecer mi cuenta como principal
                </button>
            </form>
        </section>
    @endif

    <section class="module-header">
        <div>
            <p class="eyebrow">Seguridad del sistema</p>
            <h1>Usuarios y permisos</h1>
            <p>
                Asigna un perfil definitivo a cada usuario. Los permisos se
                mantienen centralizados y no se editan de forma improvisada.
            </p>
        </div>

        <a href="{{ route('usuarios.create') }}" class="button button--primary">
            <x-ui.icon name="plus" :size="18" />
            Nuevo usuario
        </a>
    </section>

    @if ($usuariosPendientes > 0)
        <section class="notice notice--info notice--block">
            <x-ui.icon name="info" :size="20" />
            <div>
                <strong>Usuarios existentes pendientes de vinculación: {{ $usuariosPendientes }}</strong>
                <p>
                    Estas cuentas conservan acceso durante la transición. Al editarlas,
                    selecciona primero el empleado correspondiente.
                </p>
            </div>
        </section>
    @endif

    <section class="summary-strip summary-strip--four">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral">
                <x-ui.icon name="users" :size="21" />
            </span>
            <div><span>Total</span><strong>{{ (int) ($resumen->total ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="check-circle" :size="21" />
            </span>
            <div><span>Activos</span><strong>{{ (int) ($resumen->activos ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--danger">
                <x-ui.icon name="power" :size="21" />
            </span>
            <div><span>Inactivos</span><strong>{{ (int) ($resumen->inactivos ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="lock" :size="21" />
            </span>
            <div><span>Roles definidos</span><strong>{{ $matriz->count() }}</strong></div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('usuarios.index') }}" class="user-filter-grid">
            <label class="form-field user-filter-grid__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="search" :size="17" />
                    </span>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Usuario, correo, empleado o DNI"
                    >
                </div>
            </label>

            <label class="form-field">
                <span>Rol</span>
                <select name="role_id">
                    <option value="">Todos</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->id }}" @selected((int) request('role_id') === $rol->id)>
                            {{ $rol->nombre }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="1" @selected(request('estado') === '1')>Activo</option>
                    <option value="0" @selected(request('estado') === '0')>Inactivo</option>
                </select>
            </label>

            <div class="filter-actions">
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="filter" :size="17" />
                    Filtrar
                </button>
                <a href="{{ route('usuarios.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <section class="panel {{ $usuarios->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($usuarios->count() > 0)
            <div class="table-wrap table-wrap--responsive">
                <table class="data-table data-table--actions user-list-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Empleado vinculado</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Último acceso</th>
                            <th>Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuarios as $usuario)
                            <tr>
                                <td>
                                    <strong>{{ $usuario->username }}</strong>
                                    <span>{{ $usuario->email }}</span>
                                    @if ($usuario->esAdministradorPrincipal())
                                        <span class="badge badge--info">ADMINISTRADOR PRINCIPAL</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($usuario->empleado)
                                        <strong>{{ $usuario->empleado->nombre_completo }}</strong>
                                        <span>DNI {{ $usuario->empleado->dni }}</span>
                                    @else
                                        <span class="badge badge--warning">PENDIENTE</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="role-code-chip">{{ $usuario->role?->nombre ?? 'Sin rol' }}</span>
                                </td>
                                <td>
                                    <span class="badge badge--{{ $usuario->estado ? 'success' : 'danger' }}">
                                        {{ $usuario->estado ? 'ACTIVO' : 'INACTIVO' }}
                                    </span>
                                </td>
                                <td>{{ $usuario->ultimo_acceso_en?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                                <td>{{ $usuario->fecha_creacion?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    @php
                                        $actor = auth()->user();
                                        $puedeEditarObjetivo = $actor->esAdministradorPrincipal()
                                            || ! $usuario->esAdministrador()
                                            || $usuario->is($actor);
                                        $puedeCambiarEstado = ! $usuario->esAdministradorPrincipal()
                                            && ! $usuario->is($actor)
                                            && (! $usuario->esAdministrador() || $actor->esAdministradorPrincipal());
                                    @endphp
                                    <div class="table-actions">
                                        @if ($puedeEditarObjetivo)
                                            <a
                                                href="{{ route('usuarios.edit', $usuario->id) }}"
                                                class="icon-button"
                                                title="Editar usuario"
                                                aria-label="Editar usuario"
                                            >
                                                <x-ui.icon name="edit" :size="17" />
                                            </a>
                                        @endif

                                        @if ($puedeCambiarEstado)
                                            <form
                                                method="POST"
                                                action="{{ route('usuarios.toggle', $usuario->id) }}"
                                                data-confirm="¿Confirmas cambiar el estado de este usuario?"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    class="icon-button icon-button--{{ $usuario->estado ? 'danger' : 'success' }}"
                                                    title="{{ $usuario->estado ? 'Desactivar' : 'Activar' }}"
                                                    aria-label="{{ $usuario->estado ? 'Desactivar' : 'Activar' }}"
                                                >
                                                    <x-ui.icon name="power" :size="17" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$usuarios" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon">
                    <x-ui.icon name="users" :size="30" />
                </span>
                <strong>No hay usuarios para mostrar</strong>
                <span>Ajusta los filtros o registra una cuenta nueva.</span>
            </div>
        @endif
    </section>

    <section class="role-matrix-section">
        <div class="section-heading">
            <p class="eyebrow">Matriz definitiva</p>
            <h2>Alcance por perfil</h2>
            <p>Los permisos se versionan en código para evitar cambios accidentales.</p>
        </div>

        <div class="role-matrix-grid">
            @foreach ($matriz as $rol)
                <article class="role-matrix-card">
                    <div class="role-matrix-card__top">
                        <span class="role-matrix-card__icon">
                            <x-ui.icon name="lock" :size="21" />
                        </span>
                        <div>
                            <span>{{ $rol['perfil'] }}</span>
                            <strong>{{ $rol['nombre'] }}</strong>
                        </div>
                    </div>

                    <p>{{ $rol['descripcion'] }}</p>

                    <ul>
                        @foreach ($rol['modulos'] as $modulo)
                            <li>
                                <x-ui.icon name="check-circle" :size="15" />
                                {{ $modulo }}
                            </li>
                        @endforeach
                    </ul>

                    <code>{{ $rol['codigo'] }}</code>
                </article>
            @endforeach
        </div>
    </section>
@endsection
