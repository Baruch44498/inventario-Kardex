    @if ($cotizacion->solicitudCompra)
        <section class="panel supplier-quote-purchase-decision supplier-quote-purchase-decision--sent">
            <div>
                <p class="eyebrow">Continuidad de compra</p>
                <h2>Compra aprobada</h2>
                <p>La decisión quedó registrada en {{ $cotizacion->solicitudCompra->codigo }} y está disponible para consulta contable.</p>
            </div>
            <div class="supplier-quote-purchase-decision__status">
                <span class="badge badge--{{ $cotizacion->solicitudCompra->estadoClase() }}">{{ $cotizacion->solicitudCompra->estadoVisible() }}</span>
                @if ($cotizacion->solicitudCompra->ordenCompra)
                    <a href="{{ route('ordenes-compra.show', $cotizacion->solicitudCompra->ordenCompra) }}" class="button button--primary button--small">Ver orden de compra</a>
                @else
                    <a href="{{ route('solicitudes-compra.show', $cotizacion->solicitudCompra) }}" class="button button--primary button--small">Ver registro</a>
                @endif
            </div>
        </section>
    @elseif ($cotizacion->puedeAprobarParaCompra())
        @php
            $detallesElegibles = $cotizacion->detalles->filter(
                fn ($detalle) => in_array($detalle->tipoVinculacionEfectivo(), ['SOLICITADO', 'ALTERNATIVA'], true)
            );
            $puedeCompraDirecta = $puedeGestionarCompras
                && ! $cotizacion->requisicion_id
                && $cotizacion->detalles->isNotEmpty()
                && $cotizacion->detalles->every(
                    fn ($detalle) => $detalle->tipoVinculacionEfectivo() === 'ADICIONAL'
                );
        @endphp
        <section class="panel supplier-quote-purchase-decision">
            <div class="supplier-quote-purchase-decision__heading">
                <div>
                    <p class="eyebrow">Decisión de compra</p>
                    <h2>Aprobar compra y generar OC</h2>
                    <p>Confirma los productos y datos de emisión. La cotización quedará visible para Contabilidad y la OC pasará a Almacén.</p>
                </div>
                <span class="badge badge--info">Pendiente de decisión</span>
            </div>

            @if ($detallesElegibles->isNotEmpty())
                <form method="POST" action="{{ route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion) }}" class="supplier-quote-accounting-form" data-confirm="¿Confirmas aprobar esta compra y generar la orden de compra?">
                    @csrf
                    <input type="hidden" name="es_compra_directa" value="0">
                    <div class="supplier-quote-accounting-lines">
                        @foreach ($detallesElegibles as $detalle)
                            <label>
                                <input type="checkbox" name="detalle_ids[]" value="{{ $detalle->id }}" checked>
                                <span>
                                    <strong>{{ $detalle->producto?->codigo }} — {{ $detalle->producto?->descripcion }}</strong>
                                    <small><x-ui.quantity :value="$detalle->cantidad" /> · {{ $detalle->vinculacionVisible() }} · <x-ui.money :value="$detalle->precioFinalUnitario()" :currency="$cotizacion->moneda" /> final unitario</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="form-grid form-grid--two supplier-quote-order-fields">
                        <label class="form-field"><span>Fecha de emisión *</span><input type="date" name="fecha_emision" required value="{{ old('fecha_emision', now()->toDateString()) }}"></label>
                        <label class="form-field"><span>Entrega requerida</span><input type="date" name="fecha_entrega_requerida" value="{{ old('fecha_entrega_requerida') }}"></label>
                        <label class="form-field form-field--wide"><span>Documento del proveedor</span><input type="text" name="numero_documento_proveedor" maxlength="60" value="{{ old('numero_documento_proveedor', $cotizacion->numero_documento) }}"></label>
                        <label class="form-field"><span>Condiciones de pago</span><textarea name="condiciones_pago" rows="3" maxlength="500">{{ old('condiciones_pago', $cotizacion->condiciones_pago) }}</textarea></label>
                        <label class="form-field"><span>Condiciones de entrega</span><textarea name="condiciones_entrega" rows="3" maxlength="500">{{ old('condiciones_entrega', $cotizacion->condiciones_entrega) }}</textarea></label>
                        <label class="form-field form-field--wide"><span>Motivo de elección</span><textarea name="descripcion" rows="3" maxlength="500" placeholder="Ejemplo: proveedor elegido por disponibilidad y plazo de entrega.">{{ old('descripcion') }}</textarea></label>
                        <label class="form-field form-field--wide"><span>Observación de la OC</span><textarea name="observacion" rows="3" maxlength="500" placeholder="Indicaciones internas o de coordinación con el proveedor">{{ old('observacion') }}</textarea></label>
                    </div>
                    <div class="supplier-quote-accounting-form__footer">
                        <p><x-ui.icon name="info" :size="16" /> Se registrará quién aprobó la compra, se generará la OC y Contabilidad podrá verla sin modificarla.</p>
                        <button type="submit" class="button button--primary"><x-ui.icon name="purchase-order" :size="17" /> Aprobar compra y generar OC</button>
                    </div>
                </form>
            @elseif ($puedeCompraDirecta)
                <div class="notice notice--warning notice--block">
                    <x-ui.icon name="warning" :size="19" />
                    <div>
                        <strong>Compra sin requerimiento previo</strong>
                        <p>Esta excepción quedará identificada en la solicitud y en la orden. Debes seleccionar su origen operativo y justificarla.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('cotizaciones-proveedor.aprobar-y-generar-orden', $cotizacion) }}" class="supplier-quote-accounting-form" data-confirm="¿Confirmas registrar esta excepción y generar la orden de compra?">
                    @csrf
                    <input type="hidden" name="es_compra_directa" value="1">
                    <div class="supplier-quote-accounting-lines">
                        @foreach ($cotizacion->detalles as $detalle)
                            <label>
                                <input type="checkbox" checked disabled aria-label="Producto incluido obligatoriamente">
                                <input type="hidden" name="detalle_ids[]" value="{{ $detalle->id }}">
                                <span>
                                    <strong>{{ $detalle->producto?->codigo }} — {{ $detalle->producto?->descripcion }}</strong>
                                    <small><x-ui.quantity :value="$detalle->cantidad" /> · Producto adicional incluido · {{ $cotizacion->simboloMoneda() }} {{ number_format($detalle->precioFinalUnitario(), 2) }} final unitario</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="form-grid form-grid--two supplier-quote-order-fields">
                        <label class="form-field">
                            <span>Origen de la excepción *</span>
                            <select name="origen_compra_directa" required>
                                <option value="">Seleccionar origen</option>
                                @foreach (['COMPRA_DIRECTA' => 'Compra directa', 'REGULARIZACION' => 'Regularización', 'URGENTE' => 'Compra urgente', 'REPOSICION' => 'Reposición directa'] as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(old('origen_compra_directa') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-field"><span>Fecha de emisión *</span><input type="date" name="fecha_emision" required value="{{ old('fecha_emision', now()->toDateString()) }}"></label>
                        <label class="form-field form-field--wide"><span>Justificación de la excepción *</span><textarea name="justificacion_origen" rows="3" minlength="10" maxlength="500" required placeholder="Explica por qué se compra sin requerimiento previo">{{ old('justificacion_origen') }}</textarea></label>
                        <label class="form-field"><span>Entrega requerida</span><input type="date" name="fecha_entrega_requerida" value="{{ old('fecha_entrega_requerida') }}"></label>
                        <label class="form-field"><span>Documento del proveedor</span><input type="text" name="numero_documento_proveedor" maxlength="60" value="{{ old('numero_documento_proveedor', $cotizacion->numero_documento) }}"></label>
                        <label class="form-field"><span>Condiciones de pago</span><textarea name="condiciones_pago" rows="3" maxlength="500">{{ old('condiciones_pago', $cotizacion->condiciones_pago) }}</textarea></label>
                        <label class="form-field"><span>Condiciones de entrega</span><textarea name="condiciones_entrega" rows="3" maxlength="500">{{ old('condiciones_entrega', $cotizacion->condiciones_entrega) }}</textarea></label>
                        <label class="form-field form-field--wide"><span>Detalle de la elección</span><textarea name="descripcion" rows="3" maxlength="500" placeholder="Información adicional sobre la elección del proveedor">{{ old('descripcion') }}</textarea></label>
                        <label class="form-field form-field--wide"><span>Observación de la OC</span><textarea name="observacion" rows="3" maxlength="500" placeholder="Indicaciones internas o de coordinación con el proveedor">{{ old('observacion') }}</textarea></label>
                    </div>
                    <div class="supplier-quote-accounting-form__footer">
                        <p><x-ui.icon name="info" :size="16" /> Se comprarán todas las líneas. El sistema guardará el origen y la justificación para auditoría.</p>
                        <button type="submit" class="button button--primary"><x-ui.icon name="purchase-order" :size="17" /> Aprobar excepción y generar OC</button>
                    </div>
                </form>
            @else
                <div class="notice notice--warning notice--block"><x-ui.icon name="warning" :size="19" /><div><strong>Sin productos elegibles</strong><p>Los productos adicionales no pueden enviarse a compra. Vincula al menos una línea solicitada o alternativa.</p></div></div>
            @endif
        </section>

        <section class="panel supplier-quote-archive-panel">
            <div>
                <p class="eyebrow">No continuará a compra</p>
                <h2>Archivar sin perder el precio histórico</h2>
                <p>Usa “No requerida” cuando la necesidad desapareció y “No utilizada” cuando se eligió otra oferta o la compra no continuó.</p>
            </div>
            <form method="POST" action="{{ route('cotizaciones-proveedor.clasificar', $cotizacion) }}" class="supplier-quote-archive-form">
                @csrf @method('PATCH')
                <select name="estado" required>
                    <option value="">Seleccionar clasificación</option>
                    <option value="NO_REQUERIDA">No requerida</option>
                    <option value="NO_UTILIZADA">No utilizada</option>
                </select>
                <input type="text" name="motivo_evaluacion" minlength="5" maxlength="500" required placeholder="Motivo de la decisión">
                <button class="button button--ghost" type="submit"><x-ui.icon name="box" :size="17" /> Archivar cotización</button>
            </form>
        </section>
    @elseif ($cotizacion->estaArchivada())
        <section class="notice notice--info notice--block supplier-quote-archived">
            <x-ui.icon name="info" :size="20" />
            <div>
                <strong>{{ $cotizacion->estadoVisible() }}</strong>
                <p>
                    {{ $cotizacion->motivo_evaluacion }}
                    @if ($cotizacion->evaluado_en)
                        · {{ $cotizacion->evaluado_en->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('cotizaciones-proveedor.reactivar', $cotizacion) }}" data-confirm="¿Confirmas reactivar esta cotización para evaluarla nuevamente?">
                @csrf @method('PATCH')
                <button class="button button--ghost button--small" type="submit">Reactivar</button>
            </form>
        </section>
    @endif
