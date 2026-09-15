    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading">
                <div>
                    <p class="eyebrow">Información del documento</p>
                    <h2>Datos principales</h2>
                </div>
            </header>

            <dl class="supplier-info-grid">
                <div>
                    <dt>Proveedor</dt>
                    <dd>
                        <a href="{{ route('proveedores.show', $cotizacion->proveedor_id) }}">
                            {{ $cotizacion->proveedor->nombreVisible() }}
                        </a>
                    </dd>
                </div>
                <div><dt>RUC</dt><dd>{{ $cotizacion->proveedor->ruc }}</dd></div>
                <div><dt>Documento externo</dt><dd>{{ $cotizacion->numero_documento ?: 'No registrado' }}</dd></div>
                <div>
                    <dt>Origen del registro</dt>
                    <dd>
                        @if ($cotizacion->origen_registro === 'IMPORTADO_PDF')
                            PDF digital revisado
                        @elseif ($cotizacion->origen_registro === 'IMPORTADO_EXCEL')
                            Excel revisado
                        @else
                            Registro manual
                        @endif
                    </dd>
                </div>
                @if ($cotizacion->archivo_original_path)
                    <div>
                        <dt>Documento original</dt>
                        <dd>
                            <a href="{{ route('cotizaciones-proveedor.documento-original', $cotizacion) }}" data-file-download>
                                {{ $cotizacion->archivo_original_nombre ?: 'Descargar documento' }}
                            </a>
                        </dd>
                    </div>
                @endif
                <div><dt>Vigencia</dt><dd>{{ $cotizacion->fecha_validez ? $cotizacion->fecha_validez->format('d/m/Y') : 'No especificada' }}</dd></div>
                <div>
                    <dt>Requerimiento</dt>
                    <dd>
                        @if ($cotizacion->requisicion)
                            <a href="{{ route('requerimientos-compra.show', $cotizacion->requisicion) }}">{{ $cotizacion->requisicion->codigo }}</a>
                        @else
                            Sin requerimiento
                        @endif
                    </dd>
                </div>
                @if ($cotizacion->requisicion)
                    <div>
                        <dt>Productos solicitados cotizados</dt>
                        <dd>{{ $cotizacion->detalles->filter(fn ($detalle) => $detalle->tipoVinculacionEfectivo() === 'SOLICITADO')->count() }} de {{ $cotizacion->requisicion->detalles()->count() }} productos del requerimiento</dd>
                    </div>
                    @if ($cotizacion->detalles->filter(fn ($detalle) => $detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA')->isNotEmpty())
                        <div>
                            <dt>Alternativas propuestas</dt>
                            <dd>{{ $cotizacion->detalles->filter(fn ($detalle) => $detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA')->count() }} para revisión de Logística</dd>
                        </div>
                    @endif
                @endif
                <div><dt>Registrado por</dt><dd>{{ $cotizacion->registrador?->username ?: 'Usuario no disponible' }}</dd></div>
                <div><dt>Condiciones de pago</dt><dd>{{ $cotizacion->condiciones_pago ?: 'No especificadas' }}</dd></div>
                <div><dt>Condiciones de entrega</dt><dd>{{ $cotizacion->condiciones_entrega ?: 'No especificadas' }}</dd></div>
                @if ($cotizacion->observacion)
                    <div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $cotizacion->observacion }}</dd></div>
                @endif
            </dl>
        </article>

        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Resumen económico</p>
            <div><span>Subtotal sin IGV</span><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->subtotal, 2) }}</strong></div>
            <div>
                <span>Descuento general</span>
                <strong>
                    @if ($cotizacion->descuento_global_modo === 'INCLUIDO')
                        No detallado
                    @else
                        {{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->descuento_global_monto, 2) }}
                    @endif
                </strong>
            </div>
            <div><span>Base neta</span><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format($cotizacion->baseNeta(), 2) }}</strong></div>
            <div><span>IGV</span><strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->impuesto, 2) }}</strong></div>
            @if ($cotizacion->tieneAjusteRedondeo())
                <div>
                    <span>Total calculado</span>
                    <strong>
                        <x-ui.money :value="$cotizacion->totalCalculadoVisible()" :currency="$cotizacion->moneda" />
                    </strong>
                </div>
                <div>
                    <span>Ajuste por redondeo</span>
                    <strong>
                        {{ (float) $cotizacion->ajuste_redondeo > 0 ? '+' : '−' }}
                        <x-ui.money :value="abs((float) $cotizacion->ajuste_redondeo)" :currency="$cotizacion->moneda" />
                    </strong>
                </div>
            @endif
            <div class="supplier-quote-total-card__main">
                <span>{{ $cotizacion->tieneAjusteRedondeo() ? 'Total final pagable' : 'Total' }}</span>
                <strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2) }}</strong>
            </div>
            @if ($cotizacion->tieneAjusteRedondeo())
                <small>
                    Conciliado con el documento del proveedor
                    @if (is_numeric($cotizacion->total_documento))
                        (total documental:
                        <x-ui.money :value="$cotizacion->total_documento" :currency="$cotizacion->moneda_documento ?: $cotizacion->moneda" />).
                    @endif
                    Confirmado por {{ $cotizacion->confirmadorAjusteRedondeo?->username ?: 'usuario no disponible' }}
                    @if ($cotizacion->ajuste_redondeo_confirmado_en)
                        el {{ $cotizacion->ajuste_redondeo_confirmado_en->format('d/m/Y H:i') }}.
                    @endif
                </small>
            @endif
            @if ($cotizacion->moneda === 'USD')
                <small>Tipo de cambio: {{ number_format((float) $cotizacion->tipo_cambio, 2) }}</small>
            @endif
            <small>{{ $cotizacion->descuentoGlobalVisible() }}</small>
        </article>
    </section>
