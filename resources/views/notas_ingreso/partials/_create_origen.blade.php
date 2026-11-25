<section id="paso-origen" class="panel order-selector-panel" data-flow-step-section="1">
    <div class="panel-heading panel-heading--split">
        <div>
            <p class="eyebrow">Origen</p>
            <h2>¿Por qué entra al Almacén?</h2>
            <p>La referencia original evita devolver o reponer más unidades de las que realmente salieron.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('notas-ingreso.create') }}" class="order-selector-form" data-entry-origin-form>
        <div class="form-field">
            <label for="motivo_ingreso_selector">Motivo de ingreso</label>
            <select id="motivo_ingreso_selector" name="motivo_ingreso" data-entry-origin-type>
                <option value="COMPRA" @selected($motivo === 'COMPRA')>Recepción de compra</option>
                <option value="DEVOLUCION_HERRAMIENTA" @selected($motivo === 'DEVOLUCION_HERRAMIENTA')>Devolución de herramienta / uso temporal</option>
                <option value="RETORNO_MATERIAL" @selected($motivo === 'RETORNO_MATERIAL')>Retorno de material no utilizado</option>
                <option value="DEVOLUCION_MATERIAL_MALOGRADO" @selected($motivo === 'DEVOLUCION_MATERIAL_MALOGRADO')>Devolución de material malogrado</option>
                <option value="REPOSICION_PRESTAMO" @selected($motivo === 'REPOSICION_PRESTAMO')>Reposición de préstamo de Proforma</option>
            </select>
        </div>

        @if ($motivo === 'COMPRA')
            <div class="form-field">
                <label for="orden_compra_busqueda">Orden de compra</label>
                <x-ui.remote-combobox
                    name="orden_compra_id"
                    search-id="orden_compra_busqueda"
                    value-id="orden_compra_id"
                    :search-url="route('catalogos.ordenes-compra.buscar')"
                    :selected-id="$orden?->id"
                    :selected-label="$orden ? $orden->codigo.' — '.($orden->proveedor?->nombreVisible() ?? 'Sin proveedor') : ''"
                    placeholder="Código o proveedor"
                    empty-text="No hay órdenes aprobadas pendientes de recepción."
                    required
                />
            </div>
        @elseif (in_array($motivo, ['DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL', 'DEVOLUCION_MATERIAL_MALOGRADO'], true))
            <div class="form-field">
                <label for="nota_salida_busqueda">Nota de Salida original</label>
                <x-ui.remote-combobox
                    name="nota_salida_id"
                    search-id="nota_salida_busqueda"
                    value-id="nota_salida_id"
                    :search-url="route('catalogos.notas-salida.buscar', ['contexto' => $motivo === 'DEVOLUCION_HERRAMIENTA' ? 'devolucion_herramienta' : 'retorno_material'])"
                    :selected-id="$notaSalida?->id"
                    :selected-label="$notaSalida ? $notaSalida->codigo.' — '.($notaSalida->entregado_a ?: 'Sin receptor') : ''"
                    placeholder="Código de Nota de Salida o responsable"
                    empty-text="No se encontró una Nota de Salida con productos pendientes de retorno."
                    required
                />
            </div>
        @else
            <div class="form-field">
                <label for="proforma_reposicion_busqueda">Proforma del préstamo</label>
                <x-ui.remote-combobox
                    name="proforma_id"
                    search-id="proforma_reposicion_busqueda"
                    value-id="proforma_id"
                    :search-url="route('catalogos.proformas-almacen.buscar', ['contexto' => 'reposicion_prestamo'])"
                    :selected-id="$proforma?->id"
                    :selected-label="$proforma ? $proforma->codigo.' — '.($proforma->cliente?->nombreVisible() ?? 'Sin cliente') : ''"
                    placeholder="Código de Proforma o cliente"
                    empty-text="No se encontró una Proforma con préstamos."
                    required
                />
            </div>
        @endif

        <button type="submit" class="button button--primary">
            <x-ui.icon name="refresh" :size="17" />
            Cargar origen
        </button>
    </form>

    @if ($origenNoDisponible)
        <div class="notice notice--warning notice--block">
            <x-ui.icon name="warning" :size="18" />
            <span>El origen seleccionado ya no está disponible.</span>
        </div>
    @endif
</section>
