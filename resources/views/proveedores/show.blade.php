@extends('layouts.app')

@section('title', $proveedor->nombreVisible())
@section('page-kicker', 'Proveedores')
@section('page-title', 'Detalle del proveedor')

@section('content')
    <a href="{{ route('proveedores.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a proveedores
    </a>

    <section class="supplier-detail-hero">
        <div>
            <p class="eyebrow">Proveedor registrado</p>
            <h1>{{ $proveedor->nombreVisible() }}</h1>
            <p>
                RUC {{ $proveedor->ruc }}
                @if ($proveedor->nombre_comercial)
                    · {{ $proveedor->razon_social }}
                @endif
            </p>
        </div>

        <div class="supplier-detail-hero__actions">
            <span class="badge badge--{{ $proveedor->estado ? 'success' : 'danger' }}">
                {{ $proveedor->estado ? 'ACTIVO' : 'INACTIVO' }}
            </span>
            <a href="{{ route('cotizaciones-proveedor.create', ['proveedor_id' => $proveedor->id]) }}"
                class="button button--primary">
                <x-ui.icon name="quotes" :size="17" /> Registrar cotización
            </a>
            <a href="{{ route('proveedores.edit', $proveedor->id) }}" class="button button--ghost">
                <x-ui.icon name="edit" :size="17" /> Editar
            </a>
        </div>
    </section>

    <section class="summary-strip summary-strip--four">
        @foreach ([
            ['Cotizaciones', 'quotes', 'info', $proveedor->cotizaciones_vigentes_count],
            ['Productos cotizados', 'products', 'warning', $resumen['productos']],
            ['Órdenes de compra', 'purchase-order', 'success', $proveedor->ordenes_compra_count],
            ['Facturas', 'invoice', 'neutral', $proveedor->facturas_proveedor_count],
        ] as [$titulo, $icono, $tono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}">
                    <x-ui.icon :name="$icono" :size="21" />
                </span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="supplier-detail-grid">
        <article class="panel supplier-info-panel">
            <header class="supplier-panel-heading">
                <div>
                    <p class="eyebrow">Información principal</p>
                    <h2>Datos comerciales</h2>
                </div>
            </header>

            <dl class="supplier-info-grid">
                <div><dt>Contacto</dt><dd>{{ $proveedor->contacto ?: 'No registrado' }}</dd></div>
                <div><dt>Teléfono</dt><dd>{{ $proveedor->telefono ?: 'No registrado' }}</dd></div>
                <div><dt>Correo</dt><dd>{{ $proveedor->correo ?: 'No registrado' }}</dd></div>
                <div><dt>Ubicación</dt><dd>{{ $proveedor->ubicacionVisible() ?: 'No registrada' }}</dd></div>
                <div class="supplier-info-grid__wide">
                    <dt>Dirección</dt><dd>{{ $proveedor->direccion ?: 'No registrada' }}</dd>
                </div>
                <div>
                    <dt>Última cotización</dt>
                    <dd>
                        {{ $resumen['ultima_cotizacion']
                            ? \Illuminate\Support\Carbon::parse($resumen['ultima_cotizacion'])->format('d/m/Y')
                            : 'Sin cotizaciones' }}
                    </dd>
                </div>
            </dl>
        </article>

        <article class="panel supplier-history-help">
            <span><x-ui.icon name="banknote" :size="25" /></span>
            <div>
                <p class="eyebrow">Historial acumulado</p>
                <h2>Comparación de precios</h2>
                <p>Las cotizaciones alimentan el historial por producto, moneda y fecha.</p>
                <a href="{{ route('historial-precios.index', ['proveedor_id' => $proveedor->id]) }}"
                    class="button button--ghost button--small">Consultar historial</a>
            </div>
        </article>
    </section>

    <section class="panel supplier-related-panel">
        <header class="supplier-panel-heading supplier-panel-heading--split">
            <div>
                <p class="eyebrow">Documentos registrados</p>
                <h2>Cotizaciones del proveedor</h2>
            </div>
            <a href="{{ route('cotizaciones-proveedor.create', ['proveedor_id' => $proveedor->id]) }}"
                class="button button--primary button--small">
                <x-ui.icon name="plus" :size="16" /> Nueva cotización
            </a>
        </header>

        @if ($cotizaciones->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table data-table--actions">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Documento proveedor</th>
                            <th>Fecha</th>
                            <th>Moneda</th>
                            <th>Productos</th>
                            <th class="text-right">Total</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cotizaciones as $cotizacion)
                            <tr>
                                <td>
                                    <a href="{{ route('cotizaciones-proveedor.show', $cotizacion->id) }}"
                                        class="table-primary-link">{{ $cotizacion->codigo }}</a>
                                </td>
                                <td>{{ $cotizacion->numero_documento ?: 'Sin número' }}</td>
                                <td>{{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                                <td>{{ $cotizacion->moneda }}</td>
                                <td>{{ $cotizacion->detalles_count }}</td>
                                <td class="text-right">
                                    {{ $cotizacion->simboloMoneda() }}
                                    {{ number_format((float) $cotizacion->total, 2) }}
                                </td>
                                <td>
                                    <span class="badge badge--{{ $cotizacion->estadoClase() }}">
                                        {{ $cotizacion->estadoVisible() }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('cotizaciones-proveedor.show', $cotizacion->id) }}"
                                        class="button button--ghost button--small">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$cotizaciones" />
        @else
            <div class="empty-table-state supplier-related-empty">
                <span class="empty-state__icon"><x-ui.icon name="quotes" :size="29" /></span>
                <strong>Sin cotizaciones registradas</strong>
                <span>Registra la primera oferta recibida de este proveedor.</span>
            </div>
        @endif
    </section>

    <section class="panel supplier-related-panel">
        <header class="supplier-panel-heading supplier-panel-heading--split">
            <div>
                <p class="eyebrow">Trazabilidad por producto</p>
                <h2>Precios ofrecidos</h2>
            </div>
            <a href="{{ route('historial-precios.index', ['proveedor_id' => $proveedor->id]) }}"
                class="button button--ghost button--small">Ver comparación</a>
        </header>

        @if ($precios->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Marca</th>
                            <th>Moneda</th>
                            <th class="text-right">Precio informado</th>
                            <th>Descuento</th>
                            <th>IGV</th>
                            <th class="text-right">Precio final</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($precios as $precio)
                            <tr>
                                <td>{{ $precio->cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                                <td>
                                    <strong>{{ $precio->producto?->codigo }}</strong>
                                    <span>{{ $precio->producto?->descripcion }}</span>
                                </td>
                                <td>{{ $precio->marca_ofertada ?: 'No especificada' }}</td>
                                <td>{{ $precio->cotizacion->moneda }}</td>
                                <td class="text-right">
                                    {{ $precio->cotizacion->simboloMoneda() }}
                                    {{ number_format((float) $precio->precio_unitario, 2) }}
                                </td>
                                <td>{{ $precio->descuentoVisible() }}</td>
                                <td>{{ $precio->igvVisible() }}</td>
                                <td class="text-right">
                                    <strong>
                                        {{ $precio->cotizacion->simboloMoneda() }}
                                        {{ number_format($precio->precioFinalUnitario(), 2) }}
                                    </strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$precios" />
        @else
            <div class="empty-table-state supplier-related-empty">
                <span class="empty-state__icon"><x-ui.icon name="banknote" :size="29" /></span>
                <strong>Sin precios históricos</strong>
                <span>Los precios aparecerán al registrar una cotización.</span>
            </div>
        @endif
    </section>
@endsection
