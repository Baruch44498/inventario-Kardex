@extends('layouts.app')

@section('title', $cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Detalle de cotización')

@section('content')
    <a href="{{ route('cotizaciones-proveedor.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a cotizaciones
    </a>

    <section class="supplier-quote-hero">
        <div>
            <p class="eyebrow">Cotización de proveedor</p>
            <h1>{{ $cotizacion->codigo }}</h1>
            <p>
                {{ $cotizacion->proveedor->nombreVisible() }}
                · {{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}
                · {{ $cotizacion->moneda }}
            </p>
        </div>

        <div class="supplier-quote-hero__actions">
            <span class="badge badge--{{ $cotizacion->estadoClase() }}">
                {{ $cotizacion->estadoVisible() }}
            </span>

            @if ($cotizacion->puedeEditar())
                <a href="{{ route('cotizaciones-proveedor.edit', $cotizacion->id) }}"
                    class="button button--ghost">
                    <x-ui.icon name="edit" :size="17" /> Editar
                </a>
            @endif

            <a href="{{ route('historial-precios.index', ['proveedor_id' => $cotizacion->proveedor_id]) }}"
                class="button button--primary">
                <x-ui.icon name="banknote" :size="17" /> Ver historial
            </a>
        </div>
    </section>

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
                            <a href="{{ route('cotizaciones-proveedor.documento-original', $cotizacion) }}">
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

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Detalle de precios</p>
                <h2>Productos cotizados</h2>
            </div>
        </header>

        <div class="table-wrap supplier-quote-detail-table-wrap"
             role="region"
             aria-label="Detalle de productos cotizados"
             tabindex="0">
            <table class="data-table supplier-quote-detail-table">
                <thead>
                    <tr>
                        <th scope="col">Producto</th>
                        <th scope="col">Requerimiento</th>
                        <th scope="col">Marca ofrecida</th>
                        <th scope="col" class="text-right">Cantidad</th>
                        <th scope="col" class="text-right">Precio informado</th>
                        <th scope="col">Descuento</th>
                        <th scope="col">IGV</th>
                        <th scope="col" class="text-right">Base sin IGV</th>
                        <th scope="col" class="text-right">IGV línea</th>
                        <th scope="col" class="text-right">Total línea</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cotizacion->detalles as $detalle)
                        <tr>
                            <td class="supplier-quote-detail-table__product">
                                <strong>{{ $detalle->producto?->codigo }}</strong>
                                <span>{{ $detalle->producto?->descripcion }}</span>
                            </td>
                            <td class="supplier-quote-detail-table__relation">
                                <span class="badge badge--{{ $detalle->tipoVinculacionEfectivo() === 'SOLICITADO' ? 'success' : ($detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA' ? 'warning' : 'neutral') }}">
                                    {{ $detalle->vinculacionVisible() }}
                                </span>
                                @if ($detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA' && $detalle->requisicionDetalle)
                                    <strong>
                                        Solicitado: {{ $detalle->requisicionDetalle->producto?->codigo }}
                                    </strong>
                                    <span>{{ $detalle->requisicionDetalle->producto?->descripcion }}</span>
                                    <span><x-ui.quantity :value="$detalle->requisicionDetalle->cantidad_solicitada" /> solicitado</span>
                                @elseif ($detalle->requisicionDetalle)
                                    <strong><x-ui.quantity :value="$detalle->requisicionDetalle->cantidad_solicitada" /> solicitado</strong>
                                    <span>Esta oferta: <x-ui.quantity :value="$detalle->cantidad" /></span>
                                @else
                                    <span class="text-muted">Producto adicional de la oferta</span>
                                @endif
                                @if ($detalle->codigo_documento || $detalle->descripcion_documento)
                                    <small>
                                        Documento: {{ collect([$detalle->codigo_documento, $detalle->descripcion_documento])->filter()->implode(' — ') }}
                                    </small>
                                @endif
                            </td>
                            <td class="supplier-quote-detail-table__brand">
                                <strong>{{ $detalle->marca_ofertada ?: 'No especificada' }}</strong>
                                @if ($detalle->observacion)<span>{{ $detalle->observacion }}</span>@endif
                            </td>
                            <td class="text-right supplier-quote-detail-table__quantity">
                                <x-ui.quantity :value="$detalle->cantidad" />
                            </td>
                            <td class="text-right supplier-quote-detail-table__money">
                                <x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion->moneda" />
                            </td>
                            <td class="supplier-quote-detail-table__discount"><strong>{{ $detalle->descuentoVisible() }}</strong></td>
                            <td class="supplier-quote-detail-table__tax"><span>{{ $detalle->igvVisible() }}</span></td>
                            <td class="text-right supplier-quote-detail-table__money">
                                <x-ui.money :value="$detalle->subtotal" :currency="$cotizacion->moneda" />
                            </td>
                            <td class="text-right supplier-quote-detail-table__money">
                                <x-ui.money :value="$detalle->impuesto" :currency="$cotizacion->moneda" />
                            </td>
                            <td class="text-right supplier-quote-detail-table__money supplier-quote-detail-table__total">
                                <strong>
                                    <x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" />
                                </strong>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

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

    @if ($cotizacion->estaAnulada())
        <section class="notice notice--danger notice--block supplier-quote-cancelled">
            <x-ui.icon name="error" :size="20" />
            <div>
                <strong>Cotización invalidada</strong>
                <p>
                    {{ $cotizacion->motivo_anulacion }}
                    @if ($cotizacion->anulado_en)
                        · {{ $cotizacion->anulado_en->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>
        </section>
    @elseif ($cotizacion->puedeInvalidar())
        <section class="supplier-quote-danger-zone">
            <div>
                <p class="eyebrow">Control documental</p>
                <h2>Invalidar por error de registro</h2>
                <p>No la invalides solo porque no fue elegida: en ese caso debe permanecer como referencia histórica.</p>
            </div>

            <form method="POST"
                action="{{ route('cotizaciones-proveedor.anular', $cotizacion->id) }}"
                class="supplier-quote-cancel-form"
                data-confirm="¿Confirmas invalidar esta cotización por un error de registro?">
                @csrf
                @method('PATCH')
                <input type="text" name="motivo_anulacion" maxlength="500"
                    minlength="5" required placeholder="Describe el error de registro">
                <button type="submit" class="button button--danger">
                    <x-ui.icon name="error" :size="17" /> Invalidar
                </button>
            </form>
        </section>
    @endif
@endsection
