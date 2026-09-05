@php
    $tipoSeleccionado = (int) old('tipo_orden_id');
    $tipoCodigoSeleccionado = $tiposCotizacion
        ->firstWhere('id', $tipoSeleccionado)?->codigo;
    $direccionSeleccionada = (int) old('cliente_direccion_id');
    $vehiculoSeleccionado = (int) old('vehiculo_id');
    $monedaSeleccionada = old('moneda', $cotizacion->moneda);
    $faltaDireccionFiscal = $clienteSeleccionado?->requiereDireccionFiscal()
        && ! $clienteSeleccionado->tieneDireccionFiscalActiva();
@endphp

@if ($errors->any())
    <section class="notice notice--danger notice--block commercial-form-errors">
        <x-ui.icon name="error" :size="20" />
        <div><strong>Revisa la cotización</strong><span>{{ $errors->first() }}</span></div>
    </section>
@endif

<div class="commercial-document"
    data-commercial-document
    data-document-mode="quote"
    data-product-search-url="{{ route('catalogos.productos.buscar') }}"
    data-client-relations-url="{{ route('catalogos.clientes.relaciones') }}"
    data-margin="0">
    <section class="notice notice--info notice--block">
        <x-ui.icon name="orders" :size="20" />
        <div>
            <strong>Paso 1 de 3 · Define la orden principal antes de cargar costos</strong>
            <span>Primero se define si será OM, OS u OP. Después podrás cargar sus áreas y materiales manualmente, desde una plantilla o importando el Excel.</span>
        </div>
    </section>

    <section class="panel commercial-form-panel">
        <header class="panel-heading">
            <p class="eyebrow">Datos comerciales</p>
            <h2>Cliente, moneda y vigencia</h2>
            <p>Estos datos pertenecen a toda la cotización y a su futura orden principal.</p>
        </header>
        <div class="form-grid">
            <div class="form-field form-grid__full">
                <label for="cliente_busqueda">Cliente <span class="required-mark">*</span></label>
                <x-ui.remote-combobox
                    name="cliente_id"
                    search-id="cliente_busqueda"
                    value-id="cliente_id"
                    :search-url="route('catalogos.clientes.buscar')"
                    :selected-id="old('cliente_id', $clienteSeleccionado?->id)"
                    :selected-label="$clienteSeleccionado
                        ? $clienteSeleccionado->documentoVisible().' — '.$clienteSeleccionado->nombreVisible()
                        : ''"
                    placeholder="Documento o nombre del cliente"
                    empty-text="Cliente no encontrado. Regístralo primero."
                    :required="true"
                    :value-attributes="['data-commercial-client' => true]"
                />
            </div>
            <div
                class="notice notice--warning notice--block form-grid__full commercial-fiscal-warning"
                data-fiscal-warning
                @if (! $faltaDireccionFiscal) hidden @endif
            >
                <x-ui.icon name="warning" :size="18" />
                <span>Este cliente con RUC no tiene una <strong>Dirección fiscal</strong> activa.</span>
            </div>
            <label class="form-field">
                <span>Fecha de emisión <span class="required-mark">*</span></span>
                <input type="date" name="fecha_emision" value="{{ old('fecha_emision', $cotizacion->fecha_emision->format('Y-m-d')) }}" required>
            </label>
            <label class="form-field">
                <span>Válida hasta</span>
                <input type="date" name="fecha_validez" value="{{ old('fecha_validez') }}">
            </label>
            <label class="form-field">
                <span>Moneda de la cotización <span class="required-mark">*</span></span>
                <select name="moneda" data-document-currency required>
                    <option value="PEN" @selected($monedaSeleccionada === 'PEN')>PEN — Soles</option>
                    <option value="USD" @selected($monedaSeleccionada === 'USD')>USD — Dólares</option>
                </select>
            </label>
            <label class="form-field" data-exchange-field @if ($monedaSeleccionada !== 'USD') hidden @endif>
                <span>Tipo de cambio (PEN por USD) <span class="required-mark">*</span></span>
                <input type="number" name="tipo_cambio" min="0.000001" step="0.000001"
                    value="{{ $monedaSeleccionada === 'USD' ? old('tipo_cambio') : '' }}"
                    data-exchange-input @required($monedaSeleccionada === 'USD')>
                <small>PEN por cada USD.</small>
            </label>
        </div>
    </section>

    <section class="panel commercial-form-panel" data-commercial-order-context>
        <header class="panel-heading">
            <p class="eyebrow">Orden principal</p>
            <h2>¿Qué tipo de trabajo se realizará?</h2>
            <p>Ejemplo: mantenimiento de la cisterna del camión actual, fabricación de una cisterna nueva o un servicio especializado.</p>
        </header>
        <div class="form-grid">
            <label class="form-field">
                <span>Tipo de trabajo <span class="required-mark">*</span></span>
                <select name="tipo_orden_id" data-commercial-order-type required>
                    <option value="">Selecciona OM, OS u OP</option>
                    @foreach ($tiposCotizacion as $tipo)
                        <option value="{{ $tipo->id }}" data-order-code="{{ $tipo->codigo }}"
                            @selected($tipoSeleccionado === $tipo->id)>
                            {{ $tipo->codigo }} — {{ $tipo->nombre }}
                        </option>
                    @endforeach
                </select>
                <small>OM, OS y OP comparten el mismo formato de áreas, materiales y costos.</small>
                @error('tipo_orden_id')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="form-field">
                <span>Ubicación de referencia</span>
                <select name="cliente_direccion_id" data-commercial-address>
                    <option value="">Sin ubicación asociada</option>
                    @foreach ($direcciones as $direccion)
                        <option value="{{ $direccion->id }}" @selected($direccionSeleccionada === $direccion->id)>
                            {{ $direccion->destino ?: $direccion->direccion ?: 'Dirección '.$direccion->id }}
                        </option>
                    @endforeach
                </select>
                @error('cliente_direccion_id')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="form-field" data-commercial-vehicle-field
                @if ($tipoCodigoSeleccionado === 'OP') hidden @endif>
                <span>Vehículo o unidad <span data-vehicle-required-mark class="required-mark" @if ($tipoCodigoSeleccionado !== 'OM') hidden @endif>*</span></span>
                <select name="vehiculo_id" data-commercial-vehicle>
                    <option value="">Sin vehículo asociado</option>
                    @foreach ($vehiculos as $vehiculo)
                        <option value="{{ $vehiculo->id }}" @selected($vehiculoSeleccionado === $vehiculo->id)>
                            {{ $vehiculo->identificadorVisible() }} · {{ $vehiculo->descripcionVisible() }}
                        </option>
                    @endforeach
                </select>
                <small data-vehicle-help>Obligatorio en mantenimiento y opcional en servicio.</small>
                @error('vehiculo_id')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="form-field">
                <span>TC para comparación PEN/USD</span>
                <input type="number" name="tipo_cambio_comparacion" min="0.1" max="100" step="0.000001"
                    value="{{ old('tipo_cambio_comparacion') }}" placeholder="Ejemplo: 3.80">
                <small>Permite comparar el costo interno en ambas monedas; puede completarse después.</small>
                @error('tipo_cambio_comparacion')<small class="field-error">{{ $message }}</small>@enderror
            </label>
            <label class="form-field form-grid__full">
                <span>Descripción del trabajo <span class="required-mark">*</span></span>
                <textarea name="descripcion_trabajo" rows="4" minlength="5" maxlength="500"
                    placeholder="Ejemplo: mantenimiento integral de cisterna Volvo y revisión del sistema de descarga"
                    required>{{ old('descripcion_trabajo') }}</textarea>
                <small>Esta descripción identificará la cotización y la futura orden principal.</small>
                @error('descripcion_trabajo')<small class="field-error">{{ $message }}</small>@enderror
            </label>
        </div>
    </section>

    <section class="panel commercial-form-panel">
        <header class="panel-heading">
            <p class="eyebrow">Condiciones generales</p>
            <h2>Acuerdos de la cotización</h2>
        </header>
        <div class="form-grid">
            <label class="form-field"><span>Condiciones de pago</span><textarea name="condiciones_pago" maxlength="500">{{ old('condiciones_pago') }}</textarea></label>
            <label class="form-field"><span>Condiciones de entrega</span><textarea name="condiciones_entrega" maxlength="500">{{ old('condiciones_entrega') }}</textarea></label>
            <label class="form-field form-grid__full"><span>Observación</span><textarea name="observacion" maxlength="500">{{ old('observacion') }}</textarea></label>
        </div>
    </section>

    {{-- Mantiene activas las ayudas de moneda, cliente, vehículo y ubicación del JS compartido. --}}
    <div data-commercial-lines hidden></div>
    <div hidden>
        <span data-commercial-detail-title></span>
        <span data-commercial-detail-note-title></span>
        <span>Selecciona OM, OS u OP para definir cómo se mostrará este detalle al cliente.</span>
    </div>

    <div class="form-actions commercial-form-actions">
        <a class="button button--ghost" href="{{ route('cotizaciones-cliente.index') }}">Cancelar</a>
        <button type="submit" class="button button--primary" data-submit-button data-loading-text="Creando...">
            <span data-submit-icon><x-ui.icon name="arrow-right" :size="18" /></span>
            <span class="button-spinner" data-submit-spinner hidden></span>
            <span data-submit-label>Crear y cargar áreas y costos</span>
        </button>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/proforma-documentos.js') }}"></script>
@endpush
