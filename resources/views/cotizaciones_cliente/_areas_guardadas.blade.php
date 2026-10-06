<section class="panel quote-area-builder" aria-labelledby="areas-guardadas-titulo">
    @php
        $partidasEnAreas = $areasPresupuesto->sum(fn ($resumenArea) => $resumenArea['lineas']->count());
        $costoAreasSoles = $areasPresupuesto->sum('costo_soles');
        $costoAreasDolares = $areasPresupuesto->sum(fn ($resumenArea) => $resumenArea['lineas']->sum('costo_total_dolares'));
    @endphp
    <header class="panel-heading panel-heading--split">
        <div>
            <p class="eyebrow">Planificación de la orden principal</p>
            <h2 id="areas-guardadas-titulo">Áreas guardadas</h2>
        </div>
        @if ($cotizacion->esEditable())
            <a href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'paso' => 'materiales']).'#nueva-area' }}" class="button button--primary" data-budget-open-area>
                <x-ui.icon name="plus" :size="17" />
                {{ $areasPresupuesto->isEmpty() ? 'Crear primera área' : 'Agregar otra área' }}
            </a>
        @endif
    </header>

    <div class="quote-area-builder__summary" aria-label="Resumen de áreas guardadas">
        <span><strong>{{ $areasPresupuesto->count() }}</strong> {{ $areasPresupuesto->count() === 1 ? 'área' : 'áreas' }}</span>
        <span><strong>{{ $partidasEnAreas }}</strong> {{ $partidasEnAreas === 1 ? 'partida' : 'partidas' }}</span>
        <span>Costo total <strong><x-ui.money :value="$costoAreasSoles" currency="PEN" /></strong> <small>(≈ <x-ui.money :value="$costoAreasDolares" currency="USD" />)</small></span>
    </div>

    @if ($areasPresupuesto->isEmpty())
        <div class="quote-area-builder__empty">
            <x-ui.icon name="clipboard" :size="22" />
            <div>
                <strong>Todavía no hay áreas</strong>
                <span>Crea la primera manualmente, aplica una plantilla o importa el Excel.</span>
            </div>
        </div>
    @else
        <div class="quote-area-builder__tools">
            @if ($areasPresupuesto->count() > 6)
                <label class="quote-area-builder__search">
                    <span>Buscar área</span>
                    <input type="search" placeholder="Nombre del área" data-budget-area-search>
                </label>
                <span role="status" aria-live="polite" data-budget-area-count>{{ $areasPresupuesto->count() }} áreas encontradas</span>
            @endif
            <button type="button" class="button button--ghost" data-budget-toggle-areas aria-expanded="false">Expandir todo</button>
        </div>
        <div class="quote-area-accordion">
            @foreach ($areasPresupuesto as $indiceArea => $resumenArea)
                @php
                    $area = $resumenArea['area'];
                    $lineasArea = $resumenArea['lineas'];
                @endphp
                <details class="quote-area-card" id="area-{{ $area->id }}">
                    <summary>
                        <span class="quote-area-card__number">{{ $indiceArea + 1 }}</span>
                        <span class="quote-area-card__identity">
                            <strong>{{ $area->rutaVisible($cotizacion->todasLasAreas) }}</strong>
                            <small>{{ $area->origen === 'EXCEL' ? 'Importada desde Excel' : 'Creada manualmente' }}</small>
                        </span>
                        <span class="quote-area-card__stats">
                            <span>{{ $resumenArea['materiales'] }} {{ $resumenArea['materiales'] === 1 ? 'material' : 'materiales' }}</span>
                            @if ($resumenArea['servicios'] > 0)
                                <span>{{ $resumenArea['servicios'] }} {{ $resumenArea['servicios'] === 1 ? 'servicio' : 'servicios' }}</span>
                            @endif
                            <strong><x-ui.money :value="$resumenArea['costo_soles']" currency="PEN" /></strong>
                        </span>
                        <span class="quote-area-card__chevron" aria-hidden="true">⌄</span>
                    </summary>

                    <div class="quote-area-card__body">
                        <div class="quote-area-card__body-head">
                            <strong>{{ $lineasArea->count() }} {{ $lineasArea->count() === 1 ? 'partida' : 'partidas' }}</strong>
                            @if ($cotizacion->esEditable())
                                <div class="quote-area-card__actions">
                                    <a href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'componente_id' => $componenteInicial?->id, 'paso' => 'materiales', 'area_id' => $area->id]).'#nueva-area' }}" class="button button--ghost button--small">
                                        <x-ui.icon name="plus" :size="15" /> Agregar materiales
                                    </a>
                                    <a href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'componente_id' => $componenteInicial?->id, 'paso' => 'costos', 'area_id' => $area->id, 'tipo_costo' => 'SERVICIO_TERCERO']).'#nuevo-costo' }}" class="button button--ghost button--small">
                                        <x-ui.icon name="plus" :size="15" /> Agregar servicio
                                    </a>
                                </div>
                            @endif
                        </div>
                        @if ($lineasArea->isEmpty())
                            <div class="quote-area-card__empty">
                                <strong>Área vacía</strong>
                                <span>Agrega materiales o relaciona un servicio con esta área.</span>
                            </div>
                        @else
                            <div class="table-wrap">
                                <table class="data-table quote-area-card__table">
                                    <thead>
                                        <tr>
                                            <th>Partida</th>
                                            <th class="text-right">Cantidad</th>
                                            <th class="text-right">Costo</th>
                                            <th class="text-right">Venta estimada</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($lineasArea as $linea)
                                            <tr>
                                                <td>
                                                    <strong>{{ $linea->descripcion }}</strong>
                                                    <span>{{ $linea->tipoVisible() }}@if ($linea->producto) · {{ $linea->producto->codigo }}@endif</span>
                                                </td>
                                                <td class="text-right">{{ number_format((float) $linea->cantidad, 2) }} {{ $linea->unidadVisible() }}</td>
                                                <td class="text-right"><x-ui.money :value="$linea->costo_total_soles" currency="PEN" /></td>
                                                <td class="text-right"><x-ui.money :value="$linea->precio_venta_total_soles" currency="PEN" /></td>
                                                <td class="text-right">
                                                    @if ($cotizacion->esEditable())
                                                        <a href="{{ route('cotizacion-presupuestos.edit', $linea) }}" class="button button--ghost button--small">Editar</a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                    </div>
                </details>
            @endforeach
        </div>
    @endif
</section>
