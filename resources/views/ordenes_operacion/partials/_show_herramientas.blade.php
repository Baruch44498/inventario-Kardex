        <section class="panel operation-tools-in-use" id="herramientas-en-uso">
            <div class="panel-heading operation-card-heading operation-section-heading">
                <p class="eyebrow">Uso temporal</p>
                @php
                    $herramientasPendientesCount = $herramientasEnUso->count();
                @endphp
                <div class="operation-section-heading__title-row">
                    <h2>Herramientas pendientes de devolución</h2>
                    @if ($herramientasPendientesCount > 0)<span class="count-chip">{{ $herramientasPendientesCount }} {{ $herramientasPendientesCount === 1 ? 'pendiente' : 'pendientes' }}</span>@endif
                </div>
                <p>Las herramientas no se reservan. Se controlan por la Nota de Salida y permanecen “en uso” hasta su Nota de Ingreso.</p>
            </div>

            @if ($herramientasEnUso->isEmpty())
                <div class="operation-embedded-empty operation-embedded-empty--wide operation-embedded-empty--compact">
                    <span class="operation-embedded-empty__icon"><x-ui.icon name="settings" :size="25" /></span>
                    <strong>Sin herramientas pendientes</strong>
                    <span>No hay salidas de uso temporal pendientes de retorno para esta orden.</span>
                </div>
            @else
                <div class="table-wrap table-wrap--responsive table-wrap--cards">
                    <table class="data-table data-table--cards" role="table">
                        <thead role="rowgroup">
                            <tr role="row">
                                <th role="columnheader" scope="col">Herramienta</th>
                                <th role="columnheader" scope="col" class="text-right">En uso</th>
                                <th role="columnheader" scope="col">Entregada a</th>
                                <th role="columnheader" scope="col">Salida</th>
                            </tr>
                        </thead>
                        <tbody role="rowgroup">
                            @foreach ($herramientasEnUso as $herramienta)
                                <tr role="row">
                                    <td role="cell" class="table-card__wide" data-label="Herramienta"><strong>{{ $herramienta->producto_codigo }}</strong><span>{{ $herramienta->producto_descripcion }}</span></td>
                                    <td role="cell" class="text-right" data-label="En uso"><x-ui.quantity :value="$herramienta->pendiente" /> {{ $herramienta->unidad_codigo }}</td>
                                    <td role="cell" data-label="Entregada a">{{ $herramienta->entregado_a ?: 'No registrado' }}</td>
                                    <td role="cell" data-label="Salida">
                                        @if (auth()->user()->puede('salidas.ver'))
                                            <a href="{{ route('notas-salida.show', $herramienta->nota_id) }}" class="table-primary-link">{{ $herramienta->nota_codigo }}</a>
                                        @else
                                            {{ $herramienta->nota_codigo }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

