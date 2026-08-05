@php $editando = isset($direccion); @endphp

<div class="notice notice--info notice--block">
    <x-ui.icon name="info" :size="18" />
    <span>
        Identifica si este registro es la <strong>Dirección fiscal</strong> o
        una dirección adicional de entrega, taller o sucursal.
        @if ($cliente->requiereDireccionFiscal())
            La empresa debe conservar una sola dirección fiscal activa.
        @endif
    </span>
</div>

<div class="form-grid address-form-grid">
    <div class="form-field">
        <label for="destino">Etiqueta de la dirección</label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="tag" :size="17" />
            </span>
            <input
                id="destino"
                name="destino"
                type="text"
                value="{{ old('destino', $direccion->destino ?? '') }}"
                maxlength="150"
                placeholder="Ej. Sede principal, oficina administrativa"
            >
        </div>
        <small>
            Opcional; sirve para diferenciar direcciones cuando exista más de una.
        </small>
        @error('destino')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field form-field--wide">
        <label for="direccion">
            Dirección
            <span class="required-mark">*</span>
        </label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="map-pin" :size="17" />
            </span>
            <input
                id="direccion"
                name="direccion"
                type="text"
                value="{{ old('direccion', $direccion->direccion ?? '') }}"
                maxlength="350"
                required
                placeholder="Avenida, calle, número y urbanización"
            >
        </div>
        <small>
            Avenida, calle, número y demás datos necesarios para identificarla.
        </small>
        @error('direccion')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="departamento">Departamento</label>
        <input
            id="departamento"
            name="departamento"
            type="text"
            value="{{ old(
                'departamento',
                $direccion->departamento ?? ''
            ) }}"
            maxlength="100"
            placeholder="Ej. La Libertad"
        >
        @error('departamento')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="provincia">Provincia</label>
        <input
            id="provincia"
            name="provincia"
            type="text"
            value="{{ old(
                'provincia',
                $direccion->provincia ?? ''
            ) }}"
            maxlength="100"
            placeholder="Ej. Trujillo"
        >
        @error('provincia')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="distrito">Distrito</label>
        <input
            id="distrito"
            name="distrito"
            type="text"
            value="{{ old(
                'distrito',
                $direccion->distrito ?? ''
            ) }}"
            maxlength="100"
            placeholder="Ej. Trujillo"
        >
        @error('distrito')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="ciudad">Ciudad o localidad</label>
        <input
            id="ciudad"
            name="ciudad"
            type="text"
            value="{{ old(
                'ciudad',
                $direccion->ciudad ?? ''
            ) }}"
            maxlength="100"
            placeholder="Ciudad o localidad"
        >
        @error('ciudad')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field form-field--wide">
        <label for="referencia">Referencia adicional</label>
        <textarea
            id="referencia"
            name="referencia"
            rows="3"
            maxlength="250"
            placeholder="Indicaciones o información complementaria"
        >{{ old('referencia', $direccion->referencia ?? '') }}</textarea>
        @error('referencia')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <span>Tipo de dirección</span>
        <label class="switch-field">
            <input type="hidden" name="es_fiscal" value="0">
            <input
                type="checkbox"
                name="es_fiscal"
                value="1"
                @checked(
                    (bool) old(
                        'es_fiscal',
                        $direccion->es_fiscal ?? false
                    )
                )
            >
            <span class="switch-control"></span>
            <span>Dirección fiscal</span>
        </label>
        <small>
            Déjalo desmarcado para una dirección adicional.
        </small>
        @error('es_fiscal')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <span>Uso operativo</span>
        <label class="switch-field">
            <input type="hidden" name="es_principal" value="0">
            <input
                type="checkbox"
                name="es_principal"
                value="1"
                @checked(
                    (bool) old(
                        'es_principal',
                        $direccion->es_principal ?? false
                    )
                )
            >
            <span class="switch-control"></span>
            <span>Dirección principal</span>
        </label>
        <small>
            Aparecerá primero al elegir una ubicación de referencia.
        </small>
    </div>

    <div class="form-field">
        <span>Estado</span>
        <label class="switch-field">
            <input type="hidden" name="estado" value="0">
            <input
                type="checkbox"
                name="estado"
                value="1"
                @checked(
                    (bool) old(
                        'estado',
                        $direccion->estado ?? true
                    )
                )
            >
            <span class="switch-control"></span>
            <span>Dirección activa</span>
        </label>
    </div>
</div>

<div class="form-actions">
    <button
        type="button"
        class="button button--ghost"
        data-cancel-form
        data-cancel-url="{{ route('clientes.show', $cliente->id) }}"
    >
        Cancelar
    </button>

    <button type="submit" class="button button--primary">
        <x-ui.icon name="check" :size="18" />
        {{ $editando
            ? 'Guardar cambios'
            : 'Registrar dirección' }}
    </button>
</div>
