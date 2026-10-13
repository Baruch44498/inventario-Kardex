        @if ($admiteReservas)
        <section class="panel operation-material-reservations" id="reservas-materiales" data-operation-line-list>
            <div class="panel-heading operation-card-heading operation-section-heading">
                <p class="eyebrow">Planificación de materiales</p>
                @php
                    $reservasActivasCount = $orden->reservasMateriales->where('estado', 'ACTIVA')->count();
                @endphp
                <div class="operation-section-heading__title-row">
                    <h2>Reservas de la orden</h2>
                    <span class="count-chip {{ $reservasActivasCount === 0 ? 'count-chip--neutral' : '' }}">{{ $reservasActivasCount }} {{ $reservasActivasCount === 1 ? 'activa' : 'activas' }}</span>
                </div>
                <p>La reserva se sincroniza automáticamente con los materiales requeridos.</p>
                @if ($orden->estaAbierta())
                    <x-ui.collapsible-notice title="Reserva pendiente de activación" label="Reserva pendiente de activación">
                        <span>Las reservas se crearán cuando el Jefe de Planta active la orden.</span>
                    </x-ui.collapsible-notice>
                @elseif ($orden->estaEnProceso())
                    <x-ui.collapsible-notice variant="success" icon="check-circle" title="Reservas automáticas activas" label="Reservas automáticas activas">
                        <span>Esta reserva no descuenta stock físico ni crea Kardex; solo refleja la necesidad planificada.</span>
                    </x-ui.collapsible-notice>
                @endif
            </div>


            @if ($orden->reservasMateriales->isEmpty())
                <div class="operation-embedded-empty operation-embedded-empty--wide">
                    <span class="operation-embedded-empty__icon"><x-ui.icon name="inventory" :size="25" /></span>
                    <strong>Sin materiales reservados</strong>
                    <span>
                        {{ $orden->estaAbierta()
                            ? 'La orden todavía no está activa. La reserva se generará automáticamente al activarla.'
                            : 'No hay materiales pendientes de reserva para esta orden.' }}
                    </span>
                </div>
            @else
                <div class="operation-line-tools" data-operation-line-tools hidden>
                    <label>Buscar producto <input type="search" data-operation-line-search placeholder="Código o descripción" autocomplete="off"></label>
                    <label>Estado <select data-operation-line-state><option value="">Todos</option><option value="ACTIVA">Activa</option><option value="ATENDIDA">Atendida</option><option value="LIBERADA">Liberada</option></select></label>
                    <label class="operation-line-shortage"><input type="checkbox" data-operation-line-shortage> Solo con faltante</label>
                    <button type="button" class="button button--ghost" data-operation-line-clear hidden>Limpiar filtros</button>
                </div>
                <div class="table-wrap table-wrap--wide table-wrap--responsive reservation-table-wrap">
                    <table class="data-table reservation-table">
                        <thead>
                            <tr>
                                <th class="table-sticky--start">Producto</th>
                                <th class="text-right">Pendiente</th>
                                <th class="text-right">Disponible</th>
                                <th class="text-right">Compra sug.</th>
                                <th class="text-right">Reservado</th>
                                <th class="text-right">Atendido</th>
                                <th class="text-right">Liberado</th>
                                <th class="text-right">Físico</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orden->reservasMateriales as $reserva)
                                @php
                                    $disp = $reserva->resumen_disponibilidad ?? [];
                                    $pendiente = $reserva->cantidadPendiente();
                                    $necesidad = (float) ($disp['necesidad_abastecimiento'] ?? 0);
                                    $disponible = (float) ($disp['disponible'] ?? 0);
                                    $unidad = $reserva->producto?->unidadMedida?->codigo ?? '';
                                    $badgeReserva = match ($reserva->estado) {
                                        'ATENDIDA' => 'success',
                                        'LIBERADA' => 'neutral',
                                        default => $necesidad > 0.0001 ? 'warning' : 'info',
                                    };
                                @endphp
                                <tr @class(['operation-reservation--shortage' => $disponible < 0]) data-operation-line-row data-operation-line-state="{{ $reserva->estado }}" data-operation-line-shortage="{{ $necesidad > 0.0001 || $disponible < 0 ? '1' : '0' }}" data-operation-line-search-text="{{ $reserva->producto?->codigo }} {{ $reserva->producto?->descripcion }}">
                                    <td class="table-sticky--start">
                                        <strong>{{ $reserva->producto?->codigo }}</strong>
                                        <span>{{ $reserva->producto?->descripcion }}</span>
                                        @if ($reserva->observacion)<small>{{ $reserva->observacion }}</small>@endif
                                    </td>
                                    <td class="text-right"><strong><x-ui.quantity :value="$pendiente" /> {{ $unidad }}</strong></td>
                                    <td class="text-right">
                                        <span @class(['availability-negative' => $disponible < 0])>
                                            <x-ui.quantity :value="$disponible" /> {{ $unidad }}
                                        </span>
                                        @if ($disponible < 0)<span class="badge badge--danger">Falta stock</span>@endif
                                    </td>
                                    <td class="text-right">
                                        @if ($necesidad > 0.0001)
                                            <span class="badge badge--warning">Comprar <x-ui.quantity :value="$necesidad" /> {{ $unidad }}</span>
                                        @else
                                            <span class="badge badge--success">Cubierto</span>
                                        @endif
                                    </td>
                                    <td class="text-right"><x-ui.quantity :value="$reserva->cantidad_reservada" /> {{ $unidad }}</td>
                                    <td class="text-right"><x-ui.quantity :value="$reserva->cantidad_atendida" /> {{ $unidad }}</td>
                                    <td class="text-right"><x-ui.quantity :value="$reserva->cantidad_liberada" /> {{ $unidad }}</td>
                                    <td class="text-right"><x-ui.quantity :value="$disp['stock_fisico'] ?? 0" /> {{ $unidad }}</td>
                                    <td><span class="badge badge--{{ $badgeReserva }}">{{ $reserva->estado }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="operation-line-empty" data-operation-line-empty hidden>Sin reservas que coincidan con los filtros. Limpia la búsqueda o cambia el estado.</div>
                <div class="operation-line-footer" data-operation-line-footer hidden>
                    <span data-operation-line-range role="status" aria-live="polite"></span>
                    <nav class="operation-line-pagination" aria-label="Páginas de reservas" data-operation-line-pages hidden>
                        <button type="button" class="pagination__button" data-operation-line-prev>Anterior</button>
                        <span data-operation-line-numbers></span>
                        <button type="button" class="pagination__button" data-operation-line-next>Siguiente</button>
                    </nav>
                </div>
            @endif
        </section>

        @endif

