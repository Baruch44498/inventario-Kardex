@extends('layouts.app')

@section('title', $requerimiento->codigo)
@section('page-kicker', 'Requerimiento de compra')
@section('page-title', $requerimiento->codigo)

@section('content')
    @php
        $estadoClase = match ($requerimiento->estado) {
            'ATENDIDA' => 'success',
            'COTIZANDO', 'EN_REVISION' => 'warning',
            'ANULADA' => 'danger',
            'ENVIADA' => 'info',
            default => 'neutral',
        };
        $prioridadClase = match ($requerimiento->prioridad) {
            'URGENTE' => 'danger',
            'ALTA' => 'warning',
            'BAJA' => 'neutral',
            default => 'info',
        };
        $abastecimientoClase = $requerimiento->claseEstadoAbastecimiento();
    @endphp

    <a href="{{ route('requerimientos-compra.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a requerimientos
    </a>

    <section class="module-header purchase-requirement-show-header">
        <div>
            <p class="eyebrow">Almacén → Logística</p>
            <h1>{{ $requerimiento->codigo }}</h1>
            <p>{{ $requerimiento->descripcion ?: 'Necesidad de abastecimiento registrada por Almacén.' }}</p>
        </div>
        <div class="purchase-requirement-header-actions">
            <span class="badge badge--{{ $prioridadClase }}">{{ $requerimiento->prioridad }}</span>
            <span class="badge badge--{{ $estadoClase }}">Gestión: {{ str($requerimiento->estado)->replace('_', ' ')->title() }}</span>
            <span class="badge badge--{{ $abastecimientoClase }}">{{ $requerimiento->estadoAbastecimientoVisible() }}</span>

            @if ((auth()->user()->puede('compras.gestionar') || auth()->user()->esAdministrador()) && $requerimiento->cotizaciones_count > 0 && in_array($requerimiento->estado, ['EN_REVISION', 'COTIZANDO', 'ATENDIDA'], true))
                <a href="{{ route('requerimientos-compra.comparativo', $requerimiento) }}" class="button button--primary">
                    <x-ui.icon name="banknote" :size="17" /> Comparar ofertas
                </a>
            @endif

            @if ($puedeEditar)
                <a href="{{ route('requerimientos-compra.edit', $requerimiento) }}" class="button button--ghost"><x-ui.icon name="edit" :size="17" /> Editar</a>
                <form method="POST" action="{{ route('requerimientos-compra.enviar', $requerimiento) }}" data-loading-form>
                    @csrf @method('PATCH')
                    <button class="button button--primary" type="submit" data-submit-button data-loading-text="Enviando...">
                        <span data-submit-icon><x-ui.icon name="mail" :size="17" /></span>
                        <span class="button-spinner" data-submit-spinner hidden></span>
                        <span data-submit-label>Enviar a Logística</span>
                    </button>
                </form>
            @endif

        </div>
    </section>

    <section class="summary-strip summary-strip--four" aria-label="Datos del requerimiento">
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="calendar" :size="20" /></span><div><span>Fecha</span><strong>{{ $requerimiento->fecha_solicitud?->format('d/m/Y') }}</strong></div></article>
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="orders" :size="20" /></span><div><span>Origen</span><strong>{{ $requerimiento->ordenOperacion?->codigo_orden ?: 'Reposición' }}</strong></div></article>
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="user" :size="20" /></span><div><span>Solicitado por</span><strong>{{ $requerimiento->solicitante?->nombreVisible() ?? '—' }}</strong></div></article>
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="quotes" :size="20" /></span><div><span>Cotizaciones vinculadas</span><strong>{{ (int) $requerimiento->cotizaciones_count }}</strong></div></article>
    </section>

    @if ($requerimiento->estaAnulada())
        <div class="notice notice--danger notice--block" role="status">
            <x-ui.icon name="error" :size="19" />
            <div>
                <strong>Requerimiento anulado</strong>
                <p>{{ $requerimiento->motivo_anulacion }} · {{ $requerimiento->anulado_en?->format('d/m/Y H:i') }}</p>
            </div>
        </div>
    @endif

    @if ($requerimiento->alertasStock->isNotEmpty())
        <div class="notice notice--info notice--block" role="note">
            <x-ui.icon name="bell" :size="19" />
            <div>
                <strong>{{ $requerimiento->alertasStock->count() }} alerta{{ $requerimiento->alertasStock->count() === 1 ? '' : 's' }} vinculada{{ $requerimiento->alertasStock->count() === 1 ? '' : 's' }}</strong>
                <p>Este requerimiento mantiene la trazabilidad desde las alertas de stock hasta las compras y recepciones registradas.</p>
            </div>
            <a href="{{ route('alertas.index') }}" class="button button--ghost button--small">Ver alertas</a>
        </div>
    @endif

    @if ($requerimiento->abastecimientoCompleto())
        <div class="notice notice--success notice--block" role="status">
            <x-ui.icon name="check-circle" :size="19" />
            <div>
                <strong>Abastecimiento completado con recepciones reales</strong>
                <p>Todos los productos solicitados alcanzaron su cantidad requerida{{ $requerimiento->abastecido_en ? ' el '.$requerimiento->abastecido_en->format('d/m/Y H:i') : '' }}.</p>
            </div>
        </div>
    @endif

    @if ($seguimientoAbastecimiento['total_lineas'] > 0)
        <section class="panel purchase-requirement-supply-panel">
            <div class="panel-heading purchase-requirement-section-heading">
                <div class="purchase-requirement-section-heading__copy">
                    <p class="eyebrow">Abastecimiento</p>
                    <div class="purchase-requirement-section-heading__title-row">
                        <h2>Seguimiento por producto</h2>
                        <x-ui.collapsible-notice
                            class="purchase-requirement-section-heading__help"
                            title="Un estado distinto para cada producto"
                            label="Ver cómo funciona"
                        >
                            <span>El requerimiento puede dividirse entre varios proveedores. Por eso cada línea avanza de forma independiente desde la cotización hasta su recepción en Almacén.</span>
                        </x-ui.collapsible-notice>
                    </div>
                    <p class="purchase-requirement-section-heading__description">Consulta qué falta cotizar, qué ya tiene orden de compra y qué cantidad ingresó realmente.</p>
                </div>
                <div class="purchase-requirement-section-heading__meta">
                    <span class="badge badge--{{ $abastecimientoClase }}">
                        {{ number_format((float) $seguimientoAbastecimiento['avance_porcentaje'], 0) }}% recibido
                    </span>
                </div>
            </div>

            <section class="summary-strip summary-strip--four" aria-label="Resumen del abastecimiento">
                @foreach ([
                    ['Pendiente de cotizar', 'neutral', 'quotes', $seguimientoAbastecimiento['pendientes_cotizar']],
                    ['Con ofertas', 'warning', 'banknote', $seguimientoAbastecimiento['cotizadas']],
                    ['Con OC por recibir', 'info', 'purchase-order', $seguimientoAbastecimiento['ordenadas'] + $seguimientoAbastecimiento['parciales']],
                    ['Recibidos', 'success', 'check-circle', $seguimientoAbastecimiento['recibidas']],
                ] as [$titulo, $tono, $icono, $valor])
                    <article class="summary-strip__item">
                        <span class="summary-strip__icon summary-strip__icon--{{ $tono }}"><x-ui.icon :name="$icono" :size="20" /></span>
                        <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
                    </article>
                @endforeach
            </section>

            <div class="table-wrap table-wrap--wide">
                <table class="data-table purchase-requirement-detail-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Solicitado</th>
                            <th>Ordenado</th>
                            <th>Recibido</th>
                            <th>Pendiente de recibir</th>
                            <th>Estado</th>
                            <th>Documentos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($seguimientoAbastecimiento['lineas'] as $linea)
                            <tr>
                                <td>
                                    <strong>{{ $linea['codigo'] ?? '—' }}</strong>
                                    <span>{{ $linea['descripcion'] ?? 'Producto no disponible' }}</span>
                                </td>
                                <td><strong><x-ui.quantity :value="$linea['cantidad_solicitada']" /></strong> {{ $linea['unidad'] }}</td>
                                <td><x-ui.quantity :value="$linea['cantidad_ordenada']" /></td>
                                <td><strong><x-ui.quantity :value="$linea['cantidad_recibida']" /></strong></td>
                                <td><x-ui.quantity :value="$linea['cantidad_pendiente_recibir']" /></td>
                                <td><span class="badge badge--{{ $linea['estado_clase'] }}">{{ $linea['estado_visible'] }}</span></td>
                                <td>
                                    @if ($linea['ordenes']->isNotEmpty())
                                        <details class="purchase-requirement-supplier-details">
                                            <summary>{{ $linea['ordenes']->count() }} OC vinculada{{ $linea['ordenes']->count() === 1 ? '' : 's' }}</summary>
                                            <div class="purchase-requirement-supplier-mini-list">
                                                @foreach ($linea['ordenes'] as $orden)
                                                    <div>
                                                        <strong><a href="{{ route('ordenes-compra.show', $orden['orden_id']) }}">{{ $orden['orden_codigo'] }}</a></strong>
                                                        <span>{{ $orden['proveedor'] }}</span>
                                                        <small><x-ui.quantity :value="$orden['cantidad_recibida']" /> de <x-ui.quantity :value="$orden['cantidad_ordenada']" /> recibido</small>
                                                        @foreach ($orden['recepciones'] as $recepcion)
                                                            <small><a href="{{ route('notas-ingreso.show', $recepcion->nota_id) }}">{{ $recepcion->nota_codigo }}</a> · <x-ui.quantity :value="$recepcion->cantidad" /></small>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @elseif ($linea['ofertas']->isNotEmpty())
                                        <span>{{ $linea['ofertas']->count() }} oferta{{ $linea['ofertas']->count() === 1 ? '' : 's' }} disponible{{ $linea['ofertas']->count() === 1 ? '' : 's' }}</span>
                                    @else
                                        <span class="text-muted">Sin documentos posteriores</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($requerimiento->estado !== 'BORRADOR')
        <section class="panel purchase-requirement-workflow-panel">
            <div class="panel-heading purchase-requirement-section-heading">
                <div class="purchase-requirement-section-heading__copy">
                    <p class="eyebrow">Seguimiento</p>
                    <div class="purchase-requirement-section-heading__title-row">
                        <h2>Atención de Logística</h2>
                    </div>
                    <p class="purchase-requirement-section-heading__description">Responsable, etapa actual y siguiente acción del requerimiento.</p>
                </div>
                <div class="purchase-requirement-section-heading__meta">
                    <span class="badge badge--{{ $estadoClase }}">{{ str($requerimiento->estado)->replace('_', ' ')->title() }}</span>
                </div>
            </div>

            <div class="purchase-requirement-workflow-grid">
                <article class="purchase-requirement-workflow-card">
                    <span>Responsable de Logística</span>
                    <strong>{{ $requerimiento->receptor?->nombreVisible() ?? ($requerimiento->estado === 'ENVIADA' ? 'Pendiente de asignar' : '—') }}</strong>
                    <small>
                        @if ($requerimiento->recibido_en)
                            Tomado el {{ $requerimiento->recibido_en->format('d/m/Y H:i') }}
                        @else
                            Logística debe tomar el requerimiento para iniciar su atención.
                        @endif
                    </small>
                </article>

                <article class="purchase-requirement-workflow-card">
                    <span>Enviado por Almacén</span>
                    <strong>{{ $requerimiento->enviador?->nombreVisible() ?? '—' }}</strong>
                    <small>{{ $requerimiento->enviado_en?->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}</small>
                </article>

                @if ($requerimiento->estaAtendida())
                    <article class="purchase-requirement-workflow-card">
                        <span>Atendido por</span>
                        <strong>{{ $requerimiento->atendidoPor?->nombreVisible() ?? '—' }}</strong>
                        <small>{{ $requerimiento->atendido_en?->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}</small>
                    </article>
                @endif

                <article class="purchase-requirement-workflow-card">
                    <span>Abastecimiento físico</span>
                    <strong>{{ $requerimiento->estadoAbastecimientoVisible() }}</strong>
                    <small>
                        {{ $requerimiento->abastecido_en
                            ? 'Completado el '.$requerimiento->abastecido_en->format('d/m/Y H:i')
                            : 'Se completa únicamente con notas de ingreso confirmadas.' }}
                    </small>
                </article>
            </div>

            @if ($puedeGestionar && in_array($requerimiento->estado, ['ENVIADA', 'EN_REVISION', 'COTIZANDO'], true))
                @php
                    $accionSeguimiento = match ($requerimiento->estado) {
                        'ENVIADA' => [
                            'ruta' => route('requerimientos-compra.recibir', $requerimiento),
                            'texto' => 'Tomar para revisión',
                            'icono' => 'check',
                            'ayuda' => 'Al tomarlo quedas registrado como responsable inicial de Logística.',
                        ],
                        'EN_REVISION' => [
                            'ruta' => route('requerimientos-compra.cotizando', $requerimiento),
                            'texto' => 'Iniciar cotización',
                            'icono' => 'quotes',
                            'ayuda' => 'Marca que Logística ya inició el contacto y solicitud de precios a proveedores.',
                        ],
                        default => [
                            'ruta' => route('requerimientos-compra.atender', $requerimiento),
                            'texto' => 'Marcar atendido',
                            'icono' => 'check-circle',
                            'ayuda' => 'Cierra la atención del requerimiento. En 17.1.2 las cotizaciones vinculadas respaldarán esta etapa.',
                        ],
                    };
                @endphp

                <form method="POST" action="{{ $accionSeguimiento['ruta'] }}" class="purchase-requirement-followup-form" data-loading-form>
                    @csrf
                    @method('PATCH')
                    <label class="form-field purchase-requirement-followup-form__note">
                        <span>Nota de seguimiento <small>(opcional)</small></span>
                        <textarea name="observacion_seguimiento" rows="2" maxlength="500" placeholder="Ej.: Se contactará primero a los proveedores con disponibilidad inmediata.">{{ old('observacion_seguimiento') }}</textarea>
                    </label>
                    <div class="purchase-requirement-followup-form__action">
                        <small>{{ $accionSeguimiento['ayuda'] }}</small>
                        <button class="button button--primary" type="submit" data-submit-button data-loading-text="Guardando...">
                            <span data-submit-icon><x-ui.icon :name="$accionSeguimiento['icono']" :size="17" /></span>
                            <span class="button-spinner" data-submit-spinner hidden></span>
                            <span data-submit-label>{{ $accionSeguimiento['texto'] }}</span>
                        </button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    @if ($puedeAnular)
        <section class="supplier-quote-danger-zone">
            <div>
                <p class="eyebrow">Control documental</p>
                <h2>Anular requerimiento</h2>
                <p>La anulación conserva el historial y libera las alertas vinculadas. Solo está permitida antes de generar una orden de compra vigente.</p>
            </div>
            <form
                method="POST"
                action="{{ route('requerimientos-compra.anular', $requerimiento) }}"
                class="supplier-quote-cancel-form"
                data-confirm="¿Confirmas anular este requerimiento y liberar sus alertas?"
                data-confirm-title="Anular requerimiento"
                data-confirm-label="Anular requerimiento"
            >
                @csrf
                @method('PATCH')
                <input
                    type="text"
                    name="motivo_anulacion"
                    value="{{ old('motivo_anulacion') }}"
                    minlength="10"
                    maxlength="500"
                    required
                    placeholder="Explica por qué se cancela la necesidad"
                >
                @error('motivo_anulacion')<small class="field-error">{{ $message }}</small>@enderror
                <button class="button button--danger" type="submit">
                    <x-ui.icon name="error" :size="17" /> Anular requerimiento
                </button>
            </form>
        </section>
    @elseif ($tieneOrdenCompraActiva && ! $requerimiento->estaAnulada())
        <x-ui.collapsible-notice title="La anulación está bloqueada" label="Ver motivo">
            <span>Este requerimiento ya generó una orden de compra vigente. Para cancelarlo deben anularse primero sus órdenes relacionadas; una OC con recepción registrada no puede revertirse.</span>
        </x-ui.collapsible-notice>
    @endif

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

    <section class="panel purchase-requirement-detail-panel">
        <div class="panel-heading purchase-requirement-section-heading">
            <div class="purchase-requirement-section-heading__copy">
                <p class="eyebrow">Productos</p>
                <div class="purchase-requirement-section-heading__title-row">
                    <h2>Necesidad enviada</h2>
                    @if ($requerimiento->esBorrador())
                        <x-ui.collapsible-notice
                            class="purchase-requirement-section-heading__help"
                            title="Todavía es un borrador de Almacén"
                            label="Ver qué ocurrirá al enviarlo"
                        >
                            <span>Al enviarlo, Logística podrá verlo en su bandeja junto con los proveedores que históricamente cotizaron estos productos. Enviar no compra ni mueve stock.</span>
                        </x-ui.collapsible-notice>
                    @endif
                </div>
                <p class="purchase-requirement-section-heading__description">Productos y cantidades que Almacén solicita abastecer.</p>
            </div>
            <div class="purchase-requirement-section-heading__meta" aria-label="Cantidad de productos">
                <span class="count-chip">{{ $requerimiento->detalles->count() }}</span>
            </div>
        </div>

        <div class="table-wrap table-wrap--wide">
            <table class="data-table purchase-requirement-detail-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Solicitado</th>
                        <th>Sugerido al registrar</th>
                        <th>Físico</th>
                        <th>Reservado</th>
                        <th>Disponible</th>
                        <th>Mínimo</th>
                        <th>Cotizaciones recibidas</th>
                        <th>Proveedores conocidos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requerimiento->detalles as $detalle)
                        @php $proveedores = $proveedoresPorProducto->get($detalle->producto_id, collect()); @endphp
                        <tr>
                            <td>
                                <strong>{{ $detalle->producto?->codigo ?? '—' }}</strong>
                                <span>{{ $detalle->producto?->descripcion ?? 'Producto no disponible' }}</span>
                                @if ($detalle->observacion)<small>{{ $detalle->observacion }}</small>@endif
                            </td>
                            <td><strong><x-ui.quantity :value="$detalle->cantidad_solicitada" /></strong> {{ $detalle->producto?->unidadMedida?->abreviatura ?? '' }}</td>
                            <td><x-ui.quantity :value="$detalle->cantidad_sugerida ?? 0" /></td>
                            <td><x-ui.quantity :value="$detalle->stock_fisico_snapshot ?? 0" /></td>
                            <td><x-ui.quantity :value="$detalle->reservado_snapshot ?? 0" /></td>
                            <td><x-ui.quantity :value="$detalle->disponible_snapshot ?? 0" /></td>
                            <td><x-ui.quantity :value="$detalle->stock_minimo_snapshot ?? 0" /></td>
                            <td>
                                @php
                                    $ofertas = $detalle->cotizacionDetalles
                                        ->filter(fn ($linea) => $linea->cotizacion && $linea->cotizacion->estado !== 'ANULADA');
                                @endphp
                                @if ($ofertas->isNotEmpty())
                                    <details class="purchase-requirement-supplier-details">
                                        <summary>{{ $ofertas->count() }} oferta{{ $ofertas->count() === 1 ? '' : 's' }}</summary>
                                        <div class="purchase-requirement-supplier-mini-list">
                                            @foreach ($ofertas as $oferta)
                                                <div>
                                                    <strong>{{ $oferta->cotizacion?->proveedor?->nombreVisible() ?? 'Proveedor' }}</strong>
                                                    <span><x-ui.quantity :value="$oferta->cantidad" /> cotizado</span>
                                                    <small>{{ $oferta->cotizacion?->codigo ?? '—' }} · {{ $oferta->cotizacion?->simboloMoneda() }} {{ number_format((float) $oferta->precio_unitario, 2) }}</small>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @else
                                    <span class="text-muted">Aún sin oferta</span>
                                @endif
                            </td>
                            <td>
                                @if ($proveedores->isNotEmpty())
                                    <details class="purchase-requirement-supplier-details">
                                        <summary>{{ $proveedores->count() }} proveedor{{ $proveedores->count() === 1 ? '' : 'es' }}</summary>
                                        <div class="purchase-requirement-supplier-mini-list">
                                            @foreach ($proveedores as $proveedor)
                                                <div>
                                                    <strong>{{ $proveedor->nombre_comercial ?: $proveedor->razon_social }}</strong>
                                                    <span>{{ $proveedor->telefono ?: 'Sin teléfono' }}</span>
                                                    <small>{{ $proveedor->correo ?: 'Sin correo registrado' }}</small>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @else
                                    <span class="text-muted">Sin proveedor histórico</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel purchase-requirement-contacts-panel">
        <div class="panel-heading purchase-requirement-section-heading">
            <div class="purchase-requirement-section-heading__copy">
                <p class="eyebrow">Cobertura automática</p>
                <div class="purchase-requirement-section-heading__title-row">
                    <h2>Distribución sugerida por proveedor</h2>
                </div>
                <p class="purchase-requirement-section-heading__description">Cada producto aparece una sola vez. Se prioriza al proveedor histórico que cubre más productos pendientes y luego la cotización más reciente.</p>
            </div>
            <div class="purchase-requirement-section-heading__meta">
                @if ($coberturaTotal && $gruposSugeridos->count() === 1)
                    <span class="badge badge--success">Un proveedor cubre todo</span>
                @elseif ($coberturaTotal)
                    <span class="badge badge--info">Cobertura en {{ $gruposSugeridos->count() }} listas</span>
                @else
                    <span class="badge badge--warning">Cobertura parcial</span>
                @endif
            </div>
        </div>

        @if ($gruposSugeridos->isNotEmpty() || $productosSinProveedor->isNotEmpty())
            <div class="purchase-requirement-contact-grid">
                @foreach ($gruposSugeridos as $grupo)
                    <article class="purchase-requirement-contact-card">
                        <div class="purchase-requirement-contact-card__top">
                            <div>
                                <strong>{{ $grupo['nombre'] }}</strong>
                                <span>RUC {{ $grupo['ruc'] }}</span>
                            </div>
                            <span class="badge badge--info">Lista {{ $loop->iteration }} · {{ $grupo['productos']->count() }} producto{{ $grupo['productos']->count() === 1 ? '' : 's' }}</span>
                        </div>
                        <dl>
                            <div><dt>Productos asignados</dt><dd>{{ $grupo['productos']->implode(', ') }}</dd></div>
                            <div><dt>Teléfono</dt><dd>{{ $grupo['telefono'] ?: 'No registrado' }}</dd></div>
                            <div><dt>Correo</dt><dd>{{ $grupo['correo'] ?: 'No registrado' }}</dd></div>
                            <div><dt>Última referencia</dt><dd>{{ $grupo['ultima_cotizacion'] ? \Illuminate\Support\Carbon::parse($grupo['ultima_cotizacion'])->format('d/m/Y') : 'Sin fecha' }}</dd></div>
                        </dl>
                        @if ($puedeGestionar)
                            <div class="purchase-requirement-contact-actions">
                                <a href="{{ route('cotizaciones-proveedor.create', [
                                    'requisicion_id' => $requerimiento->id,
                                    'proveedor_id' => $grupo['proveedor_id'],
                                    'detalle_ids' => $grupo['detalle_ids']->all(),
                                ]) }}" class="button button--primary button--small">
                                    <x-ui.icon name="quotes" :size="16" /> Cotizar esta lista
                                </a>
                            </div>
                        @endif
                    </article>
                @endforeach

                @if ($productosSinProveedor->isNotEmpty())
                    <article class="purchase-requirement-contact-card">
                        <div class="purchase-requirement-contact-card__top">
                            <div>
                                <strong>Proveedor por definir</strong>
                                <span>Sin historial de cotizaciones</span>
                            </div>
                            <span class="badge badge--warning">{{ $productosSinProveedor->count() }} pendiente{{ $productosSinProveedor->count() === 1 ? '' : 's' }}</span>
                        </div>
                        <dl>
                            <div><dt>Productos sin cobertura</dt><dd>{{ $productosSinProveedor->implode(', ') }}</dd></div>
                        </dl>
                    </article>
                @endif
            </div>
        @else
            <div class="operation-embedded-empty operation-embedded-empty--wide">
                <span class="operation-embedded-empty__icon"><x-ui.icon name="suppliers" :size="25" /></span>
                <strong>No hay productos para distribuir</strong>
            </div>
        @endif
    </section>

    <section class="panel purchase-requirement-contacts-panel">
        <div class="panel-heading purchase-requirement-section-heading">
            <div class="purchase-requirement-section-heading__copy">
                <p class="eyebrow">Apoyo para Logística</p>
                <div class="purchase-requirement-section-heading__title-row">
                    <h2>Contactos de proveedores sugeridos</h2>
                </div>
                <p class="purchase-requirement-section-heading__description">Se obtienen de cotizaciones de proveedor registradas anteriormente para los productos de este requerimiento.</p>
            </div>
                <div class="purchase-requirement-section-heading__meta" aria-label="Cantidad de proveedores sugeridos">
                    @if ($puedeGestionar && in_array($requerimiento->estado, ['ENVIADA', 'EN_REVISION', 'COTIZANDO'], true))
                        <a href="{{ route('cotizaciones-proveedor.create', ['requisicion_id' => $requerimiento->id]) }}"
                            class="button button--primary button--small">
                            <x-ui.icon name="quotes" :size="16" /> Registrar cotización
                        </a>
                @endif
                <span class="count-chip">
                    {{ $contactos->count() }} proveedor{{ $contactos->count() === 1 ? '' : 'es' }}
                </span>
            </div>
        </div>

        @if ($contactos->isNotEmpty())
            <div class="purchase-requirement-contact-grid">
                @foreach ($contactos as $contacto)
                    <article class="purchase-requirement-contact-card">
                        <div class="purchase-requirement-contact-card__top">
                            <div>
                                <strong>{{ $contacto['nombre'] }}</strong>
                                <span>RUC {{ $contacto['ruc'] }}</span>
                            </div>
                            <span class="badge badge--info">{{ $contacto['productos']->count() }} producto{{ $contacto['productos']->count() === 1 ? '' : 's' }}</span>
                        </div>
                        <dl>
                            <div><dt>Teléfono</dt><dd>{{ $contacto['telefono'] ?: 'No registrado' }}</dd></div>
                            <div><dt>Correo</dt><dd>{{ $contacto['correo'] ?: 'No registrado' }}</dd></div>
                            <div><dt>Contacto</dt><dd>{{ $contacto['contacto'] ?: 'No registrado' }}</dd></div>
                            <div><dt>Productos relacionados</dt><dd>{{ $contacto['productos']->implode(', ') }}</dd></div>
                        </dl>
                        <div class="purchase-requirement-contact-actions">
                            @if ($contacto['telefono'])
                                <a href="tel:{{ preg_replace('/\s+/', '', $contacto['telefono']) }}" class="button button--ghost button--small">
                                    <x-ui.icon name="phone" :size="15" /> Llamar
                                </a>
                            @endif
                            @if ($contacto['correo'])
                                <a href="mailto:{{ $contacto['correo'] }}" class="button button--ghost button--small">
                                    <x-ui.icon name="mail" :size="15" /> Correo
                                </a>
                            @endif
                            @if ($puedeGestionar)
                                <a href="{{ route('cotizaciones-proveedor.create', [
                                    'requisicion_id' => $requerimiento->id,
                                    'proveedor_id' => $contacto['proveedor_id'],
                                    'detalle_ids' => $contacto['detalle_ids']->all(),
                                ]) }}" class="button button--primary button--small">
                                    <x-ui.icon name="quotes" :size="16" /> Usar este proveedor
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="operation-embedded-empty operation-embedded-empty--wide">
                <span class="operation-embedded-empty__icon"><x-ui.icon name="suppliers" :size="25" /></span>
                <strong>No hay proveedores históricos para estos productos</strong>
                <span>Logística puede buscar un proveedor en el catálogo y, cuando registre su primera cotización, quedará relacionado históricamente con el producto.</span>
            </div>
        @endif
    </section>

    @if ($requerimiento->cotizaciones->isNotEmpty())
        <section class="panel purchase-requirement-quotes-panel">
            <div class="panel-heading purchase-requirement-section-heading">
                <div class="purchase-requirement-section-heading__copy">
                    <p class="eyebrow">Compras</p>
                    <div class="purchase-requirement-section-heading__title-row">
                        <h2>Cotizaciones vinculadas</h2>
                    </div>
                </div>
                <div class="purchase-requirement-section-heading__meta" aria-label="Cantidad de cotizaciones vinculadas">
                    <span class="count-chip">{{ $requerimiento->cotizaciones->count() }}</span>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Código</th><th>Proveedor</th><th>Fecha</th><th>Cobertura</th><th>Total</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($requerimiento->cotizaciones as $cotizacion)
                            <tr>
                                <td><strong>{{ $cotizacion->codigo }}</strong></td>
                                <td>{{ $cotizacion->proveedor?->nombreVisible() ?? '—' }}</td>
                                <td>{{ $cotizacion->fecha_cotizacion?->format('d/m/Y') }}</td>
                                <td>
                                    @php
                                        $cubiertas = $cotizacion->detalles
                                            ->filter(fn ($detalle) => $detalle->tipoVinculacionEfectivo() === 'SOLICITADO')
                                            ->count();
                                        $alternativas = $cotizacion->detalles
                                            ->filter(fn ($detalle) => $detalle->tipoVinculacionEfectivo() === 'ALTERNATIVA')
                                            ->count();
                                    @endphp
                                    <strong>{{ $cubiertas }}/{{ $requerimiento->detalles->count() }}</strong> producto{{ $cubiertas === 1 ? '' : 's' }}
                                    @if ($alternativas > 0)
                                        <span>{{ $alternativas }} alternativa{{ $alternativas === 1 ? '' : 's' }} por revisar</span>
                                    @endif
                                </td>
                                <td>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2, '.', ',') }}</td>
                                <td><span class="badge badge--{{ $cotizacion->estadoClase() }}">{{ $cotizacion->estadoVisible() }}</span></td>
                                <td><a href="{{ route('cotizaciones-proveedor.show', $cotizacion) }}" class="icon-button" title="Ver cotización"><x-ui.icon name="eye" :size="16" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
