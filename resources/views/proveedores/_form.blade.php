@php $editando = isset($proveedor); @endphp

<div class="form-grid supplier-form-grid">
    <div class="form-field">
        <label for="ruc">RUC <span class="required-mark">*</span></label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol"><x-ui.icon name="hash" :size="17" /></span>
            <input id="ruc" name="ruc" type="text"
                value="{{ old('ruc', $proveedor->ruc ?? '') }}"
                maxlength="11" inputmode="numeric" required
                placeholder="20123456789"
                data-digits-only="11">
        </div>
        <small>Debe contener exactamente 11 dígitos.</small>
        @error('ruc')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="razon_social">Razón social <span class="required-mark">*</span></label>
        <input id="razon_social" name="razon_social" type="text"
            value="{{ old('razon_social', $proveedor->razon_social ?? '') }}"
            maxlength="250" required placeholder="Nombre legal del proveedor">
        @error('razon_social')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="nombre_comercial">Nombre comercial</label>
        <input id="nombre_comercial" name="nombre_comercial" type="text"
            value="{{ old('nombre_comercial', $proveedor->nombre_comercial ?? '') }}"
            maxlength="200" placeholder="Nombre utilizado comercialmente">
        @error('nombre_comercial')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="contacto">Persona de contacto</label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol"><x-ui.icon name="user" :size="17" /></span>
            <input id="contacto" name="contacto" type="text"
                value="{{ old('contacto', $proveedor->contacto ?? '') }}"
                maxlength="150" placeholder="Contacto comercial">
        </div>
        @error('contacto')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="telefono">Teléfono</label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol"><x-ui.icon name="phone" :size="17" /></span>
            <input id="telefono" name="telefono" type="text"
                value="{{ old('telefono', $proveedor->telefono ?? '') }}"
                maxlength="9" inputmode="numeric" autocomplete="tel"
                placeholder="987654321"
                data-digits-only="9">
        </div>
        <small>Solo números, máximo 9 dígitos.</small>
        @error('telefono')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="correo">Correo</label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol"><x-ui.icon name="mail" :size="17" /></span>
            <input id="correo" name="correo" type="email"
                value="{{ old('correo', $proveedor->correo ?? '') }}"
                maxlength="150" autocomplete="email"
                placeholder="ventas@proveedor.com">
        </div>
        <small>Debe incluir @ y un dominio.</small>
        @error('correo')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="departamento">Departamento</label>
        <input id="departamento" name="departamento" type="text"
            value="{{ old('departamento', $proveedor->departamento ?? '') }}"
            maxlength="100" placeholder="Departamento">
        @error('departamento')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="ciudad">Ciudad</label>
        <input id="ciudad" name="ciudad" type="text"
            value="{{ old('ciudad', $proveedor->ciudad ?? '') }}"
            maxlength="100" placeholder="Ciudad">
        @error('ciudad')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field form-field--wide">
        <label for="direccion">Dirección de referencia</label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol"><x-ui.icon name="map-pin" :size="17" /></span>
            <input id="direccion" name="direccion" type="text"
                value="{{ old('direccion', $proveedor->direccion ?? '') }}"
                maxlength="350" placeholder="Dirección registrada por el proveedor">
        </div>
        @error('direccion')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <span>Estado</span>
        <label class="switch-field">
            <input type="hidden" name="estado" value="0">
            <input type="checkbox" name="estado" value="1"
                @checked((bool) old('estado', $proveedor->estado ?? true))>
            <span class="switch-control"></span>
            <span>Proveedor activo</span>
        </label>
        <small>Solo los proveedores activos aparecerán en nuevas cotizaciones.</small>
    </div>
</div>

<div class="form-actions">
    <button type="button" class="button button--ghost"
        data-cancel-form
        data-cancel-url="{{ $editando ? route('proveedores.show', $proveedor->id) : route('proveedores.index') }}">
        Cancelar
    </button>
    <button type="submit" class="button button--primary">
        <x-ui.icon name="check" :size="18" />
        {{ $editando ? 'Guardar cambios' : 'Registrar proveedor' }}
    </button>
</div>
