@php
    $editando = isset($usuario);
    $autoridadBloqueada = $autoridadBloqueada ?? false;
    $principalProtegido = $principalProtegido ?? false;
    $bloquearEmpleado = $autoridadBloqueada
        || ($principalProtegido && ! empty($usuario->empleado_id));
@endphp

<div class="form-grid user-form-grid">
    <div class="form-field">
        <label for="empleado_id">
            Empleado vinculado <span class="required-mark">*</span>
        </label>
        @if ($bloquearEmpleado)
            <input type="hidden" name="empleado_id" value="{{ $usuario->empleado_id }}">
        @endif
        <select
            id="empleado_id"
            name="empleado_id"
            required
            @disabled($bloquearEmpleado)
            @class(['is-invalid' => $errors->has('empleado_id')])
        >
            <option value="">Selecciona un empleado activo</option>
            @foreach ($empleados as $empleadoOpcion)
                <option
                    value="{{ $empleadoOpcion->id }}"
                    @selected((int) old('empleado_id', $usuario->empleado_id ?? 0) === $empleadoOpcion->id)
                >
                    {{ $empleadoOpcion->nombre_completo }} — DNI {{ $empleadoOpcion->dni }}
                </option>
            @endforeach
        </select>
        <small>
            @if ($bloquearEmpleado)
                La vinculación administrativa está protegida.
            @else
                Cada empleado puede tener una sola cuenta de acceso.
            @endif
        </small>
        @error('empleado_id')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="username">
            Usuario <span class="required-mark">*</span>
        </label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="user" :size="18" />
            </span>
            <input
                id="username"
                name="username"
                type="text"
                value="{{ old('username', $usuario->username ?? '') }}"
                maxlength="50"
                placeholder="Ej. david"
                required
                autocomplete="off"
                @class(['is-invalid' => $errors->has('username')])
            >
        </div>
        <small>Letras, números, punto, guion o guion bajo.</small>
        @error('username')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="email">
            Correo <span class="required-mark">*</span>
        </label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="mail" :size="18" />
            </span>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $usuario->email ?? '') }}"
                maxlength="255"
                placeholder="usuario@hidroil.com"
                required
                @class(['is-invalid' => $errors->has('email')])
            >
        </div>
        @error('email')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="role_id">
            Rol <span class="required-mark">*</span>
        </label>
        @if ($autoridadBloqueada || $principalProtegido)
            <input type="hidden" name="role_id" value="{{ $usuario->role_id }}">
        @endif
        <select
            id="role_id"
            name="role_id"
            required
            @disabled($autoridadBloqueada || $principalProtegido)
            @class(['is-invalid' => $errors->has('role_id')])
        >
            <option value="">Selecciona un perfil</option>
            @foreach ($roles as $rol)
                <option
                    value="{{ $rol->id }}"
                    @selected((int) old('role_id', $usuario->role_id ?? 0) === $rol->id)
                >
                    {{ $rol->nombre }}
                </option>
            @endforeach
        </select>
        @error('role_id')
            <small class="field-error">{{ $message }}</small>
        @enderror
        @if ($autoridadBloqueada || $principalProtegido)
            <small>Solo el administrador principal puede cambiar esta autoridad.</small>
        @endif
    </div>

    <div class="form-field">
        <span>Estado</span>
        <label class="switch-field">
            <input
                type="hidden"
                name="estado"
                value="{{ ($autoridadBloqueada || $principalProtegido) ? '1' : '0' }}"
            >
            <input
                type="checkbox"
                name="estado"
                value="1"
                @disabled($autoridadBloqueada || $principalProtegido)
                @checked((bool) old('estado', $usuario->estado ?? true))
            >
            <span class="switch-control"></span>
            <span>Usuario activo</span>
        </label>
        <small>
            {{ ($autoridadBloqueada || $principalProtegido)
                ? 'El estado de esta cuenta administrativa está protegido.'
                : 'Las cuentas inactivas no pueden iniciar sesión.' }}
        </small>
    </div>

    <div class="form-field">
        <label for="password">
            Contraseña
            @unless ($editando)
                <span class="required-mark">*</span>
            @endunless
        </label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="lock" :size="18" />
            </span>
            <input
                id="password"
                name="password"
                type="password"
                @required(! $editando)
                autocomplete="new-password"
                placeholder="{{ $editando ? 'Déjala vacía para conservarla' : 'Mínimo 8 caracteres' }}"
                @class(['is-invalid' => $errors->has('password')])
            >
        </div>
        @error('password')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="password_confirmation">
            Confirmar contraseña
            @unless ($editando)
                <span class="required-mark">*</span>
            @endunless
        </label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="lock" :size="18" />
            </span>
            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                @required(! $editando)
                autocomplete="new-password"
                placeholder="Repite la contraseña"
            >
        </div>
    </div>
</div>

<div class="form-actions">
    <button
        type="button"
        class="button button--ghost"
        data-cancel-form
        data-cancel-url="{{ route('usuarios.index') }}"
    >
        Cancelar
    </button>

    <button
        type="submit"
        class="button button--primary"
        data-submit-button
        data-loading-text="{{ $editando ? 'Guardando cambios...' : 'Creando usuario...' }}"
    >
        <span data-submit-icon>
            <x-ui.icon name="check" :size="18" />
        </span>
        <span class="button-spinner" data-submit-spinner hidden></span>
        <span data-submit-label>
            {{ $editando ? 'Guardar cambios' : 'Crear usuario' }}
        </span>
    </button>
</div>
