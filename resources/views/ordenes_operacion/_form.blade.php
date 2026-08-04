@php
    $editando = isset($orden);
    $tipoSeleccionado = (int) old('tipo_orden_id', $orden->tipo_orden_id ?? 0);
    $direccionSeleccionada = (int) old('cliente_direccion_id', $orden->cliente_direccion_id ?? 0);
    $vehiculoSeleccionado = (int) old('vehiculo_id', $orden->vehiculo_id ?? 0);
@endphp

@if ($editando)
    @method('PUT')
@endif
@csrf

<div class="operation-form-grid">
    <div class="form-field">
        <label for="tipo_orden_id">Tipo de orden <span class="required-mark">*</span></label>
        <div class="input-with-icon input-with-icon--select">
            <span class="input-with-icon__symbol"><x-ui.icon name="orders" :size="18" /></span>
            <select
                id="tipo_orden_id"
                name="tipo_orden_id"
                data-order-type
                @disabled($editando)
                required
                @class(['is-invalid' => $errors->has('tipo_orden_id')])
            >
                <option value="">Selecciona un tipo</option>
                @foreach ($tipos as $tipo)
                    <option
                        value="{{ $tipo->id }}"
                        data-order-code="{{ $tipo->codigo }}"
                        @selected($tipoSeleccionado === $tipo->id)
                    >
                        {{ $tipo->codigo }} · {{ $tipo->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        @if ($editando)
            <input type="hidden" name="tipo_orden_id" value="{{ $orden->tipo_orden_id }}">
            <small>El tipo y código no cambian después del registro.</small>
        @endif
        @error('tipo_orden_id')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="codigo_preview">Código de orden</label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol"><x-ui.icon name="hash" :size="18" /></span>
            <input
                id="codigo_preview"
                type="text"
                value="{{ $editando ? $orden->codigo_orden : 'Se generará automáticamente' }}"
                data-order-code-preview
                readonly
            >
        </div>
        <small>Formato: tipo, correlativo anual y año.</small>
    </div>

    <div class="form-field">
        <label for="fecha_apertura">Fecha de apertura <span class="required-mark">*</span></label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol"><x-ui.icon name="calendar" :size="18" /></span>
            <input
                id="fecha_apertura"
                name="fecha_apertura"
                type="date"
                value="{{ old('fecha_apertura', isset($orden) ? $orden->fecha_apertura?->format('Y-m-d') : now()->toDateString()) }}"
                max="{{ now()->toDateString() }}"
                data-order-date
                required
                @class(['is-invalid' => $errors->has('fecha_apertura')])
            >
        </div>
        @error('fecha_apertura')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="cliente_busqueda">Cliente</label>
        <x-ui.remote-combobox
            name="cliente_id"
            search-id="cliente_busqueda"
            value-id="cliente_id"
            :search-url="route('catalogos.clientes.buscar')"
            :selected-id="$clienteSeleccionado?->id"
            :selected-label="$clienteSeleccionado
                ? $clienteSeleccionado->documentoVisible().' — '.$clienteSeleccionado->nombreVisible()
                : ''"
            placeholder="Documento, RUC o nombre"
            empty-text="Cliente no encontrado. Regístralo primero en Clientes."
            :value-attributes="['data-order-client' => '']"
        />
        @error('cliente_id')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field">
        <label for="cliente_direccion_id">
            Ubicación o dirección de referencia
        </label>

        <div class="input-with-icon input-with-icon--select">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="map-pin" :size="18" />
            </span>

            <select
                id="cliente_direccion_id"
                name="cliente_direccion_id"
                data-order-address
            >
                <option value="">Sin ubicación asociada</option>

                @foreach ($direcciones as $direccion)
                    <option
                        value="{{ $direccion->id }}"
                        @selected(
                            $direccionSeleccionada === $direccion->id
                        )
                    >
                        {{ $direccion->destino
                            ?: $direccion->direccion
                            ?: 'Dirección '.$direccion->id }}

                        {{ $direccion->ciudad
                            ? ' · '.$direccion->ciudad
                            : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <small>
            Es opcional y sirve como referencia del cliente. La atención,
            fabricación y recojo continúan realizándose en HIDROIL.
        </small>

        @error('cliente_direccion_id')
            <small class="field-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="vehiculo_id">Vehículo o unidad</label>
        <div class="input-with-icon input-with-icon--select">
            <span class="input-with-icon__symbol"><x-ui.icon name="movements" :size="18" /></span>
            <select id="vehiculo_id" name="vehiculo_id" data-order-vehicle>
                <option value="">Sin vehículo asociado</option>
                @foreach ($vehiculos as $vehiculo)
                    <option
                        value="{{ $vehiculo->id }}"
                        @selected($vehiculoSeleccionado === $vehiculo->id)
                    >
                        {{ $vehiculo->placa }}
                        {{ $vehiculo->marca ? ' · '.$vehiculo->marca : '' }}
                        {{ $vehiculo->modelo ? ' '.$vehiculo->modelo : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <small>
            Opcional. Para una fabricación nueva puedes registrar al cliente
            sin vehículo y describir la carroza o unidad en el trabajo.
        </small>
        @error('vehiculo_id')<small class="field-error">{{ $message }}</small>@enderror
    </div>

    <div class="form-field form-field--span-2">
        <label for="descripcion">Descripción del trabajo <span class="required-mark">*</span></label>
        <textarea
            id="descripcion"
            name="descripcion"
            rows="5"
            maxlength="500"
            placeholder="Describe el servicio, mantenimiento, producción, venta o trabajo asociado"
            required
            @class(['is-invalid' => $errors->has('descripcion')])
        >{{ old('descripcion', $orden->descripcion ?? '') }}</textarea>
        <div class="field-meta">
            <small>
                En fabricación, indica el tipo de carroza, capacidad,
                dimensiones u otra referencia técnica disponible.
            </small>
            <small data-character-count="descripcion">0 / 500</small>
        </div>
        @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
    </div>
</div>

<div class="form-actions">
    <button
        type="button"
        class="button button--ghost"
        data-cancel-form
        data-cancel-url="{{ route('ordenes-operacion.index') }}"
    >
        Cancelar
    </button>

    <button
        type="submit"
        class="button button--primary"
        data-submit-button
        data-loading-text="{{ $editando ? 'Guardando cambios...' : 'Registrando orden...' }}"
    >
        <span data-submit-icon><x-ui.icon name="check" :size="18" /></span>
        <span class="button-spinner" data-submit-spinner hidden></span>
        <span data-submit-label>{{ $editando ? 'Guardar cambios' : 'Registrar orden' }}</span>
    </button>
</div>

@push('scripts')
<script>
    document.querySelectorAll('[data-order-form]').forEach((form) => {
        const client = form.querySelector('[data-order-client]');
        const address = form.querySelector('[data-order-address]');
        const vehicle = form.querySelector('[data-order-vehicle]');
        const type = form.querySelector('[data-order-type]');
        const date = form.querySelector('[data-order-date]');
        const preview = form.querySelector('[data-order-code-preview]');
        const relationsUrl = @json(route('catalogos.clientes.relaciones'));
        let loadedClientId = client?.value || '';

        const replaceOptions = (select, placeholder, items) => {
            if (!select) return;

            select.replaceChildren();
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = placeholder;
            select.appendChild(empty);

            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = item.label;
                select.appendChild(option);
            });
        };

        const refreshRelations = async () => {
            const clientId = client?.value || '';
            if (clientId === loadedClientId) return;
            loadedClientId = clientId;

            replaceOptions(address, 'Cargando ubicaciones...', []);
            replaceOptions(vehicle, 'Cargando vehículos...', []);

            try {
                const url = new URL(relationsUrl, window.location.origin);
                if (clientId !== '') url.searchParams.set('cliente_id', clientId);

                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) throw new Error('No se pudieron cargar las relaciones.');

                const payload = await response.json();
                replaceOptions(address, 'Sin ubicación asociada', payload.direcciones || []);
                replaceOptions(vehicle, 'Sin vehículo asociado', payload.vehiculos || []);
            } catch (error) {
                replaceOptions(address, 'No se pudieron cargar las ubicaciones', []);
                replaceOptions(vehicle, 'No se pudieron cargar los vehículos', []);
            }
        };

        const refreshCode = () => {
            if (!preview || preview.dataset.fixed === 'true') return;

            const code = type?.selectedOptions[0]?.dataset.orderCode || 'TIPO';
            const yearValue = date?.value
                ? new Date(`${date.value}T00:00:00`).getFullYear()
                : new Date().getFullYear();
            const shortYear = String(yearValue).slice(-2);

            preview.value = `${code}-###-${shortYear}`;
        };

        client?.addEventListener('change', refreshRelations);
        type?.addEventListener('change', refreshCode);
        date?.addEventListener('change', refreshCode);

        if (form.dataset.editing === 'true' && preview) {
            preview.dataset.fixed = 'true';
        }

        refreshCode();
    });
</script>
@endpush
