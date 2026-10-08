@extends('layouts.app')

@section('title', $proforma->codigo)
@section('page-kicker', 'Proformas')
@section('page-title', 'Detalle de proforma')

@section('content')
    @php
        $tono = match ($proforma->estado) {
            'ANULADA' => 'danger',
            'COTIZADA', 'SIN_COBRO', 'CONVERTIDA_EN_ORDEN' => 'success',
            'ENVIADA_A_LOGISTICA' => 'warning',
            default => 'info',
        };
        $ultimaCotizacion = $proforma->cotizacionesCliente->last();
        $puedeGestionar = auth()->user()->puede('proformas.cotizar');
        $puedeCrearCotizacion = $puedeGestionar
            && $proforma->estado === 'ENVIADA_A_LOGISTICA'
            && $proforma->tieneVentas();
        $puedeConfirmarSinCobro = $puedeGestionar
            && $proforma->puedeConfirmarseSinCobro();
        $puedeRegistrarSalida = auth()->user()->puede('salidas.registrar')
            && ! $proforma->estaAnulada()
            && $proforma->detalles->contains(fn ($detalle) => $detalle->cantidadPendienteSalida() > 0.0001);
        $puedeRegistrarIngreso = auth()->user()->puede('ingresos.registrar')
            && ! $proforma->estaAnulada()
            && $proforma->detalles->contains(fn ($detalle) => $detalle->esPrestamo() && $detalle->cantidadPendienteReposicion() > 0.0001);
        $puedeAnular = $puedeGestionar
            && ! $proforma->estaAnulada()
            && ! in_array($proforma->estado, ['BORRADOR', 'CONVERTIDA_EN_ORDEN'], true);
    @endphp

    <div class="proforma-detail">
    <a href="{{ route('proformas.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a proformas
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div class="proforma-detail__hero-copy">
            <p class="eyebrow">{{ $proforma->origenVisible() }}</p>
            <div class="proforma-detail__title">
                <h1>{{ $proforma->codigo }}</h1>
                <x-ui.status-badge :tone="$tono">{{ str_replace('_', ' ', $proforma->estado) }}</x-ui.status-badge>
            </div>
            <p class="proforma-detail__subtitle">
                {{ $proforma->cliente?->nombreVisible() ?: 'Cliente pendiente de definir' }}
                · {{ $proforma->fecha_emision->format('d/m/Y') }}
                @if ($puedeGestionar) · {{ $proforma->moneda }} @endif
            </p>
        </div>
        <div class="supplier-quote-hero__actions">
            @if (auth()->user()->puede('proformas.crear') && $proforma->esEditable())
                <a href="{{ route('proformas.edit', $proforma) }}" class="button button--ghost">
                    <x-ui.icon name="edit" :size="17" /> Editar borrador
                </a>
            @endif
            @if (auth()->user()->puede('proformas.crear') && $proforma->puedeEnviarse())
                <form method="POST" action="{{ route('proformas.enviar', $proforma) }}" data-confirm="¿Enviar esta proforma a Logística? Después quedará bloqueada para Almacén.">
                    @csrf @method('PATCH')
                    <button class="button button--primary" type="submit"><x-ui.icon name="check-circle" :size="17" /> Enviar a Logística</button>
                </form>
            @endif
        </div>
    </section>

    <section class="commercial-flow" aria-label="Flujo documental">
        @php
            $pasoActualAsignado = false;
            $pasosFlujo = [
                ['Proforma de Almacén', true],
                ['Revisión de Logística', $proforma->estado !== 'BORRADOR'],
                ['Venta valorizada / sin cobro', $proforma->cotizacionesCliente->contains(fn ($cotizacion) => in_array($cotizacion->estado, ['CERRADA', 'CONVERTIDA_EN_ORDEN'], true)) || $proforma->estado === 'SIN_COBRO'],
                ['Préstamos regularizados', ! $proforma->tienePrestamos() || ! $proforma->prestamosPendientes()],
            ];
        @endphp
        @foreach ($pasosFlujo as [$etiqueta, $completado])
            @php
                $noAplica = $loop->last && ! $proforma->tienePrestamos();
                $esActual = ! $completado && ! $noAplica && ! $pasoActualAsignado;
                $pasoActualAsignado = $pasoActualAsignado || $esActual;
            @endphp
            <div @class([
                'commercial-flow__step',
                'is-complete' => $completado && ! $noAplica,
                'is-current' => $esActual,
                'is-not-applicable' => $noAplica,
            ]) @if ($esActual) aria-current="step" @endif>
                <span>@if ($noAplica) — @else <x-ui.icon :name="$completado ? 'check-circle' : 'clipboard'" :size="18" /> @endif</span>
                <strong>{{ $etiqueta }}</strong>
                @if ($noAplica)<small>No aplica</small>@endif
            </div>
        @endforeach
    </section>

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <h2>Productos y despacho</h2>
                <p>El stock cambia al confirmar la Nota de Salida.</p>
            </div>
            @if ($puedeRegistrarSalida)
                <a href="{{ route('notas-salida.create', ['motivo_salida' => 'PROFORMA', 'proforma_id' => $proforma->id]) }}" class="button button--ghost button--small">
                    <x-ui.icon name="exit" :size="16" /> Registrar salida física
                </a>
            @endif
        </header>
        <div class="table-wrap table-wrap--wide">
            <table @class([
                'data-table data-table--detail',
                'proforma-request-table' => ! $puedeGestionar,
                'data-table--wide' => $puedeGestionar,
            ])>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-right">Cantidad</th>
                        <th>Tratamiento</th>
                        <th>Despachado</th>
                        <th class="text-right">Pendiente de salida</th>
                        <th>Estado</th>
                        @if ($puedeGestionar)
                            <th class="text-right">Costo ref.</th>
                            <th class="text-right">Sugerido</th>
                            <th>IGV</th>
                            <th class="text-right">Total</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($proforma->detalles as $detalle)
                        @php
                            $despachado = (float) $detalle->cantidadDespachada();
                            $pendiente = (float) $detalle->cantidadPendienteSalida();
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $detalle->codigo_producto }}</strong>
                                <span>{{ $detalle->descripcion }}</span>
                                @if ($detalle->observacion)
                                    <span>Referencia: {{ $detalle->observacion }}</span>
                                @endif
                            </td>
                            <td class="text-right"><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->unidad_medida }}</td>
                            <td>
                                <x-ui.status-badge :tone="$detalle->esPrestamo() ? 'warning' : 'success'">
                                    {{ $detalle->esPrestamo() ? 'PRÉSTAMO' : 'VENTA' }}
                                </x-ui.status-badge>
                                @if ($detalle->esPrestamo())
                                    <span>Prestado: <x-ui.quantity :value="$detalle->cantidadPrestadaFisicamente()" /> · Repuesto: <x-ui.quantity :value="$detalle->cantidadRepuesta()" /></span>
                                @endif
                            </td>
                            <td>
                                <strong><x-ui.quantity :value="$despachado" /> / <x-ui.quantity :value="$detalle->cantidad" /></strong>
                                <progress class="proforma-detail__progress" value="{{ min(max(0, $despachado), (float) $detalle->cantidad) }}" max="{{ max(0.001, (float) $detalle->cantidad) }}" aria-label="Despacho de {{ $detalle->codigo_producto }}"></progress>
                            </td>
                            <td class="text-right"><strong><x-ui.quantity :value="$pendiente" /></strong></td>
                            <td>
                                @if ($pendiente <= 0.0001)
                                    <x-ui.status-badge tone="success">Despachado</x-ui.status-badge>
                                @elseif ($despachado > 0.0001)
                                    <x-ui.status-badge tone="warning">Parcial</x-ui.status-badge>
                                @else
                                    <x-ui.status-badge tone="info">Pendiente</x-ui.status-badge>
                                @endif
                            </td>
                            @if ($puedeGestionar)
                                <td class="text-right">S/ {{ number_format((float) $detalle->costo_referencia, 2) }}</td>
                                @if ($detalle->esVenta())
                                    <td class="text-right"><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $detalle->precio_sugerido, 2) }}</strong></td>
                                    <td>Por definir</td>
                                    <td class="text-right"><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $detalle->total, 2) }}</strong></td>
                                @else
                                    <td class="text-right">—</td>
                                    <td>No aplica</td>
                                    <td class="text-right"><strong>Sin cobro</strong></td>
                                @endif
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="proforma-detail__summary">
        <article class="panel supplier-quote-info-panel">
            <header class="supplier-panel-heading"><div><h2>Datos de la solicitud</h2></div></header>
            <dl class="supplier-info-grid">
                <div><dt>Tipo de cliente</dt><dd>{{ $proforma->cliente?->tipoCliente?->nombre ?: 'Pendiente' }}</dd></div>
                <div><dt>Registrada por</dt><dd>{{ $proforma->registrador?->nombreVisible() }}</dd></div>
                <div><dt>Enviada por</dt><dd>{{ $proforma->enviador?->nombreVisible() ?: 'Todavía no enviada' }}</dd></div>
                @if ($puedeGestionar)
                    <div><dt>Margen sugerido</dt><dd>{{ number_format((float) $proforma->margen_cliente_porcentaje, 2) }} %</dd></div>
                @endif
                @if ($proforma->observacion)
                    <div class="supplier-info-grid__wide"><dt>Observación</dt><dd>{{ $proforma->observacion }}</dd></div>
                @endif
            </dl>
        </article>
        @if ($puedeGestionar)
            <article class="panel supplier-quote-total-card">
                <p class="eyebrow">Valor sugerido</p>
                <div><span>Subtotal</span><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $proforma->subtotal, 2) }}</strong></div>
                <div><span>IGV (por definir)</span><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $proforma->impuesto, 2) }}</strong></div>
                <div class="supplier-quote-total-card__main"><span>Total</span><strong>{{ $proforma->simboloMoneda() }} {{ number_format((float) $proforma->total, 2) }}</strong></div>
                <small>Solo considera líneas de venta. Los préstamos no forman parte del monto a cobrar.</small>
            </article>
        @endif
    </section>

    @if ($proforma->detalles->contains(fn ($detalle) => $detalle->esPrestamo()))
        <section class="panel commercial-history-panel">
            <header class="supplier-panel-heading">
                <div>
                    <p class="eyebrow">Préstamos</p>
                    <h2>Reposiciones físicas</h2>
                    <p>Una reposición vuelve a Almacén mediante Nota de Ingreso. Así inventario y Kardex se actualizan en el mismo movimiento.</p>
                </div>
                @if ($puedeRegistrarIngreso)
                    <a href="{{ route('notas-ingreso.create', ['motivo_ingreso' => 'REPOSICION_PRESTAMO', 'proforma_id' => $proforma->id]) }}" class="button button--ghost button--small">
                        <x-ui.icon name="entry" :size="16" /> Registrar reposición
                    </a>
                @endif
            </header>
            <div class="table-wrap">
                <table class="data-table data-table--detail">
                    <thead>
                        <tr><th>Producto</th><th class="text-right">Prestado físicamente</th><th class="text-right">Repuesto</th><th class="text-right">Pendiente</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($proforma->detalles->filter(fn ($detalle) => $detalle->esPrestamo()) as $detalle)
                            <tr>
                                <td><strong>{{ $detalle->codigo_producto }}</strong><span>{{ $detalle->descripcion }}</span></td>
                                <td class="text-right"><x-ui.quantity :value="$detalle->cantidadPrestadaFisicamente()" /></td>
                                <td class="text-right"><x-ui.quantity :value="$detalle->cantidadRepuesta()" /></td>
                                <td class="text-right"><strong><x-ui.quantity :value="$detalle->cantidadPendienteReposicion()" /></strong></td>
                                <td>
                                    @if ($detalle->prestamoRegularizado())
                                        <x-ui.status-badge tone="success">Regularizado</x-ui.status-badge>
                                    @elseif ($detalle->cantidadPrestadaFisicamente() <= 0.0001)
                                        <x-ui.status-badge tone="info">Pendiente de salida</x-ui.status-badge>
                                    @else
                                        <x-ui.status-badge tone="warning">Pendiente de reposición</x-ui.status-badge>
                                    @endif
                                </td>
                            </tr>
                            @if ($detalle->reposiciones->isNotEmpty())
                                <tr>
                                    <td colspan="5">
                                        <small>
                                            Historial de ingresos:
                                            @foreach ($detalle->reposiciones as $reposicion)
                                                <x-ui.quantity :value="$reposicion->cantidad" /> el {{ $reposicion->registrado_en?->format('d/m/Y H:i') }} por {{ $reposicion->registrador?->nombreVisible() }}@if (! $loop->last); @endif
                                            @endforeach
                                        </small>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($proforma->cotizacionesCliente->isNotEmpty())
        <section class="panel commercial-history-panel">
            <header class="supplier-panel-heading">
                <div><p class="eyebrow">Trazabilidad</p><h2>Historial de cotizaciones</h2></div>
            </header>
            <div class="commercial-version-list">
                @foreach ($proforma->cotizacionesCliente as $cotizacion)
                    @php
                        $tonoVersion = match ($cotizacion->estado) {
                            'ANULADA' => 'danger',
                            'CERRADA', 'CONVERTIDA_EN_ORDEN' => 'success',
                            default => 'info',
                        };
                    @endphp
                    <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}" class="commercial-version-card">
                        <span class="commercial-version-card__number">VRS{{ $cotizacion->version }}</span>
                        <span><strong>{{ $cotizacion->codigo }}</strong><small>{{ $cotizacion->fecha_emision->format('d/m/Y') }} · {{ $cotizacion->cliente_nombre }}</small></span>
                        <span class="badge badge--{{ $tonoVersion }}">{{ $cotizacion->estado }}</span>
                        <strong>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2) }}</strong>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($proforma->estaAnulada())
        <section class="notice notice--danger notice--block commercial-cancelled">
            <x-ui.icon name="error" :size="20" />
            <div><strong>Proforma anulada</strong><span>{{ $proforma->motivo_anulacion }} · {{ $proforma->anulado_en?->format('d/m/Y H:i') }}</span></div>
        </section>
    @elseif ($puedeCrearCotizacion || $puedeConfirmarSinCobro || $puedeAnular)
        <section class="panel commercial-quote-actions">
            <header class="panel-heading">
                <p class="eyebrow">Control documental</p>
                <h2>Acciones de la proforma</h2>
                <p>Valoriza las líneas de venta, confirma los préstamos sin cobro o anula la proforma sin borrar su historial.</p>
            </header>

            <div @class([
                'commercial-quote-actions__grid',
                'commercial-quote-actions__grid--single' => collect([$puedeCrearCotizacion, $puedeConfirmarSinCobro, $puedeAnular])->filter()->count() === 1,
            ])>
                @if ($puedeCrearCotizacion)
                    <article class="commercial-quote-action commercial-quote-action--approve">
                        <div>
                            <h3>Crear cotización abierta</h3>
                            <p>Se generará {{ $ultimaCotizacion ? 'una nueva cotización' : 'la VRS1' }} para que Logística defina precios, IGV y condiciones.</p>
                        </div>
                        <form method="POST" action="{{ route('proformas.cotizar', $proforma) }}" class="commercial-quote-action__form">
                            @csrf
                            @if (! $proforma->cliente_id)
                                <div class="form-field">
                                    <label for="cliente_cotizacion_busqueda">Cliente <span class="required-mark">*</span></label>
                                    <x-ui.remote-combobox
                                        name="cliente_id"
                                        search-id="cliente_cotizacion_busqueda"
                                        value-id="cliente_cotizacion_id"
                                        :search-url="route('catalogos.clientes.buscar')"
                                        placeholder="Documento o nombre"
                                        empty-text="Cliente no encontrado. Regístralo primero."
                                        :required="true"
                                    />
                                </div>
                            @endif
                            <button class="button button--primary" type="submit">
                                <x-ui.icon name="quotes" :size="17" /> Cotizar ahora
                            </button>
                        </form>
                    </article>
                @endif

                @if ($puedeConfirmarSinCobro)
                    <article class="commercial-quote-action commercial-quote-action--approve">
                        <div>
                            <h3>Confirmar operación sin cobro</h3>
                            <p>La proforma contiene únicamente préstamos. No se crea cotización ni orden; quedará pendiente su reposición.</p>
                        </div>
                        <form method="POST" action="{{ route('proformas.sin-cobro', $proforma) }}" class="commercial-quote-action__form" data-confirm="¿Confirmar que esta proforma no tiene líneas de venta por cobrar?">
                            @csrf
                            @method('PATCH')
                            <button class="button button--primary" type="submit">Confirmar sin cobro</button>
                        </form>
                    </article>
                @endif

                @if (($puedeCrearCotizacion || $puedeConfirmarSinCobro) && $puedeAnular)
                    <div class="commercial-quote-actions__divider" aria-hidden="true"></div>
                @endif

                @if ($puedeAnular)
                    <article class="commercial-quote-action commercial-quote-action--cancel">
                        <div>
                            <h3>Anular proforma</h3>
                            <p>El motivo, usuario y fecha quedarán registrados. Las cotizaciones cerradas seguirán visibles.</p>
                        </div>
                        <form method="POST" action="{{ route('proformas.anular', $proforma) }}"
                            class="commercial-quote-action__form"
                            data-confirm="¿Confirmas anular esta proforma?"
                            data-confirm-title="Anular proforma"
                            data-confirm-label="Anular proforma"
                            data-confirm-tone="danger">
                            @csrf
                            @method('PATCH')
                            <label class="form-field">
                                <span>Motivo de anulación <span class="required-mark">*</span></span>
                                <input type="text" name="motivo_anulacion" minlength="5" maxlength="500" required placeholder="Explica el motivo">
                            </label>
                            <button type="submit" class="button button--danger">
                                <x-ui.icon name="error" :size="17" /> Anular
                            </button>
                        </form>
                    </article>
                @endif
            </div>
        </section>
    @endif
    </div>
@endsection
