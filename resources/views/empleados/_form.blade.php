@php
    $editando = isset($empleado);
    $empleadoProtegido = $empleadoProtegido ?? false;
@endphp

@if ($errors->any())
    <div class="notice notice--danger notice--block admin-form-errors" role="alert">
        <x-ui.icon name="warning" :size="19" />
        <div>
            <strong>Revisa los campos indicados.</strong>
            @php($primerError = collect(['nombre_completo', 'dni', 'estado'])->first(fn ($campo) => $errors->has($campo)))
            @if ($primerError)
                <p><a href="#{{ $primerError }}">Ir al primer campo con error</a> y corrige el mensaje junto al campo.</p>
            @endif
        </div>
    </div>
@endif

<div class="admin-form-sections">
    <section class="admin-form-section" aria-labelledby="employee-identity-heading">
        <h2 id="employee-identity-heading">Identidad</h2>
        <div class="form-grid user-form-grid">
            <div class="form-field">
                <label for="nombre_completo">
                    Nombre completo <span class="required-mark">*</span>
                </label>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="user" :size="18" />
                    </span>
                    <input
                        id="nombre_completo"
                        name="nombre_completo"
                        type="text"
                        value="{{ old('nombre_completo', $empleado->nombre_completo ?? '') }}"
                        maxlength="150"
                        placeholder="Ej. Juan Pérez Ramírez"
                        required
                        autocomplete="off"
                        @class(['is-invalid' => $errors->has('nombre_completo')])
                    >
                </div>
                @error('nombre_completo')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-field">
                <label for="dni">
                    DNI <span class="required-mark">*</span>
                </label>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="id-card" :size="18" />
                    </span>
                    <input
                        id="dni"
                        name="dni"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]{8}"
                        value="{{ old('dni', $empleado->dni ?? '') }}"
                        minlength="8"
                        maxlength="8"
                        placeholder="12345678"
                        required
                        autocomplete="off"
                        @class(['is-invalid' => $errors->has('dni')])
                    >
                </div>
                <small>Debe contener exactamente 8 números y no puede repetirse.</small>
                @error('dni')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </div>

        </div>
    </section>
    <section class="admin-form-section" aria-labelledby="employee-state-heading">
        <h2 id="employee-state-heading">Disponibilidad</h2>
        <div class="form-grid user-form-grid">
            <div class="form-field">
                <span>Estado</span>
                <label class="switch-field">
                    <input type="hidden" name="estado" value="{{ $empleadoProtegido ? '1' : '0' }}">
                    <input
                        type="checkbox"
                        id="estado"
                        name="estado"
                        value="1"
                        @disabled($empleadoProtegido)
                        @checked((bool) old('estado', $empleado->estado ?? true))
                    >
                    <span class="switch-control"></span>
                    <span>Empleado activo</span>
                </label>
                <small>
                    {{ $empleadoProtegido
                        ? 'El empleado del administrador principal no puede desactivarse.'
                        : 'Solo los empleados activos podrán elegirse en nuevas operaciones.' }}
                </small>
                @error('estado')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </div>
        </div>
    </section>
</div>

<div class="form-actions">
    <button
        type="button"
        class="button button--ghost"
        data-cancel-form
        data-cancel-url="{{ route('empleados.index') }}"
    >
        Cancelar
    </button>

    <button
        type="submit"
        class="button button--primary"
        data-submit-button
        data-loading-text="{{ $editando ? 'Guardando cambios...' : 'Registrando empleado...' }}"
    >
        <span data-submit-icon>
            <x-ui.icon name="check" :size="18" />
        </span>
        <span class="button-spinner" data-submit-spinner hidden></span>
        <span data-submit-label>
            {{ $editando ? 'Guardar cambios' : 'Registrar empleado' }}
        </span>
    </button>
</div>
