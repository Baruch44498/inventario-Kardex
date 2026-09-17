<section id="paso-origen" class="panel order-selector-panel" data-flow-step-section="1">
    <div class="panel-heading panel-heading--split">
        <div><p class="eyebrow">Origen</p><h2>¿Por qué sale del Almacén?</h2><p>Selecciona el documento o motivo que explica el movimiento físico.</p></div>
    </div>

    <form method="GET" action="{{ route('notas-salida.create') }}" class="order-selector-form order-selector-form--note-output" data-output-origin-form>
        <div class="form-field">
            <label for="motivo_salida_selector">Motivo de salida</label>
            <select id="motivo_salida_selector" name="motivo_salida" data-output-origin-type>
                <option value="ORDEN_OPERACION" @selected($motivo === 'ORDEN_OPERACION')>Orden de operación (OM / OS / OP)</option>
                <option value="PROFORMA" @selected($motivo === 'PROFORMA')>Proforma de Almacén</option>
                <option value="USO_INTERNO" @selected($motivo === 'USO_INTERNO')>Uso interno</option>
                <option value="OTRO" @selected($motivo === 'OTRO')>Otro</option>
            </select>
        </div>

        @if ($motivo === 'ORDEN_OPERACION')
            <div class="form-field">
                <label for="orden_operacion_busqueda">Orden activa relacionada</label>
                <x-ui.remote-combobox name="orden_operacion_id" search-id="orden_operacion_busqueda" value-id="orden_operacion_id" :search-url="route('catalogos.ordenes-operacion.buscar')" :selected-id="$orden?->id" :selected-label="$orden ? $orden->codigo_orden.' — '.($orden->cliente?->nombreVisible() ?? 'Sin cliente') : ''" placeholder="OM-0001, mantenimiento, OS, servicio, OP, producción o cliente" empty-text="No se encontraron órdenes activas en ejecución." required />
                <small>El buscador muestra únicamente OM/OS/OP en ejecución. Las órdenes cerradas, anuladas o aún no activadas no aparecen.</small>
            </div>
            @if ($orden)
                <div class="form-field">
                    <label for="area_trabajo_selector">Área del trabajo</label>
                    <select id="area_trabajo_selector" name="area_trabajo" required>
                        @foreach ($areasTrabajo as $areaDisponible)<option value="{{ $areaDisponible }}" @selected($areaTrabajo === $areaDisponible)>{{ $areaDisponible }}</option>@endforeach
                    </select>
                    <small>Las áreas provienen de los grupos de materiales de la hoja de costos de esta orden.</small>
                </div>
            @endif
        @elseif ($motivo === 'PROFORMA')
            <div class="form-field">
                <label for="proforma_busqueda">Proforma</label>
                <x-ui.remote-combobox name="proforma_id" search-id="proforma_busqueda" value-id="proforma_id" :search-url="route('catalogos.proformas-almacen.buscar')" :selected-id="$proforma?->id" :selected-label="$proforma ? $proforma->codigo.' — '.($proforma->cliente?->nombreVisible() ?? 'Sin cliente') : ''" placeholder="Código de Proforma o cliente" empty-text="No se encontró una Proforma vigente." required />
            </div>
        @endif

        <button type="submit" class="button button--primary order-selector-form__submit"><x-ui.icon name="refresh" :size="17" /> {{ $orden ? 'Cargar orden y área' : 'Cargar origen' }}</button>
    </form>

    @if ($origenNoDisponible)
        <div class="notice notice--warning notice--block"><x-ui.icon name="warning" :size="18" /><span>{{ $motivo === 'ORDEN_OPERACION' ? 'La orden seleccionada no está activa o ya no está disponible. Actívala antes de registrar una salida.' : 'El origen seleccionado ya no está disponible.' }}</span></div>
    @endif
</section>
