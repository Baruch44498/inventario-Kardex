@extends('layouts.app')

@section('title', 'Notas de salida')
@section('page-kicker', 'Almacén')
@section('page-title', 'Notas de salida')

@section('content')
<div class="document-list-page">
    <section class="module-header">
        <div>
            <p class="eyebrow">Movimientos físicos</p>
            <h1>Notas de salida</h1>
            <p>Registra cualquier producto que deja físicamente Almacén: órdenes, Proformas, herramientas de uso temporal u otras salidas internas.</p>
        </div>
        <a href="{{ route('notas-salida.create') }}" class="button button--primary"><x-ui.icon name="plus" :size="18" /> Nueva nota de salida</a>
    </section>

    <section class="summary-strip summary-strip--four" aria-label="Resumen de notas de salida">
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="exit" :size="21" /></span><div><span>Total</span><strong>{{ (int) ($resumen->total ?? 0) }}</strong></div></article>
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="check-circle" :size="21" /></span><div><span>Confirmadas</span><strong>{{ (int) ($resumen->confirmadas ?? 0) }}</strong></div></article>
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--danger"><x-ui.icon name="error" :size="21" /></span><div><span>Anuladas</span><strong>{{ (int) ($resumen->anuladas ?? 0) }}</strong></div></article>
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="banknote" :size="21" /></span><div><span>Valor de salidas</span><strong>S/ {{ number_format($valorEntregado, 2, '.', ',') }}</strong></div></article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('notas-salida.index') }}" class="filter-grid filter-grid--entries">
            <label class="form-field filter-grid__search"><span>Buscar</span><div class="input-with-icon"><span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span><input type="search" name="q" value="{{ request('q') }}" placeholder="Nota, orden, Proforma, cliente o receptor"></div></label>
            <label class="form-field"><span>Motivo</span><select name="motivo"><option value="">Todos</option><option value="ORDEN_OPERACION" @selected(request('motivo') === 'ORDEN_OPERACION')>Orden de operación</option><option value="PROFORMA" @selected(request('motivo') === 'PROFORMA')>Proforma</option><option value="USO_INTERNO" @selected(request('motivo') === 'USO_INTERNO')>Uso interno</option><option value="OTRO" @selected(request('motivo') === 'OTRO')>Otro</option></select></label>
            <label class="form-field"><span>Estado</span><select name="estado"><option value="">Todos</option><option value="CONFIRMADA" @selected(request('estado') === 'CONFIRMADA')>Confirmada</option><option value="BORRADOR" @selected(request('estado') === 'BORRADOR')>Borrador</option><option value="ANULADA" @selected(request('estado') === 'ANULADA')>Anulada</option></select></label>
            <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
            <div class="filter-actions"><button type="submit" class="button button--primary"><x-ui.icon name="filter" :size="17" /> Filtrar</button><a href="{{ route('notas-salida.index') }}" class="button button--ghost">Limpiar</a></div>
        </form>
    </section>

    <x-ui.collapsible-notice title="Movimiento físico de stock" label="Ver cómo afecta una Nota de Salida al stock">
        <span>Confirmar una Nota de Salida disminuye el stock físico. Una reserva futura no lo hará hasta que el producto sea realmente retirado.</span>
    </x-ui.collapsible-notice>

    <section class="panel {{ $notas->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($notas->count() > 0)
            <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--responsive output-list-table">
                    <thead><tr><th>Nota</th><th>Fecha</th><th>Motivo / origen</th><th>Entregado a</th><th>Productos</th><th>Cantidad</th><th>Valor</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($notas as $nota)
                            @php
                                $estadoClase = match ($nota->estado) { 'CONFIRMADA' => 'success', 'ANULADA' => 'danger', default => 'warning' };
                                $origen = match ($nota->motivo_salida) {
                                    'ORDEN_OPERACION' => $nota->ordenOperacion?->codigo_orden ?? '—',
                                    'PROFORMA' => $nota->proforma?->codigo ?? '—',
                                    'USO_INTERNO' => 'Uso interno',
                                    default => 'Otro',
                                };
                            @endphp
                            <tr>
                                <td><a href="{{ route('notas-salida.show', $nota->id) }}" class="table-primary-link">{{ $nota->codigo }}</a><span>Por {{ $nota->registrador?->username ?? '—' }}</span></td>
                                <td><strong>{{ $nota->fecha_salida?->format('d/m/Y') }}</strong></td>
                                <td><strong>{{ $nota->motivoVisible() }}</strong><span>{{ $origen }}</span></td>
                                <td>{{ $nota->entregado_a ?: 'No registrado' }}</td>
                                <td>{{ (int) $nota->detalles_count }}</td>
                                <td><x-ui.quantity :value="$nota->cantidad_total ?? 0" /></td>
                                <td>S/ {{ number_format((float) ($nota->importe_total ?? 0), 2, '.', ',') }}</td>
                                <td><span class="badge badge--{{ $estadoClase }}">{{ $nota->estado }}</span></td>
                                <td><a href="{{ route('notas-salida.show', $nota->id) }}" class="icon-button" title="Ver nota de salida" aria-label="Ver nota de salida"><x-ui.icon name="eye" :size="17" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$notas" />
        @else
            <div class="empty-table-state"><span class="empty-state__icon"><x-ui.icon name="exit" :size="30" /></span><strong>Aún no hay notas de salida</strong><span>Registra la primera salida física de Almacén.</span><div class="empty-table-state__actions"><a href="{{ route('notas-salida.create') }}" class="button button--primary button--small"><x-ui.icon name="plus" :size="16" /> Registrar primera salida</a></div></div>
        @endif
    </section>
</div>
@endsection
