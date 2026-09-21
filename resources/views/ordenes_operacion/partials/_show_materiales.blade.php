        @if ($orden->cotizacionCliente)
            <section class="panel supplier-quote-detail-lines" id="documento-origen">
                <header class="supplier-panel-heading supplier-panel-heading--split">
                    <div>
                        <p class="eyebrow">Documento de origen</p>
                        <h2>Productos de {{ $orden->cotizacionCliente->codigo }}</h2>
                        <p>
                            Lista aprobada para esta orden. Los materiales adicionales se controlarán
                            posteriormente sin modificar esta cotización cerrada.
                        </p>
                    </div>
                    @if (auth()->user()->puede('proformas.ver'))
                        <a href="{{ route('cotizaciones-cliente.show', $orden->cotizacionCliente) }}"
                            class="button button--ghost button--small">
                            Ver cotización
                        </a>
                    @endif
                </header>
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
            </section>
        @endif

        @if ($admiteReservas)
        <section class="panel operation-required-materials" id="materiales-requeridos">
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
                <p>
                    Esta es la necesidad operativa de la OM/OS/OP. Antes de activar puede corregirse
                    libremente. Al activar la orden, la previsión vigente queda congelada y el sistema
                    genera las reservas automáticamente; los cambios posteriores quedan como variaciones.
                </p>
                @if ($orden->estaAbierta())
                    <x-ui.collapsible-notice title="Previsión editable" label="Ver información sobre la previsión de materiales">
                        <span>Al activar la orden se congelará como previsto original y se reservará sin descontar stock físico.</span>
                    </x-ui.collapsible-notice>
                @elseif ($orden->estaEnProceso())
                    <x-ui.collapsible-notice variant="success" icon="check-circle" title="Previsión congelada" label="Ver información sobre la previsión congelada">
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
                        <small>Si el producto ya existe en la lista, la cantidad se suma como material adicional.</small>
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
                <div class="table-wrap table-wrap--wide table-wrap--responsive required-materials-table-wrap">
                    <table class="data-table required-materials-table">
                        <thead>
                            <tr>
                                <th class="table-sticky--start">Producto</th>
                                <th class="text-right">Previsto</th>
                                <th class="text-right">Variación</th>
                                <th class="text-right">Requerido</th>
                                <th class="text-right">Entregado</th>
                                <th class="text-right">Pendiente</th>
                                <th>Estado</th>
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
                                <tr>
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
                                    <td class="text-right"><strong><x-ui.quantity :value="$material->cantidad_requerida" /> {{ $unidadMaterial }}</strong></td>
                                    <td class="text-right"><x-ui.quantity :value="$material->cantidad_entregada" /> {{ $unidadMaterial }}</td>
                                    <td class="text-right"><strong><x-ui.quantity :value="$material->cantidad_pendiente" /> {{ $unidadMaterial }}</strong></td>
                                    <td><span class="badge badge--{{ $badgeMaterial }}">{{ $estadoMaterial }}</span></td>
                                    @if ($puedeGestionarMateriales)
                                        <td class="text-right">
                                            @if (! $orden->estaCerrada() && ! $orden->estaAnulada())
                                                <details class="required-material-adjustment">
                                                    <summary class="button button--ghost button--small">Modificar</summary>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('materiales-requeridos.update', $material->id) }}"
                                                        class="required-material-adjustment__form"
                                                        data-loading-form
                                                    >
                                                        @csrf
                                                        @method('PATCH')
                                                        <label>
                                                            Nuevo total requerido
                                                            <input
                                                                name="cantidad_nueva"
                                                                type="number"
                                                                min="0.001"
                                                                step="0.001"
                                                                value="{{ $material->cantidad_requerida }}"
                                                                required
                                                            >
                                                        </label>
                                                        <label>
                                                            Motivo del cambio
                                                            <textarea name="motivo" rows="2" maxlength="500" required placeholder="Explica por qué cambia el requerimiento"></textarea>
                                                        </label>
                                                        <small>No puede quedar por debajo de lo ya entregado físicamente.</small>
                                                        <button type="submit" class="button button--primary button--small" data-submit-button data-loading-text="Guardando...">
                                                            Guardar ajuste
                                                        </button>
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

