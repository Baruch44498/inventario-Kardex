@extends('layouts.app')

@section('title', $producto->codigo)
@section('page-kicker', 'Productos')
@section('page-title', 'Detalle del producto')

@section('content')
    <a href="{{ route('productos.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a productos
    </a>

    <section class="module-header">
        <div>
            <div class="title-with-status">
                <h1>{{ $producto->codigo }}</h1>
                <span class="badge badge--{{ $producto->activo ? 'success' : 'neutral' }}">
                    {{ $producto->activo ? 'ACTIVO' : 'INACTIVO' }}
                </span>
            </div>
            <p>{{ $producto->descripcion }}</p>
        </div>

        <a
            href="{{ route('productos.edit', $producto->id_producto) }}"
            class="button button--primary"
        >
            <x-ui.icon name="edit" :size="18" />
            Editar producto
        </a>
    </section>

    <section class="detail-grid">
        <article class="panel detail-card">
            <header class="detail-card__header">
                <span class="detail-card__icon">
                    <x-ui.icon name="products" :size="22" />
                </span>
                <div>
                    <p class="eyebrow">Información general</p>
                    <h2>Datos maestros</h2>
                </div>
            </header>

            <dl class="description-list">
                <div>
                    <dt>Código</dt>
                    <dd>{{ $producto->codigo }}</dd>
                </div>
                <div>
                    <dt>Unidad</dt>
                    <dd>
                        {{ $producto->unidad_codigo }}
                        · {{ $producto->unidad_nombre }}
                    </dd>
                </div>
                <div>
                    <dt>Marca principal</dt>
                    <dd>{{ $producto->marca_nombre ?? 'Sin marca asignada' }}</dd>
                </div>
                <div>
                    <dt>Última actualización</dt>
                    <dd>
                        {{ $producto->actualizado_en
                            ? \Carbon\Carbon::parse($producto->actualizado_en)->format('d/m/Y H:i')
                            : '—' }}
                    </dd>
                </div>
            </dl>
        </article>

        <article class="panel detail-card">
            <header class="detail-card__header">
                <span class="detail-card__icon">
                    <x-ui.icon name="inventory" :size="22" />
                </span>
                <div>
                    <p class="eyebrow">Existencias</p>
                    <h2>Resumen</h2>
                </div>
            </header>

            @php
                $stockTotal = $inventarios->sum('stock_actual');
                $valorTotal = $inventarios->sum(
                    fn ($item) =>
                        (float) $item->stock_actual
                        * (float) $item->costo_promedio_soles
                );
            @endphp

            <div class="mini-metric-grid">
                <div class="mini-metric">
                    <span>Stock total</span>
                    <strong><x-ui.quantity :value="$stockTotal" /></strong>
                </div>
                <div class="mini-metric">
                    <span>Repisas</span>
                    <strong>{{ $inventarios->count() }}</strong>
                </div>
                <div class="mini-metric">
                    <span>Valor estimado</span>
                    <strong>S/ {{ number_format($valorTotal, 2, '.', ',') }}</strong>
                </div>
            </div>
        </article>
    </section>

    <section class="panel">
        <header class="panel__header">
            <div>
                <p class="eyebrow">Ubicaciones</p>
                <h2>Inventario por repisa</h2>
            </div>

            <a href="{{ route('inventario.index', ['q' => $producto->codigo]) }}" class="text-link">
                Ver en inventario
            </a>
        </header>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Repisa</th>
                        <th class="text-right">Stock actual</th>
                        <th class="text-right">Mínimo</th>
                        <th class="text-right">Máximo</th>
                        <th class="text-right">Costo promedio</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inventarios as $item)
                        @php
                            $badge = match ($item->estado_stock) {
                                'SIN_STOCK' => 'danger',
                                'BAJO_MINIMO' => 'warning',
                                'SOBRE_MAXIMO' => 'info',
                                default => 'success',
                            };

                            $label = match ($item->estado_stock) {
                                'SIN_STOCK' => 'SIN STOCK',
                                'BAJO_MINIMO' => 'BAJO MÍNIMO',
                                'SOBRE_MAXIMO' => 'SOBRE MÁXIMO',
                                default => 'NORMAL',
                            };
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $item->repisa_codigo }}</strong>
                                <span>{{ $item->repisa_descripcion ?? 'Sin descripción' }}</span>
                            </td>
                            <td class="text-right">
                                <x-ui.quantity :value="$item->stock_actual" />
                            </td>
                            <td class="text-right">
                                <x-ui.quantity :value="$item->stock_minimo" />
                            </td>
                            <td class="text-right">
                                @if ($item->stock_maximo === null)
                                    —
                                @else
                                    <x-ui.quantity :value="$item->stock_maximo" />
                                @endif
                            </td>
                            <td class="text-right">
                                S/ {{ number_format((float) $item->costo_promedio_soles, 2, '.', ',') }}
                            </td>
                            <td>
                                <span class="badge badge--{{ $badge }}">
                                    {{ $label }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-table-state">
                                    <span class="empty-state__icon">
                                        <x-ui.icon name="inventory" :size="30" />
                                    </span>
                                    <strong>Producto sin inventario asignado</strong>
                                    <span>
                                        Aparecerá aquí cuando tenga existencias en una repisa.
                                    </span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
