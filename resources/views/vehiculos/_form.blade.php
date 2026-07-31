@php $editando = isset($vehiculo); @endphp

<div class="form-grid vehicle-form-grid">
    <div class="form-field">
        <label for="placa">
            Número de placa
            <span class="required-mark">*</span>
        </label>

        <div class="input-with-icon">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="car" :size="17" />
            </span>
            <input
                id="placa"
                name="placa"
                type="text"
                value="{{ old(
                    'placa',
                    $vehiculo->placa ?? ''
                ) }}"
                maxlength="20"
                required
                autocomplete="off"
                placeholder="Ej. ABC-123"
                oninput="this.value = this.value
                    .toUpperCase()
                    .replace(/\s+/g, '')
                    .replace(/[^A-Z0-9-]/g, '')"
            >
        </div>

        <small>
            Es el identificador único y obligatorio de la unidad.
        </small>

        @error('placa')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="marca">Marca</label>
        <input
            id="marca"
            name="marca"
            type="text"
            value="{{ old(
                'marca',
                $vehiculo->marca ?? ''
            ) }}"
            maxlength="100"
            placeholder="Marca"
        >
        @error('marca')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="modelo">Modelo</label>
        <input
            id="modelo"
            name="modelo"
            type="text"
            value="{{ old(
                'modelo',
                $vehiculo->modelo ?? ''
            ) }}"
            maxlength="100"
            placeholder="Modelo"
        >
        @error('modelo')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="anio">Año</label>
        <input
            id="anio"
            name="anio"
            type="number"
            value="{{ old(
                'anio',
                $vehiculo->anio ?? ''
            ) }}"
            min="1900"
            max="{{ now()->year + 1 }}"
            placeholder="Ej. 2022"
        >
        @error('anio')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="color">Color</label>
        <input
            id="color"
            name="color"
            type="text"
            value="{{ old(
                'color',
                $vehiculo->color ?? ''
            ) }}"
            maxlength="60"
            placeholder="Color"
        >
        @error('color')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="procedencia">
            Procedencia o referencia
        </label>
        <input
            id="procedencia"
            name="procedencia"
            type="text"
            value="{{ old(
                'procedencia',
                $vehiculo->procedencia ?? ''
            ) }}"
            maxlength="150"
            placeholder="Origen, sede o área"
        >
        @error('procedencia')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field form-field--wide">
        <label for="descripcion">Descripción</label>
        <textarea
            id="descripcion"
            name="descripcion"
            rows="3"
            maxlength="250"
            placeholder="Información adicional de la unidad"
        >{{ old(
            'descripcion',
            $vehiculo->descripcion ?? ''
        ) }}</textarea>
        @error('descripcion')
            <small class="field-error">{{ $message }}</small>
        @enderror
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
                        $vehiculo->estado ?? true
                    )
                )
            >
            <span class="switch-control"></span>
            <span>Vehículo activo</span>
        </label>
        <small>
            Solo las unidades activas aparecerán en nuevas órdenes.
        </small>
    </div>
</div>

<div class="form-actions">
    <button
        type="button"
        class="button button--ghost"
        data-cancel-form
        data-cancel-url="{{ $editando
            ? route(
                'clientes.vehiculos.show',
                [$cliente->id, $vehiculo->id]
            )
            : route('clientes.show', $cliente->id) }}"
    >
        Cancelar
    </button>

    <button type="submit" class="button button--primary">
        <x-ui.icon name="check" :size="18" />
        {{ $editando
            ? 'Guardar cambios'
            : 'Registrar vehículo' }}
    </button>
</div>
