<section class="panel quote-area-builder" aria-labelledby="areas-guardadas-titulo">
    <header class="panel-heading panel-heading--split">
        <div>
            <p class="eyebrow">Planificación de la orden principal</p>
            <h2 id="areas-guardadas-titulo">Áreas guardadas</h2>
            <p>Cada área conserva sus materiales y servicios. Ábrela solo cuando necesites revisar su contenido.</p>
        </div>
        @if ($cotizacion->esEditable())
            <a href="#nueva-area" class="button button--primary">
                <x-ui.icon name="plus" :size="17" />
                Agregar otra área
            </a>
        @endif
    </header>

    @if ($areasPresupuesto->isEmpty())
        <div class="quote-area-builder__empty">
            <x-ui.icon name="clipboard" :size="22" />
            <div>
                <strong>Todavía no hay áreas</strong>
                <span>Crea la primera manualmente, aplica una plantilla o importa el Excel.</span>
            </div>
        </div>
    @else
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
                            <strong>{{ $area->nombre }}</strong>
                            <small>{{ $area->origen === 'EXCEL' ? 'Importada desde Excel' : 'Creada manualmente' }}</small>
                        </span>
                        <span class="quote-area-card__stats">
                            <span>{{ $resumenArea['materiales'] }} {{ $resumenArea['materiales'] === 1 ? 'material' : 'materiales' }}</span>
                            <span>{{ $resumenArea['servicios'] }} {{ $resumenArea['servicios'] === 1 ? 'servicio' : 'servicios' }}</span>
                            <strong><x-ui.money :value="$resumenArea['costo_soles']" currency="PEN" /></strong>
                        </span>
                        <span class="quote-area-card__chevron" aria-hidden="true">⌄</span>
                    </summary>

                    <div class="quote-area-card__body">
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

                        @if ($cotizacion->esEditable())
                            <div class="quote-area-card__actions">
                                <a href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'componente_id' => $componenteInicial?->id, 'paso' => 'materiales', 'area' => $area->nombre]).'#nueva-area' }}" class="button button--ghost button--small">
                                    <x-ui.icon name="plus" :size="15" /> Agregar materiales
                                </a>
                                <a href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'componente_id' => $componenteInicial?->id, 'paso' => 'costos', 'area' => $area->nombre, 'tipo_costo' => 'SERVICIO_TERCERO']).'#nuevo-costo' }}" class="button button--ghost button--small">
                                    <x-ui.icon name="plus" :size="15" /> Agregar servicio
                                </a>
                            </div>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    @endif
</section>
