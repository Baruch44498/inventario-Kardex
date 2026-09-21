        @if ($admiteReservas)
        <section class="panel operation-material-reservations" id="reservas-materiales">
            <div class="panel-heading operation-card-heading operation-section-heading">
                <p class="eyebrow">Planificación de materiales</p>
                @php
                    $reservasActivasCount = $orden->reservasMateriales->where('estado', 'ACTIVA')->count();
                @endphp
                <div class="operation-section-heading__title-row">
                    <h2>Reservas de la orden</h2>
                    <span class="count-chip {{ $reservasActivasCount === 0 ? 'count-chip--neutral' : '' }}">{{ $reservasActivasCount }} {{ $reservasActivasCount === 1 ? 'activa' : 'activas' }}</span>
                </div>
                <p>
                    La reserva se sincroniza automáticamente con los materiales requeridos. Reservar no
                    descuenta stock físico ni crea Kardex; la salida real se registra mediante Nota de Salida.
                </p>
                @if ($orden->estaAbierta())
                    <x-ui.collapsible-notice title="Reserva pendiente de activación" label="Ver cuándo se crean las reservas">
                        <span>Las reservas se crearán cuando el Jefe de Planta active la orden.</span>
                    </x-ui.collapsible-notice>
                @elseif ($orden->estaEnProceso())
                    <x-ui.collapsible-notice variant="success" icon="check-circle" title="Reservas automáticas activas" label="Ver cómo se actualizan las reservas">
                        <span>Los cambios en materiales requeridos reajustan este saldo sin mover stock físico.</span>
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
                <div class="table-wrap table-wrap--wide table-wrap--responsive reservation-table-wrap">
                    <table class="data-table reservation-table">
                        <thead>
                            <tr>
                                <th class="table-sticky--start">Producto</th>
                                <th class="text-right">Reservado</th>
                                <th class="text-right">Atendido</th>
                                <th class="text-right">Liberado</th>
                                <th class="text-right">Pendiente</th>
                                <th class="text-right">Físico</th>
                                <th class="text-right">Disponible</th>
                                <th class="text-right">Compra sug.</th>
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
                                <tr>
                                    <td class="table-sticky--start">
                                        <strong>{{ $reserva->producto?->codigo }}</strong>
                                        <span>{{ $reserva->producto?->descripcion }}</span>
                                        @if ($reserva->observacion)<small>{{ $reserva->observacion }}</small>@endif
                                    </td>
                                    <td class="text-right"><x-ui.quantity :value="$reserva->cantidad_reservada" /> {{ $unidad }}</td>
                                    <td class="text-right"><x-ui.quantity :value="$reserva->cantidad_atendida" /> {{ $unidad }}</td>
                                    <td class="text-right"><x-ui.quantity :value="$reserva->cantidad_liberada" /> {{ $unidad }}</td>
                                    <td class="text-right"><strong><x-ui.quantity :value="$pendiente" /> {{ $unidad }}</strong></td>
                                    <td class="text-right"><x-ui.quantity :value="$disp['stock_fisico'] ?? 0" /> {{ $unidad }}</td>
                                    <td class="text-right">
                                        <span @class(['availability-negative' => $disponible < 0])>
                                            <x-ui.quantity :value="$disponible" /> {{ $unidad }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        @if ($necesidad > 0.0001)
                                            <span class="badge badge--warning">Comprar <x-ui.quantity :value="$necesidad" /> {{ $unidad }}</span>
                                        @else
                                            <span class="badge badge--success">Cubierto</span>
                                        @endif
                                    </td>
                                    <td><span class="badge badge--{{ $badgeReserva }}">{{ $reserva->estado }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @endif

