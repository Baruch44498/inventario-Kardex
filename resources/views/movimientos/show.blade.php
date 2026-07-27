@extends('layouts.app')

@section('title', 'Detalle del movimiento')
@section('page-kicker', 'Movimientos')
@section('page-title', 'Detalle del movimiento')

@section('content')
    @php
        $esEntrada = $movimiento->tipo_movimiento === 'ENTRADA';
        $tipoBadge = $esEntrada ? 'success' : 'danger';
        $origen = str($movimiento->origen_tipo)->replace('_', ' ')->title();
    @endphp

    <a href="{{ route('movimientos.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a movimientos
    </a>

    <section class="module-header">
        <div>
            <p class="eyebrow">Trazabilidad de inventario</p>
            <div class="title-with-status">
                <h1>Movimiento #{{ $movimiento->id }}</h1>
                <span class="badge badge--{{ $tipoBadge }}">
                    {{ $movimiento->tipo_movimiento }}
                </span>
            </div>
            <p>
                Registro inmutable generado por una operación confirmada
                del almacén.
            </p>
        </div>

        <a
            href="{{ route('productos.show', $movimiento->producto_id) }}"
            class="button button--ghost"
        >
            <x-ui.icon name="products" :size="18" />
            Ver producto
        </a>
    </section>

    <section class="detail-grid detail-grid--movement">
        <article class="panel detail-card">
            <header class="detail-card__header">
                <span class="detail-card__icon">
                    <x-ui.icon name="products" :size="20" />
                </span>
                <h2>Producto y ubicación</h2>
            </header>

            <dl class="description-list">
                <div>
                    <dt>Producto</dt>
                    <dd>{{ $movimiento->producto_codigo }}</dd>
                </div>
                <div>
                    <dt>Descripción</dt>
                    <dd>{{ $movimiento->producto_descripcion }}</dd>
                </div>
                <div>
                    <dt>Repisa</dt>
                    <dd>{{ $movimiento->repisa_codigo }}</dd>
                </div>
                <div>
                    <dt>Ubicación</dt>
                    <dd>{{ $movimiento->repisa_descripcion ?: 'Sin descripción' }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel detail-card">
            <header class="detail-card__header">
                <span class="detail-card__icon">
                    <x-ui.icon name="activity" :size="20" />
                </span>
                <h2>Variación de stock</h2>
            </header>

            <div class="movement-hero">
                <span class="movement-hero__type movement-hero__type--{{ $esEntrada ? 'in' : 'out' }}">
                    <x-ui.icon :name="$esEntrada ? 'entry' : 'exit'" :size="22" />
                    {{ $esEntrada ? '+' : '−' }}<x-ui.quantity :value="$movimiento->cantidad" />
                </span>

                <div class="movement-hero__flow">
                    <div>
                        <span>Stock anterior</span>
                        <strong><x-ui.quantity :value="$movimiento->stock_anterior" /></strong>
                    </div>
                    <x-ui.icon name="arrow-right" :size="22" />
                    <div>
                        <span>Stock posterior</span>
                        <strong><x-ui.quantity :value="$movimiento->stock_posterior" /></strong>
                    </div>
                </div>
            </div>
        </article>
    </section>

    <section class="detail-grid detail-grid--movement">
        <article class="panel detail-card">
            <header class="detail-card__header">
                <span class="detail-card__icon">
                    <x-ui.icon name="clipboard" :size="20" />
                </span>
                <h2>Origen y registro</h2>
            </header>

            <dl class="description-list">
                <div>
                    <dt>Motivo</dt>
                    <dd>{{ str($movimiento->motivo)->replace('_', ' ')->title() }}</dd>
                </div>
                <div>
                    <dt>Origen</dt>
                    <dd>{{ $origen }} #{{ $movimiento->origen_id }}</dd>
                </div>
                <div>
                    <dt>Fecha</dt>
                    <dd>
                        {{ \Illuminate\Support\Carbon::parse($movimiento->fecha_movimiento)->format('d/m/Y H:i:s') }}
                    </dd>
                </div>
                <div>
                    <dt>Registrado por</dt>
                    <dd>{{ $movimiento->usuario }}</dd>
                </div>
            </dl>
        </article>

        <article class="panel detail-card">
            <header class="detail-card__header">
                <span class="detail-card__icon">
                    <x-ui.icon name="coins" :size="20" />
                </span>
                <h2>Valoración</h2>
            </header>

            <dl class="description-list">
                <div>
                    <dt>Costo unitario</dt>
                    <dd>
                        {{ $movimiento->costo_unitario !== null
                            ? 'S/ ' . number_format((float) $movimiento->costo_unitario, 4)
                            : 'No aplica' }}
                    </dd>
                </div>
                <div>
                    <dt>Costo promedio anterior</dt>
                    <dd>S/ {{ number_format((float) $movimiento->costo_promedio_anterior, 4) }}</dd>
                </div>
                <div>
                    <dt>Costo promedio nuevo</dt>
                    <dd>S/ {{ number_format((float) $movimiento->costo_promedio_nuevo, 4) }}</dd>
                </div>
                <div>
                    <dt>Inventario relacionado</dt>
                    <dd>#{{ $movimiento->inventario_id }}</dd>
                </div>
            </dl>
        </article>
    </section>

    @if ($movimiento->observacion)
        <section class="panel detail-card">
            <header class="detail-card__header">
                <span class="detail-card__icon">
                    <x-ui.icon name="align-left" :size="20" />
                </span>
                <h2>Observación</h2>
            </header>

            <p class="detail-note">{{ $movimiento->observacion }}</p>
        </section>
    @endif
@endsection
