@extends('layouts.app')

@section('title', $nota->codigo)
@section('page-kicker', 'Notas de ingreso')
@section('page-title', $nota->codigo)

@section('content')
    @php
        $estadoClase = match ($nota->estado) {
            'CONFIRMADA' => 'success',
            'ANULADA' => 'danger',
            default => 'warning',
        };

        $origen = match ($nota->motivo_ingreso) {
            'COMPRA' => $nota->ordenCompra?->codigo ?? 'Orden de compra no disponible',
            'DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL' => $nota->notaSalidaOrigen?->codigo ?? 'Nota de salida no disponible',
            'REPOSICION_PRESTAMO' => $nota->proforma?->codigo ?? 'Proforma no disponible',
            default => '—',
        };

        $productosDistintos = $nota->detalles->pluck('producto_id')->filter()->unique()->count();
        $unidadesDetalle = $nota->detalles
            ->map(fn ($detalle) => $detalle->producto?->unidadMedida?->codigo)
            ->filter()
            ->unique()
            ->values();
        $puedeTotalizarCantidad = $unidadesDetalle->count() === 1;
        $unidadResumen = $unidadesDetalle->first();
        $facturasPosteriores = $nota->detalles
            ->flatMap(fn ($detalle) => $detalle->facturaProveedorDetalles)
            ->map(fn ($detalleFactura) => $detalleFactura->facturaProveedor)
            ->filter()
            ->unique('id')
            ->values();
    @endphp

    <div class="document-flow-page document-flow-page--completed">
    <a href="{{ route('notas-ingreso.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a notas de ingreso
    </a>

    <section class="module-header module-header--compact entry-show-header">
        <div>
            <p class="eyebrow">{{ $nota->motivoVisible() }}</p>
            <h1>{{ $nota->codigo }}</h1>
            <p>Entrada física vinculada a <strong>{{ $origen }}</strong>.</p>
        </div>

        <span class="badge badge--{{ $estadoClase }} badge--large">{{ $nota->estado }}</span>
    </section>

    <x-ui.workflow-stepper
        :steps="$pasosRegistro"
        :current="5"
        :interactive="false"
        label="Registro de la Nota de Ingreso completado"
    />

    <section class="entry-document-grid">
        <article class="panel entry-document-card">
            <div class="panel-heading">
                <p class="eyebrow">Documento</p>
                <h2>Datos del ingreso</h2>
            </div>

            <dl class="detail-list detail-list--entry">
                <div><dt>Fecha de ingreso</dt><dd>{{ $nota->fecha_ingreso?->format('d/m/Y') }}</dd></div>
                <div><dt>Motivo</dt><dd>{{ $nota->motivoVisible() }}</dd></div>
                <div>
                    <dt>Documento origen</dt>
                    <dd>
                        @if ($nota->ordenCompra)
                            <a href="{{ route('ordenes-compra.show', $nota->ordenCompra) }}">{{ $origen }}</a>
                        @else
                            {{ $origen }}
                        @endif
                    </dd>
                </div>

                @if ($nota->ordenCompra)
                    <div><dt>Proveedor</dt><dd>{{ $nota->ordenCompra?->proveedor?->razon_social ?? '—' }}</dd></div>
                    <div><dt>RUC</dt><dd>{{ $nota->ordenCompra?->proveedor?->ruc ?? '—' }}</dd></div>
                    <div><dt>Guía de remisión</dt><dd>{{ $nota->numero_guia_remision ?: 'No registrada' }}</dd></div>
                    <div>
                        <dt>Factura vinculada</dt>
                        <dd>
                            @if ($nota->facturaProveedor)
                                <a href="{{ route('facturas-proveedor.show', $nota->facturaProveedor) }}">
                                    {{ $nota->facturaProveedor->tipo_documento }} {{ $nota->facturaProveedor->serie }}-{{ $nota->facturaProveedor->numero }}
                                </a>
                                <small>Costo real del documento aplicado</small>
                            @elseif ($facturasPosteriores->isNotEmpty())
                                @foreach ($facturasPosteriores as $facturaPosterior)
                                    <a href="{{ route('facturas-proveedor.show', $facturaPosterior) }}">
                                        {{ $facturaPosterior->tipo_documento }} {{ $facturaPosterior->serie }}-{{ $facturaPosterior->numero }}
                                    </a>@if (! $loop->last), @endif
                                @endforeach
                                <small>Factura registrada posteriormente; ajuste de costo trazable</small>
                            @else
                                No vinculada · costo provisional de la OC
                            @endif
                        </dd>
                    </div>
                @elseif ($nota->notaSalidaOrigen)
                    <div><dt>Salida original</dt><dd>{{ $nota->notaSalidaOrigen?->codigo }}</dd></div>
                    <div><dt>Destino original</dt><dd>{{ $nota->notaSalidaOrigen?->ordenOperacion?->codigo_orden ?? $nota->notaSalidaOrigen?->proforma?->codigo ?? $nota->notaSalidaOrigen?->motivoVisible() }}</dd></div>
                @elseif ($nota->proforma)
                    <div><dt>Cliente</dt><dd>{{ $nota->proforma?->cliente?->razon_social ?? '—' }}</dd></div>
                @endif

                <div><dt>Registrado por</dt><dd>{{ $nota->registrador?->username ?? '—' }}</dd></div>
                <div><dt>Confirmado</dt><dd>{{ $nota->confirmado_en?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>
        </article>

        <article class="panel entry-total-card">
            <span class="entry-total-card__icon"><x-ui.icon name="entry" :size="28" /></span>
            <span>Productos distintos</span>
            <strong>{{ $productosDistintos }}</strong>
            @if ($puedeTotalizarCantidad)
                <small>Cantidad recibida: <x-ui.quantity :value="$nota->detalles->sum('cantidad')" /> {{ $unidadResumen }}</small>
            @else
                <small>Ver cantidades en el detalle</small>
            @endif
            <div class="entry-total-card__amount">
                <span>Valor de ingreso</span>
                <strong>S/ {{ number_format((float) $nota->detalles->sum('subtotal'), 2, '.', ',') }}</strong>
            </div>
        </article>
    </section>

    @if ($nota->ordenCompra)
        <div class="notice notice--{{ $nota->ordenCompra->estaRecibida() ? 'success' : 'warning' }} notice--block">
            <x-ui.icon :name="$nota->ordenCompra->estaRecibida() ? 'check-circle' : 'inventory'" :size="20" />
            <div>
                <strong>{{ $nota->ordenCompra->estadoVisible() }}</strong>
                <p>{{ $nota->ordenCompra->estaRecibida() ? 'Esta nota completó la recepción de la orden.' : 'La orden conserva cantidades pendientes. Registra otra recepción cuando llegue el saldo.' }}</p>
            </div>
            <a href="{{ route('ordenes-compra.show', $nota->ordenCompra) }}" class="button button--ghost button--small">Ver orden y saldo</a>
        </div>
    @endif

    @if ($nota->observacion)
        <div class="notice notice--info notice--block">
            <x-ui.icon name="info" :size="18" /><span>{{ $nota->observacion }}</span>
        </div>
    @endif

    <section class="panel entry-detail-card">
        <div class="panel-heading panel-heading--split">
            <div><p class="eyebrow">Detalle</p><h2>Productos ingresados</h2></div>
            <div class="panel-heading__actions">
                <a href="{{ route('inventario.index') }}" class="button button--ghost button--small"><x-ui.icon name="inventory" :size="16" /> Ver inventario</a>
                <a href="{{ route('movimientos.index', ['q' => $nota->id]) }}" class="button button--ghost button--small"><x-ui.icon name="movements" :size="16" /> Ver movimientos</a>
            </div>
        </div>

        <div class="table-wrap table-wrap--wide table-wrap--responsive">
            <table class="data-table entry-detail-table">
                <thead>
                    <tr><th>Producto</th><th>Repisa</th><th>Cantidad</th><th>Costo unitario con IGV</th><th>Subtotal con IGV</th><th>Referencia física</th></tr>
                </thead>
                <tbody>
                    @foreach ($nota->detalles as $detalle)
                        <tr>
                            <td><a href="{{ route('productos.show', $detalle->producto_id) }}" class="table-primary-link">{{ $detalle->producto?->codigo }}</a><span>{{ $detalle->producto?->descripcion }}</span></td>
                            <td><span class="location-chip"><x-ui.icon name="shelf" :size="14" /> {{ $detalle->repisa?->codigo }}</span></td>
                            <td><x-ui.quantity :value="$detalle->cantidad" /> {{ $detalle->producto?->unidadMedida?->codigo }}</td>
                            <td>S/ {{ number_format((float) $detalle->costo_unitario, 2, '.', ',') }}</td>
                            <td><strong>S/ {{ number_format((float) $detalle->subtotal, 2, '.', ',') }}</strong></td>
                            <td>
                                @if ($detalle->notaSalidaDetalle)
                                    Retorno de salida #{{ $detalle->notaSalidaDetalle->nota_salida_id }}
                                @elseif ($detalle->proformaDetalle)
                                    Reposición de {{ $detalle->proformaDetalle->codigo_producto }}
                                @else
                                    Recepción de compra
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    </div>
@endsection
