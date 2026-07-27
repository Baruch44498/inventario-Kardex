@extends('layouts.app')

@section('title', $nota->codigo)
@section('page-kicker', 'Notas de ingreso')
@section('page-title', $nota->codigo)

@section('content')
    <a href="{{ route('notas-ingreso.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a notas de ingreso
    </a>

    <section class="module-header module-header--compact entry-show-header">
        <div>
            <p class="eyebrow">Recepción confirmada</p>
            <h1>{{ $nota->codigo }}</h1>
            <p>
                Ingreso asociado a la orden {{ $nota->ordenCompra?->codigo }}
                del proveedor {{ $nota->ordenCompra?->proveedor?->razon_social }}.
            </p>
        </div>

        <span class="badge badge--{{ $nota->estado === 'CONFIRMADA' ? 'success' : ($nota->estado === 'ANULADA' ? 'danger' : 'warning') }} badge--large">
            {{ $nota->estado }}
        </span>
    </section>

    <section class="entry-document-grid">
        <article class="panel entry-document-card">
            <div class="panel-heading">
                <p class="eyebrow">Documento</p>
                <h2>Datos de la recepción</h2>
            </div>

            <dl class="detail-list detail-list--entry">
                <div><dt>Fecha de ingreso</dt><dd>{{ $nota->fecha_ingreso?->format('d/m/Y') }}</dd></div>
                <div><dt>Orden de compra</dt><dd>{{ $nota->ordenCompra?->codigo ?? '—' }}</dd></div>
                <div><dt>Proveedor</dt><dd>{{ $nota->ordenCompra?->proveedor?->razon_social ?? '—' }}</dd></div>
                <div><dt>RUC</dt><dd>{{ $nota->ordenCompra?->proveedor?->ruc ?? '—' }}</dd></div>
                <div><dt>Guía de remisión</dt><dd>{{ $nota->numero_guia_remision ?: 'No registrada' }}</dd></div>
                <div>
                    <dt>Factura vinculada</dt>
                    <dd>
                        @if ($nota->facturaProveedor)
                            {{ $nota->facturaProveedor->tipo_documento }}
                            {{ $nota->facturaProveedor->serie }}-{{ $nota->facturaProveedor->numero }}
                        @else
                            No vinculada
                        @endif
                    </dd>
                </div>
                <div><dt>Registrado por</dt><dd>{{ $nota->registrador?->username ?? '—' }}</dd></div>
                <div><dt>Confirmado</dt><dd>{{ $nota->confirmado_en?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>
        </article>

        <article class="panel entry-total-card">
            <span class="entry-total-card__icon">
                <x-ui.icon name="entry" :size="28" />
            </span>
            <span>Productos recibidos</span>
            <strong>{{ $nota->detalles->count() }}</strong>
            <small>
                Cantidad total:
                <x-ui.quantity :value="$nota->detalles->sum('cantidad')" />
            </small>

            <div class="entry-total-card__amount">
                <span>Valor recibido</span>
                <strong>S/ {{ number_format((float) $nota->detalles->sum('subtotal'), 2, '.', ',') }}</strong>
            </div>
        </article>
    </section>

    @if ($nota->observacion)
        <div class="notice notice--info notice--block">
            <x-ui.icon name="info" :size="18" />
            <span>{{ $nota->observacion }}</span>
        </div>
    @endif

    <section class="panel">
        <div class="panel-heading panel-heading--split">
            <div>
                <p class="eyebrow">Detalle</p>
                <h2>Productos ingresados</h2>
            </div>

            <div class="panel-heading__actions">
                <a href="{{ route('inventario.index') }}" class="button button--ghost button--small">
                    <x-ui.icon name="inventory" :size="16" />
                    Ver inventario
                </a>
                <a href="{{ route('movimientos.index', ['q' => $nota->id]) }}" class="button button--ghost button--small">
                    <x-ui.icon name="movements" :size="16" />
                    Ver movimientos
                </a>
            </div>
        </div>

        <div class="table-wrap table-wrap--wide">
            <table class="data-table entry-detail-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Repisa</th>
                        <th>Cantidad</th>
                        <th>Costo unitario</th>
                        <th>Subtotal</th>
                        <th>Lote</th>
                        <th>Vencimiento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($nota->detalles as $detalle)
                        <tr>
                            <td>
                                <a href="{{ route('productos.show', $detalle->producto_id) }}" class="table-primary-link">
                                    {{ $detalle->producto?->codigo }}
                                </a>
                                <span>{{ $detalle->producto?->descripcion }}</span>
                            </td>
                            <td>
                                <span class="location-chip">
                                    <x-ui.icon name="shelf" :size="14" />
                                    {{ $detalle->repisa?->codigo }}
                                </span>
                            </td>
                            <td><x-ui.quantity :value="$detalle->cantidad" /></td>
                            <td>S/ {{ number_format((float) $detalle->costo_unitario, 4, '.', ',') }}</td>
                            <td><strong>S/ {{ number_format((float) $detalle->subtotal, 2, '.', ',') }}</strong></td>
                            <td>{{ $detalle->lote ?: '—' }}</td>
                            <td>{{ $detalle->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
