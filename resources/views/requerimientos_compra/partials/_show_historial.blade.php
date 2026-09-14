<section class="panel purchase-requirement-history-panel">
    <div class="panel-heading purchase-requirement-section-heading">
        <div class="purchase-requirement-section-heading__copy">
            <p class="eyebrow">Trazabilidad</p>
            <div class="purchase-requirement-section-heading__title-row">
                <h2>Historial del requerimiento</h2>
                <x-ui.collapsible-notice
                    class="purchase-requirement-section-heading__help"
                    title="El historial no reemplaza las cotizaciones"
                    label="Ver alcance del historial"
                >
                    <span>Registra quién cambió la etapa del requerimiento, cuándo lo hizo y la nota de seguimiento. Las cotizaciones de proveedores se vinculan como documentos separados.</span>
                </x-ui.collapsible-notice>
            </div>
            <p class="purchase-requirement-section-heading__description">Secuencia de estados desde que Almacén creó la necesidad hasta su atención por Logística.</p>
        </div>
        <div class="purchase-requirement-section-heading__meta" aria-label="Cantidad de movimientos del historial">
            <span class="count-chip">{{ $requerimiento->historial->count() }}</span>
        </div>
    </div>

    @if ($requerimiento->historial->isNotEmpty())
        <ol class="purchase-requirement-history-list">
            @foreach ($requerimiento->historial->sortByDesc('created_at') as $movimiento)
                <li class="purchase-requirement-history-item">
                    <span class="purchase-requirement-history-item__marker" aria-hidden="true"><x-ui.icon name="check-circle" :size="16" /></span>
                    <div class="purchase-requirement-history-item__content">
                        <div class="purchase-requirement-history-item__top">
                            <strong>
                                @if ($movimiento->estado_anterior)
                                    {{ str($movimiento->estado_anterior)->replace('_', ' ')->title() }} → {{ str($movimiento->estado_nuevo)->replace('_', ' ')->title() }}
                                @else
                                    {{ str($movimiento->estado_nuevo)->replace('_', ' ')->title() }}
                                @endif
                            </strong>
                            <time datetime="{{ $movimiento->created_at?->toIso8601String() }}">{{ $movimiento->created_at?->format('d/m/Y H:i') }}</time>
                        </div>
                        <span>{{ $movimiento->usuario?->nombreVisible() ?? 'Sistema' }}</span>
                        @if ($movimiento->observacion)
                            <p>{{ $movimiento->observacion }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @else
        <div class="operation-embedded-empty operation-embedded-empty--wide">
            <span class="operation-embedded-empty__icon"><x-ui.icon name="check-circle" :size="25" /></span>
            <strong>Sin movimientos registrados</strong>
            <span>Los cambios de estado aparecerán aquí automáticamente.</span>
        </div>
    @endif
</section>
