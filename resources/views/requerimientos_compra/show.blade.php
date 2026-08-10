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
            <span class="badge badge--{{ $estadoClase }}">{{ str($requerimiento->estado)->replace('_', ' ')->title() }}</span>

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

            @if ($puedeGestionar && $requerimiento->estado === 'ENVIADA')
                <form method="POST" action="{{ route('requerimientos-compra.recibir', $requerimiento) }}">
                    @csrf @method('PATCH')
                    <button class="button button--primary" type="submit"><x-ui.icon name="check" :size="17" /> Tomar para revisión</button>
                </form>
            @endif

            @if ($puedeGestionar && $requerimiento->estado === 'EN_REVISION')
                <form method="POST" action="{{ route('requerimientos-compra.cotizando', $requerimiento) }}">
                    @csrf @method('PATCH')
                    <button class="button button--primary" type="submit"><x-ui.icon name="quotes" :size="17" /> Iniciar cotización</button>
                </form>
            @endif

            @if ($puedeGestionar && in_array($requerimiento->estado, ['EN_REVISION', 'COTIZANDO'], true))
                <form method="POST" action="{{ route('requerimientos-compra.atender', $requerimiento) }}">
                    @csrf @method('PATCH')
                    <button class="button button--ghost" type="submit"><x-ui.icon name="check-circle" :size="17" /> Marcar atendido</button>
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

    @if ($requerimiento->esBorrador())
        <x-ui.collapsible-notice title="Todavía es un borrador de Almacén" label="Ver qué ocurrirá al enviarlo">
            <span>Al enviarlo, Logística podrá verlo en su bandeja junto con los proveedores que históricamente cotizaron estos productos. Enviar no compra ni mueve stock.</span>
        </x-ui.collapsible-notice>
    @endif

    <section class="panel purchase-requirement-detail-panel">
        <div class="panel-heading panel-heading--split">
            <div><p class="eyebrow">Productos</p><h2>Necesidad enviada</h2></div>
            <span class="count-chip">{{ $requerimiento->detalles->count() }}</span>
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
        <div class="panel-heading panel-heading--split">
            <div>
                <p class="eyebrow">Apoyo para Logística</p>
                <h2>Contactos de proveedores sugeridos</h2>
                <p>Se obtienen de cotizaciones de proveedor registradas anteriormente para los productos de este requerimiento.</p>
            </div>
            <span class="count-chip">{{ $contactos->count() }}</span>
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
                                <a href="{{ route('cotizaciones-proveedor.create', ['requisicion_id' => $requerimiento->id, 'proveedor_id' => $contacto['proveedor_id']]) }}" class="button button--primary button--small">
                                    <x-ui.icon name="quotes" :size="16" /> Registrar cotización
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
        <section class="panel">
            <div class="panel-heading panel-heading--split">
                <div><p class="eyebrow">Compras</p><h2>Cotizaciones vinculadas</h2></div>
                <span class="count-chip">{{ $requerimiento->cotizaciones->count() }}</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Código</th><th>Proveedor</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($requerimiento->cotizaciones as $cotizacion)
                            <tr>
                                <td><strong>{{ $cotizacion->codigo }}</strong></td>
                                <td>{{ $cotizacion->proveedor?->nombreVisible() ?? '—' }}</td>
                                <td>{{ $cotizacion->fecha_cotizacion?->format('d/m/Y') }}</td>
                                <td>{{ $cotizacion->simboloMoneda() }} {{ number_format((float) $cotizacion->total, 2, '.', ',') }}</td>
                                <td><span class="badge badge--{{ $cotizacion->estado === 'ANULADA' ? 'danger' : 'info' }}">{{ $cotizacion->estado }}</span></td>
                                <td><a href="{{ route('cotizaciones-proveedor.show', $cotizacion) }}" class="icon-button" title="Ver cotización"><x-ui.icon name="eye" :size="16" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
