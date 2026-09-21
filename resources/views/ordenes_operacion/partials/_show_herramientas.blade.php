        <section class="panel operation-tools-in-use" id="herramientas-en-uso">
            <div class="panel-heading operation-card-heading operation-section-heading">
                <p class="eyebrow">Uso temporal</p>
                @php
                    $herramientasPendientesCount = $herramientasEnUso->count();
                @endphp
                <div class="operation-section-heading__title-row">
                    <h2>Herramientas pendientes de devolución</h2>
                    <span class="count-chip {{ $herramientasPendientesCount === 0 ? 'count-chip--neutral' : '' }}">{{ $herramientasPendientesCount }} {{ $herramientasPendientesCount === 1 ? 'pendiente' : 'pendientes' }}</span>
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
                <div class="table-wrap table-wrap--responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Herramienta</th>
                                <th class="text-right">En uso</th>
                                <th>Entregada a</th>
                                <th>Salida</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($herramientasEnUso as $herramienta)
                                <tr>
                                    <td><strong>{{ $herramienta->producto_codigo }}</strong><span>{{ $herramienta->producto_descripcion }}</span></td>
                                    <td class="text-right"><x-ui.quantity :value="$herramienta->pendiente" /> {{ $herramienta->unidad_codigo }}</td>
                                    <td>{{ $herramienta->entregado_a ?: 'No registrado' }}</td>
                                    <td>
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

