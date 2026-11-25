        <section class="metric-grid" aria-label="Indicadores de almacén">
            @foreach ([
                ['Productos activos', 'productos', 'success', $resumen['productos_activos'], 'Catálogo disponible'],
                ['Bajo mínimo', 'warning', 'warning', $resumen['inventarios_bajo_minimo'], 'Requieren revisión'],
                ['Sin stock', 'box-off', 'danger', $resumen['sin_stock'], 'Atención prioritaria'],
                ['Órdenes en curso', 'orders', 'info', $resumen['ordenes_en_curso'], 'Abiertas o en proceso'],
                ['Alertas abiertas', 'bell', 'warning', $resumen['alertas_abiertas'], 'Activas o atendidas'],
                ['Movimientos hoy', 'movements', 'info', $resumen['movimientos_hoy'], 'Entradas y salidas'],
            ] as [$titulo, $icono, $tono, $valor, $detalle])
                <article class="metric-card metric-card--{{ $tono }}">
                    <div class="metric-card__top">
                        <span class="metric-card__label">{{ $titulo }}</span>
                        <span class="metric-card__icon">
                            <x-ui.icon :name="$icono" :size="22" />
                        </span>
                    </div>
                    <strong>{{ number_format($valor) }}</strong>
                    <small>{{ $detalle }}</small>
                </article>
            @endforeach
        </section>

        @include('dashboard._bandeja_operativa')

        <section class="dashboard-grid">
            <article class="panel {{ $movimientosRecientes->isEmpty() ? 'panel--empty-list' : '' }}">
                <header class="panel__header">
                    <div>
                        <p class="eyebrow">Actividad</p>
                        <h2>Movimientos recientes</h2>
                    </div>
                    @if (auth()->user()->puede('movimientos.ver'))
                        <a href="{{ route('movimientos.index') }}" class="text-link">
                            Ver módulo
                        </a>
                    @endif
                </header>

                @if ($movimientosRecientes->isNotEmpty())
                    <div class="table-wrap table-wrap--cards">
                        <table class="data-table data-table--cards" role="table">
                            <thead role="rowgroup">
                                <tr role="row">
                                    <th role="columnheader" scope="col">Fecha</th>
                                    <th role="columnheader" scope="col">Producto</th>
                                    <th role="columnheader" scope="col">Repisa</th>
                                    <th role="columnheader" scope="col">Tipo</th>
                                    <th role="columnheader" scope="col" class="text-right">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody role="rowgroup">
                                @foreach ($movimientosRecientes as $movimiento)
                                    <tr role="row">
                                        <td role="cell" data-label="Fecha">{{ $movimiento->fecha_movimiento?->format('d/m/Y H:i') }}</td>
                                        <td role="cell" class="table-card__wide" data-label="Producto">
                                            <strong>{{ $movimiento->producto?->codigo ?? '—' }}</strong>
                                            <span>{{ $movimiento->producto?->descripcion ?? '' }}</span>
                                        </td>
                                        <td role="cell" data-label="Repisa">{{ $movimiento->repisa?->codigo ?? '—' }}</td>
                                        <td role="cell" data-label="Tipo">
                                            <span class="badge badge--{{ $movimiento->tipo_movimiento === 'ENTRADA' ? 'success' : 'danger' }}">
                                                {{ $movimiento->tipo_movimiento }}
                                            </span>
                                        </td>
                                        <td role="cell" class="text-right" data-label="Cantidad">
                                            <x-ui.quantity :value="$movimiento->cantidad" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-ui.empty-table
                        icon="movements"
                        title="Sin movimientos recientes"
                        description="Las entradas y salidas aparecerán aquí."
                    />
                @endif
            </article>

            <article class="panel">
                <header class="panel__header">
                    <div>
                        <p class="eyebrow">Reposición</p>
                        <h2>Alertas abiertas</h2>
                    </div>
                    @if (auth()->user()->puede('alertas.ver'))
                        <a href="{{ route('alertas.index') }}" class="text-link">
                            Ver módulo
                        </a>
                    @endif
                </header>

                <div class="alert-list">
                    @forelse ($alertasRecientes as $alerta)
                        <div class="alert-item">
                            <div class="alert-item__marker alert-item__marker--{{ $alerta->nivel === 'CRITICA' ? 'danger' : 'warning' }}"></div>
                            <div class="alert-item__content">
                                <strong>{{ $alerta->producto?->codigo ?? 'Producto' }}</strong>
                                <span>
                                    Repisa {{ $alerta->repisa?->codigo ?? '—' }}
                                    · Stock <x-ui.quantity :value="$alerta->stock_actual" />
                                </span>
                                <small>{{ $alerta->tipo_alerta }}</small>
                            </div>
                            <span class="badge badge--{{ $alerta->estado === 'ACTIVA' ? 'danger' : 'warning' }}">
                                {{ $alerta->estado }}
                            </span>
                        </div>
                    @empty
                        <div class="empty-card">
                            <span class="empty-state__icon empty-state__icon--success">
                                <x-ui.icon name="check-circle" :size="34" />
                            </span>
                            <strong>Sin alertas abiertas</strong>
                            <span>El inventario no presenta condiciones pendientes.</span>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>
