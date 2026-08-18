@extends('layouts.app')

@section('title', $cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Cotización al cliente')

@section('content')
    @php
        $esUltima = $cotizacion->version === (int) $versiones->max('version');
        $puedeGestionar = auth()->user()->puede('proformas.cotizar');
        $puedeAprobar = $puedeGestionar && $cotizacion->puedeConvertirseEnOrden();
        $puedeCerrarParaCobro = $puedeGestionar
            && $cotizacion->proforma_id !== null
            && $cotizacion->estado === 'ABIERTA';
        $puedeAnular = $puedeGestionar
            && ! $cotizacion->estaAnulada()
            && $cotizacion->estado !== 'CONVERTIDA_EN_ORDEN';
        $codigoTipoOrden = $cotizacion->tipoOrden?->codigo;
        $esProduccion = $codigoTipoOrden === 'OP';
        $esServicioMantenimiento = in_array($codigoTipoOrden, ['OM', 'OS'], true);
    @endphp

    <a href="{{ $cotizacion->proforma
        ? route('proformas.show', $cotizacion->proforma)
        : route('cotizaciones-cliente.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        {{ $cotizacion->proforma
            ? 'Volver a '.$cotizacion->proforma->codigo
            : 'Volver a cotizaciones' }}
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div>
            <p class="eyebrow">{{ $cotizacion->origenVisible() }} · Versión {{ $cotizacion->version }}</p>
            <h1>{{ $cotizacion->codigo }}</h1>
            <p>{{ $cotizacion->cliente_nombre }} · {{ $cotizacion->fecha_emision->format('d/m/Y') }} · {{ $cotizacion->moneda }}</p>
        </div>
        <div class="supplier-quote-hero__actions">
            <x-ui.status-badge :tone="$cotizacion->tonoEstadoVisual()" class="badge--large">
                {{ $cotizacion->estadoVisual() }}
            </x-ui.status-badge>
            @if (auth()->user()->puede('proformas.cotizar') && $cotizacion->esEditable())
                <a href="{{ route('cotizaciones-cliente.edit', $cotizacion) }}" class="button button--primary"><x-ui.icon name="edit" :size="17" /> Continuar cotizando</a>
            @endif
        </div>
    </section>

    <nav class="commercial-version-tabs" aria-label="Versiones de cotización">
        @foreach ($versiones as $version)
            <a href="{{ route('cotizaciones-cliente.show', $version) }}" class="{{ $version->is($cotizacion) ? 'is-active' : '' }}">
                VRS{{ $version->version }} <span>{{ $version->estadoVisual() }}</span>
            </a>
        @endforeach
    </nav>

    @if ($puedeGestionar)
        <section class="notice notice--info notice--block">
            <x-ui.icon name="quotes" :size="20" />
            <div>
                <strong>Presupuesto interno de ejecución</strong>
                <span>Registra materiales, personal, servicios, transporte, viáticos y consumibles en PEN o USD. Esta información nunca se muestra en el documento del cliente.</span>
            </div>
            <a href="{{ route('cotizaciones-cliente.presupuesto.show', $cotizacion) }}" class="button button--secondary">
                Gestionar presupuesto
            </a>
        </section>
    @endif

    <section class="supplier-quote-detail-grid">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Información</p><h2>Datos comerciales</h2></div></header>
            <dl class="supplier-info-grid">
                <div>
                    <dt>Origen</dt>
                    <dd>
                        @if ($cotizacion->proforma)
                            <a href="{{ route('proformas.show', $cotizacion->proforma) }}">{{ $cotizacion->proforma->codigo }}</a>
                        @else
                            Cotización directa de Logística
                        @endif
                    </dd>
                </div>
                <div><dt>Cliente</dt><dd>{{ $cotizacion->cliente_nombre }}</dd></div>
                <div><dt>Documento</dt><dd>{{ $cotizacion->cliente_documento ?: 'No registrado' }}</dd></div>
                <div><dt>Tipo de cliente</dt><dd>{{ $cotizacion->cliente?->tipoCliente?->nombre ?: 'No definido' }}</dd></div>
                @if ($cotizacion->proforma)
                    <div><dt>Destino</dt><dd>Valorización para cobro · Sin OV</dd></div>
                    <div><dt>Vehículo</dt><dd>No aplica</dd></div>
                @else
                    <div><dt>Trabajo</dt><dd>{{ $cotizacion->tipoOrden?->codigo }} · {{ $cotizacion->tipoOrden?->nombre ?: 'Pendiente de completar' }}</dd></div>
                    <div><dt>Vehículo</dt><dd>{{ $cotizacion->vehiculo?->identificadorVisible() ?: 'No aplica' }}</dd></div>
                @endif
                <div><dt>Cotizada por</dt><dd>{{ $cotizacion->cotizador?->nombreVisible() }}</dd></div>
                <div><dt>Cerrada por</dt><dd>{{ $cotizacion->cerrador?->nombreVisible() ?: 'Aún abierta' }}</dd></div>
                @unless ($cotizacion->proforma)
                    <div>
                        <dt>Orden vinculada</dt>
                        <dd>
                            @if ($cotizacion->ordenOperacion)
                                <a href="{{ route('ordenes-operacion.show', $cotizacion->ordenOperacion) }}">{{ $cotizacion->ordenOperacion->codigo_orden }}</a>
                            @else
                                Aún no convertida
                            @endif
                        </dd>
                    </div>
                @endunless
                <div><dt>Condiciones de pago</dt><dd>{{ $cotizacion->condiciones_pago ?: 'No especificadas' }}</dd></div>
                <div><dt>Condiciones de entrega</dt><dd>{{ $cotizacion->condiciones_entrega ?: 'No especificadas' }}</dd></div>
                <div class="supplier-info-grid__wide"><dt>{{ $cotizacion->proforma ? 'Referencia' : 'Descripción del trabajo' }}</dt><dd>{{ $cotizacion->descripcion_trabajo ?: 'Sin referencia adicional' }}</dd></div>
                @if ($cotizacion->observacion)<div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $cotizacion->observacion }}</dd></div>@endif
            </dl>
        </article>
        <article class="panel supplier-quote-total-card">
            <p class="eyebrow">Resumen económico</p>
            <div><span>Subtotal</span><strong><x-ui.money :value="$cotizacion->subtotal" :currency="$cotizacion->moneda" /></strong></div>
            <div><span>IGV</span><strong><x-ui.money :value="$cotizacion->impuesto" :currency="$cotizacion->moneda" /></strong></div>
            <div class="supplier-quote-total-card__main"><span>Total</span><strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong></div>
            @if ($cotizacion->moneda === 'USD')<small>Tipo de cambio (PEN por USD): {{ rtrim(rtrim(number_format((float) $cotizacion->tipo_cambio, 2, '.', ''), '0'), '.') }}</small>@endif
        </article>
    </section>

    @if ($cotizacion->proforma)
        <section class="panel supplier-quote-detail-lines">
            <header class="supplier-panel-heading"><div><p class="eyebrow">Detalle</p><h2>Productos de la venta</h2></div></header>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Sugerido</th><th class="text-right">Cotizado</th><th>IGV</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($cotizacion->detalles as $detalle)
                            <tr>
                                <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                                <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                                <td class="text-right"><x-ui.money :value="$detalle->precio_sugerido" :currency="$cotizacion->moneda" /></td>
                                <td class="text-right"><strong><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion->moneda" /></strong>@if ($detalle->precioFueAjustado())<span>Ajustado por Logística</span>@endif</td>
                                <td>{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                                <td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" /></strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        @if ($esServicioMantenimiento)
            <section class="panel supplier-quote-detail-lines commercial-client-summary commercial-client-materials">
                <header class="supplier-panel-heading">
                    <div>
                        <p class="eyebrow">Propuesta comercial</p>
                        <h2>Trabajo, materiales y repuestos para el cliente</h2>
                        <p>En OM y OS el cliente sí ve los materiales o repuestos cotizados. Los costos de referencia, sugeridos y márgenes permanecen internos.</p>
                    </div>
                </header>
                <div class="notice notice--info notice--block">
                    <x-ui.icon name="orders" :size="18" />
                    <div>
                        <strong>Trabajo cotizado</strong>
                        <span>{{ $cotizacion->descripcion_trabajo }}</span>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Material / repuesto</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Precio unitario</th>
                                <th>IGV</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cotizacion->detalles as $detalle)
                                <tr>
                                    <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                                    <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                                    <td class="text-right"><strong><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion->moneda" /></strong></td>
                                    <td>{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                                    <td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" /></strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="panel supplier-quote-detail-lines commercial-client-summary">
                <header class="supplier-panel-heading">
                    <div>
                        <p class="eyebrow">Propuesta comercial</p>
                        <h2>Resumen que corresponde al cliente</h2>
                        <p>{{ $esProduccion
                            ? 'En Producción el cliente ve la capacidad o descripción del trabajo y el importe final. La composición de materiales permanece interna.'
                            : 'El documento comercial utiliza el concepto del trabajo y el importe final.' }}</p>
                    </div>
                </header>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Concepto</th><th class="text-right">Importe</th></tr></thead>
                        <tbody>
                            <tr>
                                <td><strong>{{ $cotizacion->descripcion_trabajo }}</strong><span>{{ $cotizacion->tipoOrden?->codigo }} · {{ $cotizacion->tipoOrden?->nombre }}</span></td>
                                <td class="text-right"><strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($puedeGestionar)
            <section class="panel supplier-quote-detail-lines commercial-internal-composition">
                <header class="supplier-panel-heading">
                    <div>
                        <p class="eyebrow">Uso interno · No incluir en documento al cliente</p>
                        <h2>{{ $esProduccion ? 'Composición interna valorizada' : 'Control interno de valorización' }}</h2>
                        <p>{{ $esProduccion
                            ? 'Esta previsión forma el precio de la fabricación y, al convertir la cotización, genera la lista inicial de materiales de la OP.'
                            : 'El cliente ve los materiales/repuestos y sus precios cotizados; esta vista adicional conserva la referencia sugerida y el control interno de Logística.' }}</p>
                    </div>
                </header>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Material / producto</th><th class="text-right">Cantidad prevista</th><th class="text-right">Referencia interna</th><th class="text-right">Precio cotizado</th><th>IGV</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                            @foreach ($cotizacion->detalles as $detalle)
                                <tr>
                                    <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                                    <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                                    <td class="text-right"><x-ui.money :value="$detalle->precio_sugerido" :currency="$cotizacion->moneda" /></td>
                                    <td class="text-right"><strong><x-ui.money :value="$detalle->precio_unitario" :currency="$cotizacion->moneda" /></strong>@if ($detalle->precioFueAjustado())<span>Ajustado por Logística</span>@endif</td>
                                    <td>{{ str_replace('_', ' ', $detalle->igv_modo) }}</td>
                                    <td class="text-right"><strong><x-ui.money :value="$detalle->total" :currency="$cotizacion->moneda" /></strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif

    @if ($cotizacion->proforma)
        <x-ui.collapsible-notice title="Esta cotización no genera Orden de Venta" label="Ver información sobre el flujo de esta cotización">
            <span>Solo contiene las líneas marcadas como Venta en {{ $cotizacion->proforma->codigo }}. Los préstamos se controlan y reponen desde la Proforma.</span>
        </x-ui.collapsible-notice>
    @endif

    @if ($cotizacion->proforma && $cotizacion->estado === 'CERRADA')
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div>
                <strong>Valorización lista para cobro</strong>
                <span>El total quedó cerrado. La integración con Contabilidad/Cuentas por cobrar se realizará en su módulo correspondiente.</span>
            </div>
        </section>
    @endif

    @if ($puedeAprobar || $puedeCerrarParaCobro || $puedeAnular)
        <section class="panel commercial-quote-actions">
            <header class="panel-heading">
                <p class="eyebrow">Control documental</p>
                <h2>Acciones de la cotización</h2>
                <p>{{ $cotizacion->proforma ? 'Cierra la valorización para cobro o anúlala conservando el historial.' : 'Aprueba el documento o anúlalo sin eliminar su historial.' }}</p>
            </header>

            <div class="commercial-quote-actions__grid">
                @if ($puedeCerrarParaCobro)
                    <article class="commercial-quote-action commercial-quote-action--approve">
                        <div>
                            <h3>Cerrar valorización para cobro</h3>
                            <p>Verifica precios, moneda e IGV. Al cerrar, esta versión quedará bloqueada y lista para Contabilidad.</p>
                        </div>
                        <form method="POST" action="{{ route('cotizaciones-cliente.cerrar', $cotizacion) }}" class="commercial-quote-action__form" data-confirm="¿Cerrar esta valorización y dejarla lista para cobro?">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="button button--primary">
                                <x-ui.icon name="check-circle" :size="17" /> Cerrar para cobro
                            </button>
                        </form>
                    </article>
                @endif

                @if ($puedeAprobar)
                    <article class="commercial-quote-action commercial-quote-action--approve">
                        <div>
                            <h3>Aprobar y generar {{ $cotizacion->tipoOrden?->codigo ?: 'la orden' }}</h3>
                            <p>
                                La orden heredará cliente, trabajo, productos y vehículo.
                                Esta acción no descuenta stock ni genera Kardex.
                            </p>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('cotizaciones-cliente.convertir-orden', $cotizacion) }}"
                            class="commercial-quote-action__form"
                            data-confirm="¿Aprobar esta cotización y generar su orden?"
                            data-confirm-title="Aprobar cotización"
                            data-confirm-label="Aprobar y generar orden"
                            data-confirm-tone="info"
                        >
                            @csrf
                            @if (! $cotizacion->tieneContextoOperativoCompleto())
                                <div class="notice notice--warning notice--block">
                                    <x-ui.icon name="warning" :size="18" />
                                    <span>Registro anterior: completa sus datos operativos una sola vez.</span>
                                </div>
                                <label class="form-field">
                                    <span>Tipo de orden <span class="required-mark">*</span></span>
                                    <select name="tipo_orden_id" required>
                                        <option value="">Selecciona el tipo</option>
                                        @foreach ($tiposOrden as $tipo)
                                            <option value="{{ $tipo->id }}" @selected($cotizacion->tipo_orden_id === $tipo->id)>
                                                {{ $tipo->codigo }} — {{ $tipo->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="form-field">
                                    <span>Ubicación de referencia</span>
                                    <select name="cliente_direccion_id">
                                        <option value="">Sin ubicación asociada</option>
                                        @foreach ($direccionesCliente as $direccion)
                                            <option value="{{ $direccion->id }}">
                                                {{ $direccion->es_fiscal ? 'Dirección fiscal — ' : '' }}{{ $direccion->destino ?: $direccion->direccion }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="form-field">
                                    <span>Vehículo o unidad</span>
                                    <select name="vehiculo_id">
                                        <option value="">Sin vehículo asociado</option>
                                        @foreach ($vehiculosCliente as $vehiculo)
                                            <option value="{{ $vehiculo->id }}">{{ $vehiculo->identificadorVisible() }} · {{ $vehiculo->descripcionVisible() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="form-field">
                                    <span>Descripción del trabajo <span class="required-mark">*</span></span>
                                    <textarea name="descripcion" minlength="5" maxlength="500" required>{{ $cotizacion->descripcion_trabajo }}</textarea>
                                </label>
                            @endif
                            <label class="form-field">
                                <span>Fecha de apertura <span class="required-mark">*</span></span>
                                <input type="date" name="fecha_apertura" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" required>
                            </label>
                            <button type="submit" class="button button--primary">
                                <x-ui.icon name="orders" :size="17" /> Aprobar y generar orden
                            </button>
                        </form>
                    </article>
                @endif

                @if (($puedeAprobar || $puedeCerrarParaCobro) && $puedeAnular)
                    <div class="commercial-quote-actions__divider" aria-hidden="true"></div>
                @endif

                @if ($puedeAnular)
                    <article class="commercial-quote-action commercial-quote-action--cancel">
                        <div>
                            <h3>Anular esta versión</h3>
                            <p>La versión seguirá visible con usuario, fecha y motivo.</p>
                        </div>
                        <form
                            method="POST"
                            action="{{ route('cotizaciones-cliente.anular', $cotizacion) }}"
                            class="commercial-quote-action__form"
                            data-confirm="¿Confirmas anular esta versión?"
                            data-confirm-title="Anular cotización"
                            data-confirm-label="Anular versión"
                            data-confirm-tone="danger"
                        >
                            @csrf
                            @method('PATCH')
                            <label class="form-field">
                                <span>Motivo de anulación <span class="required-mark">*</span></span>
                                <input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required placeholder="Explica el motivo">
                            </label>
                            <button type="submit" class="button button--danger">
                                <x-ui.icon name="error" :size="17" /> Anular versión
                            </button>
                        </form>
                    </article>
                @endif
            </div>
        </section>
    @elseif ($cotizacion->ordenOperacion)
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div>
                <strong>Versión vinculada con una orden</strong>
                <span>
                    Esta cotización originó
                    <a href="{{ route('ordenes-operacion.show', $cotizacion->ordenOperacion) }}">{{ $cotizacion->ordenOperacion->codigo_orden }}</a>.
                </span>
            </div>
        </section>
    @endif

    @if (auth()->user()->puede('proformas.cotizar') && $cotizacion->puedeCrearVersion() && $esUltima)
        <section class="commercial-action-panel commercial-action-panel--primary">
            <div><p class="eyebrow">Solicitud posterior</p><h2>Crear VRS{{ $cotizacion->version + 1 }}</h2><p>Se copiará esta versión y la nueva quedará abierta para modificarla.</p></div>
            <form method="POST" action="{{ route('cotizaciones-cliente.version', $cotizacion) }}">@csrf<button class="button button--ghost" type="submit"><x-ui.icon name="plus" :size="17" /> Nueva versión</button></form>
        </section>
    @endif

    @if ($cotizacion->estaAnulada())
        <section class="notice notice--danger notice--block commercial-cancelled"><x-ui.icon name="error" :size="20" /><div><strong>Versión anulada</strong><span>{{ $cotizacion->motivo_anulacion }} · {{ $cotizacion->anulado_en?->format('d/m/Y H:i') }}</span></div></section>
    @endif
@endsection
