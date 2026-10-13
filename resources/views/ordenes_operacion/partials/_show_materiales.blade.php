        @if ($orden->cotizacionCliente)
            <section class="panel supplier-quote-detail-lines" id="documento-origen">
                <div class="supplier-panel-heading"><div><p class="eyebrow">Documento de origen</p><h2>Cotización vinculada</h2></div></div>
                <details class="operation-origin-details">
                    <summary>Productos de {{ $orden->cotizacionCliente->codigo }} ({{ $orden->cotizacionCliente->detalles->count() }} {{ $orden->cotizacionCliente->detalles->count() === 1 ? 'línea' : 'líneas' }})</summary>
                    <div class="operation-origin-details__content">
                        <p>Lista aprobada; los materiales adicionales se controlan sin modificar la cotización.</p>
                        @if (auth()->user()->puede('proformas.ver'))
                            <a href="{{ route('cotizaciones-cliente.show', $orden->cotizacionCliente) }}"
                                class="button button--ghost button--small">
                                Ver cotización
                            </a>
                        @endif
                        <div class="table-wrap order-products-table-wrap">
                        <table class="data-table order-products-table">
                            <thead>
                                <tr>
                                    <th class="order-products-table__product">Producto</th>
                                    <th class="text-center order-products-table__quantity">Cantidad</th>
                                    <th class="text-center order-products-table__unit">Unidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orden->cotizacionCliente->detalles as $detalle)
                                    <tr>
                                        <td class="order-products-table__product">
                                            <strong class="table-code-cell">{{ $detalle->codigo_producto }}</strong>
                                            <span
                                                class="order-product-description"
                                                tabindex="0"
                                                title="{{ $detalle->descripcion }}"
                                                aria-label="{{ $detalle->descripcion }}"
                                            >{{ $detalle->descripcion }}</span>
                                        </td>
                                        <td class="text-center order-products-table__quantity"><x-ui.quantity :value="$detalle->cantidad" /></td>
                                        <td class="text-center order-products-table__unit">{{ $detalle->unidad_medida ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                </details>
            </section>
        @endif

        @if ($admiteReservas)
        <section class="panel operation-required-materials" id="materiales-requeridos" data-operation-line-list>
            <div class="panel-heading operation-card-heading operation-section-heading">
                <p class="eyebrow">Necesidad operativa</p>
                @php
                    $materialesRequeridosCount = $orden->materialesRequeridos->count();
                @endphp
                <div class="operation-section-heading__title-row">
                    <h2>Materiales requeridos</h2>
                    <span class="count-chip {{ $materialesRequeridosCount === 0 ? 'count-chip--neutral' : '' }}">
                        {{ $materialesRequeridosCount }} {{ $materialesRequeridosCount === 1 ? 'material' : 'materiales' }}
                    </span>
                </div>
                <p>La previsión se congela al activar la orden; los cambios posteriores quedan registrados como variaciones.</p>
                @if ($orden->estaAbierta())
                    <x-ui.collapsible-notice title="Previsión editable" label="Previsión editable">
                        <span>Al activar la orden se congelará como previsto original y se reservará sin descontar stock físico.</span>
                    </x-ui.collapsible-notice>
                @elseif ($orden->estaEnProceso())
                    <x-ui.collapsible-notice variant="success" icon="check-circle" title="Previsión congelada" label="Previsión congelada">
                        <span>Todo material adicional o ajuste que registre Planta sincroniza automáticamente la reserva pendiente.</span>
                    </x-ui.collapsible-notice>
                @endif
            </div>

            @if ($puedeGestionarMateriales && ! $orden->estaCerrada() && ! $orden->estaAnulada())
                <form
                    method="POST"
                    action="{{ route('ordenes-operacion.materiales-requeridos.store', $orden->id) }}"
                    class="material-reservation-form required-material-form"
                    data-loading-form
                >
                    @csrf
                    <div class="form-field material-reservation-form__product">
                        <label for="material_requerido_producto_busqueda">Producto / material</label>
                        <x-ui.remote-combobox
                            name="producto_id"
                            search-id="material_requerido_producto_busqueda"
                            value-id="material_requerido_producto_id"
                            :search-url="route('catalogos.productos.buscar', ['contexto' => 'reserva_orden', 'orden_id' => $orden->id])"
                            placeholder="Código o descripción"
                            empty-text="No se encontró un producto activo."
                            required
                        />
                        <small>Si este producto ya existe, la cantidad a agregar se suma al total requerido; no reemplaza el valor actual.</small>
                        @error('producto_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field material-reservation-form__quantity">
                        <label for="material_requerido_cantidad">Cantidad a agregar</label>
                        <input
                            id="material_requerido_cantidad"
                            name="cantidad"
                            type="number"
                            min="0.001"
                            step="0.001"
                            value="{{ old('cantidad') }}"
                            required
                        >
                        <small>Ingresa solo la cantidad adicional, no el nuevo total.</small>
                        @error('cantidad')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field material-reservation-form__note">
                        <label for="material_requerido_motivo">Motivo / observación</label>
                        <input
                            id="material_requerido_motivo"
                            name="motivo"
                            type="text"
                            maxlength="500"
                            value="{{ old('motivo') }}"
                            placeholder="Ej. Material adicional para etapa de armado"
                        >
                    </div>

                    <div class="material-reservation-form__action">
                        <button type="submit" class="button button--primary" data-submit-button data-loading-text="Guardando...">
                            <x-ui.icon name="inventory" :size="17" />
                            <span data-submit-label>Agregar material</span>
                        </button>
                    </div>
                </form>
            @endif

            @if ($orden->materialesRequeridos->isEmpty())
                <div class="operation-embedded-empty operation-embedded-empty--wide">
                    <span class="operation-embedded-empty__icon"><x-ui.icon name="inventory" :size="25" /></span>
                    <strong>Sin materiales requeridos</strong>
                    <span>Esta orden todavía no tiene una necesidad de materiales registrada.</span>
                </div>
            @else
                <div class="operation-line-tools" data-operation-line-tools hidden>
                    <label>Buscar material <input type="search" data-operation-line-search placeholder="Código o descripción" autocomplete="off"></label>
                    <label>Estado <select data-operation-line-state><option value="">Todos</option><option value="PENDIENTE">Pendiente</option><option value="PARCIAL">Parcial</option><option value="ATENDIDO">Atendido</option><option value="EXCEDIDO">Excedido</option></select></label>
                    <button type="button" class="button button--ghost" data-operation-line-clear hidden>Limpiar filtros</button>
                </div>
                <div class="table-wrap table-wrap--wide table-wrap--responsive required-materials-table-wrap">
                    <table class="data-table required-materials-table">
                        <thead>
                            <tr>
                                <th class="table-sticky--start">Producto</th>
                                <th class="text-right">Requerido</th>
                                <th class="text-right">Pendiente</th>
                                <th>Estado</th>
                                <th class="text-right">Previsto</th>
                                <th class="text-right">Variación</th>
                                <th class="text-right">Entregado</th>
                                @if ($puedeGestionarMateriales)<th class="text-right">Acción</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orden->materialesRequeridos as $material)
                                @php
                                    $unidadMaterial = $material->producto?->unidadMedida?->codigo ?? '';
                                    $variacionMaterial = (float) ($material->variacion_acumulada ?? 0);
                                    $estadoMaterial = $material->estado_requerimiento ?? 'PENDIENTE';
                                    $badgeMaterial = match ($estadoMaterial) {
                                        'ATENDIDO' => 'success',
                                        'PARCIAL' => 'warning',
                                        'EXCEDIDO' => 'danger',
                                        default => 'info',
                                    };
                                @endphp
                                <tr data-operation-line-row data-operation-line-state="{{ $estadoMaterial }}" data-operation-line-search-text="{{ $material->producto?->codigo }} {{ $material->producto?->descripcion }}">
                                    <td class="table-sticky--start">
                                        <strong>{{ $material->producto?->codigo }}</strong>
                                        <span>{{ $material->producto?->descripcion }}</span>
                                        @if ($material->observacion)<small>{{ $material->observacion }}</small>@endif

                                        <details class="required-material-history">
                                            <summary>Historial ({{ $material->historial->count() }})</summary>
                                            <div class="required-material-history__list">
                                                @foreach ($material->historial->sortByDesc('created_at') as $cambio)
                                                    @php $cantidadCambio = (float) $cambio->cantidad_cambio; @endphp
                                                    <div class="required-material-history__item">
                                                        <div>
                                                            <strong>{{ $cambio->tipoVisible() }}</strong>
                                                            <span>
                                                                {{ $cambio->created_at?->format('d/m/Y H:i') }}
                                                                · {{ $cambio->registradoPor?->username ?? 'Usuario' }}
                                                            </span>
                                                        </div>
                                                        <div class="required-material-history__numbers">
                                                            <strong class="{{ $cantidadCambio < 0 ? 'availability-negative' : '' }}">
                                                                {{ $cantidadCambio > 0 ? '+' : '' }}<x-ui.quantity :value="$cantidadCambio" /> {{ $unidadMaterial }}
                                                            </strong>
                                                            <span>
                                                                <x-ui.quantity :value="$cambio->cantidad_anterior" /> →
                                                                <x-ui.quantity :value="$cambio->cantidad_nueva" /> {{ $unidadMaterial }}
                                                            </span>
                                                        </div>
                                                        @if ($cambio->motivo)<small>{{ $cambio->motivo }}</small>@endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    </td>
                                    <td class="text-right"><strong><x-ui.quantity :value="$material->cantidad_requerida" /> {{ $unidadMaterial }}</strong></td>
                                    <td class="text-right"><strong><x-ui.quantity :value="$material->cantidad_pendiente" /> {{ $unidadMaterial }}</strong></td>
                                    <td><span class="badge badge--{{ $badgeMaterial }}">{{ $estadoMaterial }}</span></td>
                                    <td class="text-right"><x-ui.quantity :value="$material->cantidad_inicial" /> {{ $unidadMaterial }}</td>
                                    <td class="text-right">
                                        @if (abs($variacionMaterial) > 0.0001)
                                            <span class="{{ $variacionMaterial < 0 ? 'availability-negative' : '' }}">
                                                {{ $variacionMaterial > 0 ? '+' : '' }}<x-ui.quantity :value="$variacionMaterial" /> {{ $unidadMaterial }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-right"><x-ui.quantity :value="$material->cantidad_entregada" /> {{ $unidadMaterial }}</td>
                                    @if ($puedeGestionarMateriales)
                                        <td class="text-right">
                                            @if (! $orden->estaCerrada() && ! $orden->estaAnulada())
                                                <details class="required-material-adjustment" @if ((int) old('_operation_material_id') === $material->id && ($errors->has('cantidad_nueva') || $errors->has('motivo'))) open @endif>
                                                    <summary class="button button--ghost button--small operation-adjustment-trigger">Modificar</summary>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('materiales-requeridos.update', $material->id) }}"
                                                        class="required-material-adjustment__form"
                                                        data-loading-form
                                                        data-operation-discard-form
                                                    >
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="_operation_material_id" value="{{ $material->id }}">
                                                        <label>
                                                            Nuevo total requerido
                                                            <small>Actual: <x-ui.quantity :value="$material->cantidad_requerida" /> {{ $unidadMaterial }}</small>
                                                            <input
                                                                name="cantidad_nueva"
                                                                type="number"
                                                                min="0.001"
                                                                step="0.001"
                                                                value="{{ (int) old('_operation_material_id') === $material->id ? old('cantidad_nueva', $material->cantidad_requerida) : $material->cantidad_requerida }}"
                                                                required
                                                            >
                                                            @if ((int) old('_operation_material_id') === $material->id)
                                                                @error('cantidad_nueva')<small class="field-error">{{ $message }}</small>@enderror
                                                            @endif
                                                        </label>
                                                        <label>
                                                            Motivo del cambio
                                                            <textarea name="motivo" rows="2" maxlength="500" required placeholder="Explica por qué cambia el requerimiento">{{ (int) old('_operation_material_id') === $material->id ? old('motivo') : '' }}</textarea>
                                                            @if ((int) old('_operation_material_id') === $material->id)
                                                                @error('motivo')<small class="field-error">{{ $message }}</small>@enderror
                                                            @endif
                                                        </label>
                                                        <small>No puede quedar por debajo de lo ya entregado físicamente.</small>
                                                        <div class="required-material-adjustment__actions">
                                                            <button type="button" class="button button--ghost button--small" data-operation-discard-button>Cancelar ajuste</button>
                                                            <button type="submit" class="button button--primary button--small" data-submit-button data-loading-text="Guardando...">Guardar ajuste</button>
                                                        </div>
                                                    </form>
                                                </details>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="operation-line-empty" data-operation-line-empty hidden>Sin materiales que coincidan con los filtros. Limpia la búsqueda o cambia el estado.</div>
                <div class="operation-line-footer" data-operation-line-footer hidden>
                    <span data-operation-line-range role="status" aria-live="polite"></span>
                    <nav class="operation-line-pagination" aria-label="Páginas de materiales requeridos" data-operation-line-pages hidden>
                        <button type="button" class="pagination__button" data-operation-line-prev>Anterior</button>
                        <span data-operation-line-numbers></span>
                        <button type="button" class="pagination__button" data-operation-line-next>Siguiente</button>
                    </nav>
                </div>
            @endif
        </section>

        <section class="panel operation-material-comparison" id="comparacion-materiales">
            <div class="panel-heading operation-card-heading operation-section-heading">
                <p class="eyebrow">Estimado contra real</p>
                <h2>Consumo de materiales por área</h2>
                <p>El retorno utilizable reduce el consumo real. El material malogrado permanece como pérdida y no vuelve al stock disponible.</p>
            </div>

            @if ($resumenEjecucion['comparacion_materiales']->isEmpty())
                <div class="empty-table-state">
                    <strong>Sin movimientos comparables</strong>
                    <span>La comparación aparecerá cuando exista una planificación o una salida de consumo.</span>
                </div>
            @else
                <div class="table-wrap table-wrap--wide table-wrap--responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Área / producto</th>
                                <th class="text-right">Estimado</th>
                                <th class="text-right">Salida bruta</th>
                                <th class="text-right">Retorno utilizable</th>
                                <th class="text-right">Malogrado</th>
                                <th class="text-right">Real neto</th>
                                <th class="text-right">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resumenEjecucion['comparacion_materiales'] as $comparacion)
                                @php
                                    $unidadComparacion = $comparacion['producto']?->unidadMedida?->codigo ?? 'UND';
                                    $diferenciaComparacion = (float) $comparacion['diferencia'];
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $comparacion['area'] }}</strong>
                                        <span>{{ $comparacion['producto']?->codigo ?? 'Producto' }} · {{ $comparacion['producto']?->descripcion ?? 'Sin descripción' }}</span>
                                    </td>
                                    <td class="text-right"><x-ui.quantity :value="$comparacion['estimado']" /> {{ $unidadComparacion }}</td>
                                    <td class="text-right"><x-ui.quantity :value="$comparacion['salida_bruta']" /> {{ $unidadComparacion }}</td>
                                    <td class="text-right"><x-ui.quantity :value="$comparacion['retorno_utilizable']" /> {{ $unidadComparacion }}</td>
                                    <td class="text-right"><x-ui.quantity :value="$comparacion['malogrado']" /> {{ $unidadComparacion }}</td>
                                    <td class="text-right"><strong><x-ui.quantity :value="$comparacion['real']" /> {{ $unidadComparacion }}</strong></td>
                                    <td class="text-right">
                                        <span class="badge badge--{{ $diferenciaComparacion > 0.0001 ? 'danger' : ($diferenciaComparacion < -0.0001 ? 'info' : 'success') }}">
                                            {{ $diferenciaComparacion > 0 ? '+' : '' }}<x-ui.quantity :value="$diferenciaComparacion" />
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @endif

