@extends('layouts.app')

@section('title', 'Notas de ingreso')
@section('page-kicker', 'Almacén')
@section('page-title', 'Notas de ingreso')

@section('content')
<div class="document-list-page">
    <section class="module-header">
        <div>
            <p class="eyebrow">Entradas físicas</p>
            <h1>Notas de ingreso</h1>
            <p>Registra compras, devoluciones de herramientas, retorno de materiales y reposiciones de préstamos con trazabilidad hasta su documento origen.</p>
        </div>
        <a href="{{ route('notas-ingreso.create') }}" class="button button--primary"><x-ui.icon name="plus" :size="18" /> Nueva nota de ingreso</a>
    </section>

    <section class="summary-strip summary-strip--four" aria-label="Resumen de notas de ingreso">
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="entry" :size="21" /></span><div><span>Total</span><strong>{{ (int) ($resumen->total ?? 0) }}</strong></div></article>
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="check-circle" :size="21" /></span><div><span>Confirmadas</span><strong>{{ (int) ($resumen->confirmadas ?? 0) }}</strong></div></article>
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="calendar" :size="21" /></span><div><span>Ingresos hoy</span><strong>{{ (int) ($resumen->hoy ?? 0) }}</strong></div></article>
        <article class="summary-strip__item"><span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="banknote" :size="21" /></span><div><span>Valor de ingresos</span><strong>S/ {{ number_format($valorRecibido, 2, '.', ',') }}</strong></div></article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('notas-ingreso.index') }}" class="filter-grid filter-grid--entries">
            <label class="form-field filter-grid__search"><span>Buscar</span><div class="input-with-icon"><span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span><input type="search" name="q" value="{{ request('q') }}" placeholder="Nota, OC, Nota de Salida o Proforma"></div></label>
            <label class="form-field"><span>Motivo</span><select name="motivo"><option value="">Todos</option><option value="COMPRA" @selected(request('motivo') === 'COMPRA')>Recepción de compra</option><option value="DEVOLUCION_HERRAMIENTA" @selected(request('motivo') === 'DEVOLUCION_HERRAMIENTA')>Devolución de herramienta</option><option value="RETORNO_MATERIAL" @selected(request('motivo') === 'RETORNO_MATERIAL')>Retorno de material</option><option value="REPOSICION_PRESTAMO" @selected(request('motivo') === 'REPOSICION_PRESTAMO')>Reposición de préstamo</option></select></label>
            <label class="form-field"><span>Estado</span><select name="estado"><option value="">Todos</option><option value="CONFIRMADA" @selected(request('estado') === 'CONFIRMADA')>Confirmada</option><option value="BORRADOR" @selected(request('estado') === 'BORRADOR')>Borrador</option><option value="ANULADA" @selected(request('estado') === 'ANULADA')>Anulada</option></select></label>
            <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
            <div class="filter-actions"><button type="submit" class="button button--primary"><x-ui.icon name="filter" :size="17" /> Filtrar</button><a href="{{ route('notas-ingreso.index') }}" class="button button--ghost">Limpiar</a></div>
        </form>
    </section>

    <x-ui.collapsible-notice title="Movimiento físico de stock" label="Ver cómo afecta una Nota de Ingreso al stock">
        <span>Toda entrada o retorno físico incrementa stock mediante Nota de Ingreso y conserva la referencia a la compra, salida o Proforma que lo originó.</span>
    </x-ui.collapsible-notice>

    <section class="panel {{ $notas->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($notas->count() > 0)
            <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--entries data-table--responsive">
                    <thead><tr><th>Nota</th><th>Fecha</th><th>Motivo / origen</th><th>Productos</th><th>Cantidad</th><th>Valor</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($notas as $nota)
                            @php
                                $estadoClase = match ($nota->estado) { 'CONFIRMADA' => 'success', 'ANULADA' => 'danger', default => 'warning' };
                                $origen = match ($nota->motivo_ingreso) {
                                    'COMPRA' => $nota->ordenCompra?->codigo ?? '—',
                                    'DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL' => $nota->notaSalidaOrigen?->codigo ?? '—',
                                    'REPOSICION_PRESTAMO' => $nota->proforma?->codigo ?? '—',
                                    default => '—',
                                };
                            @endphp
                            <tr>
                                <td><a href="{{ route('notas-ingreso.show', $nota->id) }}" class="table-primary-link">{{ $nota->codigo }}</a><span>Por {{ $nota->registrador?->username ?? '—' }}</span></td>
                                <td><strong>{{ $nota->fecha_ingreso?->format('d/m/Y') }}</strong></td>
                                <td><strong>{{ $nota->motivoVisible() }}</strong><span>{{ $origen }}</span></td>
                                <td>{{ (int) $nota->detalles_count }}</td>
                                <td><x-ui.quantity :value="$nota->cantidad_total ?? 0" /></td>
                                <td>S/ {{ number_format((float) ($nota->importe_total ?? 0), 2, '.', ',') }}</td>
                                <td><span class="badge badge--{{ $estadoClase }}">{{ $nota->estado }}</span></td>
                                <td><a href="{{ route('notas-ingreso.show', $nota->id) }}" class="icon-button" title="Ver nota de ingreso" aria-label="Ver nota de ingreso"><x-ui.icon name="eye" :size="17" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$notas" />
        @else
            <div class="empty-table-state"><span class="empty-state__icon"><x-ui.icon name="entry" :size="30" /></span><strong>Aún no hay notas de ingreso</strong><span>Registra la primera entrada o retorno físico de Almacén.</span><div class="empty-table-state__actions"><a href="{{ route('notas-ingreso.create') }}" class="button button--primary button--small"><x-ui.icon name="plus" :size="16" /> Registrar primera nota</a></div></div>
        @endif
    </section>
</div>
@endsection
