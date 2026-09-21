        <section class="operation-related-grid" id="abastecimiento">
            <article class="panel operation-related-card" id="requerimientos-compra">
                <div class="panel-heading panel-heading--split operation-card-heading">
                    <div>
                        <p class="eyebrow">Abastecimiento</p>
                        <h2>Requerimientos de compra</h2>
                    </div>
                    <div class="operation-section-heading__title-row">
                        <span class="count-chip">{{ $orden->requisiciones_count }}</span>
                        @if (auth()->user()->puede('requerimientos.compra.crear') && $orden->estado === 'EN_PROCESO')
                            <a href="{{ route('requerimientos-compra.create', ['orden_operacion_id' => $orden->id]) }}" class="button button--ghost button--small">
                                <x-ui.icon name="plus" :size="15" /> Requerimiento
                            </a>
                        @endif
                    </div>
                </div>

                @forelse ($orden->requisiciones as $requisicion)
                    @if (auth()->user()->puedeAlguno('requerimientos.compra.crear', 'requerimientos.compra.gestionar'))
                        <a href="{{ route('requerimientos-compra.show', $requisicion) }}" class="related-record">
                    @else
                        <div class="related-record">
                    @endif
                            <div>
                                <strong>{{ $requisicion->codigo }}</strong>
                                <span>
                                    {{ $requisicion->fecha_solicitud?->format('d/m/Y') }}
                                    · {{ $requisicion->descripcion }}
                                </span>
                            </div>
                            <span class="badge badge--info">
                                {{ $requisicion->estado }}
                            </span>
                    @if (auth()->user()->puedeAlguno('requerimientos.compra.crear', 'requerimientos.compra.gestionar'))
                        </a>
                    @else
                        </div>
                    @endif
                @empty
                    <div class="operation-embedded-empty">
                        <span class="operation-embedded-empty__icon">
                            <x-ui.icon name="requisitions" :size="25" />
                        </span>
                        <strong>Sin requerimientos de compra</strong>
                        <span>
                            Almacén todavía no ha enviado un requerimiento de compra para esta orden.
                        </span>
                    </div>
                @endforelse
            </article>

            <article class="panel operation-related-card">
                <div class="panel-heading panel-heading--split operation-card-heading">
                    <div>
                        <p class="eyebrow">Almacén</p>
                        <h2>Notas de salida</h2>
                    </div>
                    <span class="count-chip">{{ $orden->notas_salida_count }}</span>
                </div>

                @forelse ($orden->notasSalida as $nota)
                    <a
                        href="{{ route('notas-salida.show', $nota->id) }}"
                        class="related-record"
                    >
                        <div>
                            <strong>{{ $nota->codigo }}</strong>
                            <span>
                                {{ $nota->fecha_salida?->format('d/m/Y') }}
                                · {{ $nota->entregado_a }}
                            </span>
                        </div>
                        <span
                            class="badge badge--{{ $nota->estado === 'CONFIRMADA'
                                ? 'success'
                                : ($nota->estado === 'ANULADA' ? 'danger' : 'warning') }}"
                        >
                            {{ $nota->estado }}
                        </span>
                    </a>
                @empty
                    <div class="operation-embedded-empty">
                        <span class="operation-embedded-empty__icon">
                            <x-ui.icon name="exit" :size="25" />
                        </span>
                        <strong>Sin salidas</strong>
                        <span>
                            No se han entregado productos para esta orden.
                        </span>
                    </div>
                @endforelse
            </article>
        </section>

