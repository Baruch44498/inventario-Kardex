@extends('layouts.app')

@section('title', 'Nota de salida ' . $nota->codigo)
@section('page-kicker', 'Notas de salida')
@section('page-title', $nota->codigo)

@section('content')
@php
    $estadoClase = match ($nota->estado) { 'CONFIRMADA' => 'success', 'ANULADA' => 'danger', default => 'warning' };
    $origen = match ($nota->motivo_salida) { 'ORDEN_OPERACION' => $nota->ordenOperacion?->codigo_orden ?? 'Orden no disponible', 'PROFORMA' => $nota->proforma?->codigo ?? 'Proforma no disponible', 'USO_INTERNO' => 'Uso interno', default => 'Salida sin documento origen' };
    $productosDistintos = $nota->detalles->pluck('producto_id')->filter()->unique()->count();
    $unidadesDetalle = $nota->detalles->map(fn ($detalle) => $detalle->producto?->unidadMedida?->codigo)->filter()->unique()->values();
    $puedeTotalizarCantidad = $unidadesDetalle->count() === 1;
    $unidadResumen = $unidadesDetalle->first();
@endphp

<div class="document-flow-page document-flow-page--completed output-detail-workspace" data-output-detail-tabs data-default-tab="salida">
    <a href="{{ route('notas-salida.index') }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a notas de salida</a>
    <section class="module-header module-header--compact entry-show-header"><div><p class="eyebrow">{{ $nota->motivoVisible() }}</p><h1>{{ $nota->codigo }}</h1><p>Salida física vinculada a <strong>{{ $origen }}</strong>.</p></div><div class="module-header__actions"><span class="badge badge--{{ $estadoClase }} badge--large">{{ $nota->estado }}</span></div></section>
    <x-ui.workflow-stepper :steps="$pasosRegistro" :current="5" :interactive="false" label="Registro de la Nota de Salida completado" />

    <div class="output-detail-tabs" role="tablist" aria-label="Detalle de la Nota de Salida">
        <button type="button" id="salida-tab-salida" class="output-detail-tab" role="tab" aria-selected="true" aria-controls="salida-panel-salida" data-output-detail-tab="salida">Salida física <span>{{ $nota->detalles->count() }}</span></button>
        <button type="button" id="salida-tab-planificados" class="output-detail-tab" role="tab" aria-selected="false" aria-controls="salida-panel-planificados" tabindex="-1" data-output-detail-tab="planificados">Planificados <span>{{ $detallesPlanificados->count() }}</span></button>
        <button type="button" id="salida-tab-adicionales" class="output-detail-tab" role="tab" aria-selected="false" aria-controls="salida-panel-adicionales" tabindex="-1" data-output-detail-tab="adicionales">Adicionales <span>{{ $detallesAdicionales->count() }}</span></button>
        <button type="button" id="salida-tab-devoluciones" class="output-detail-tab" role="tab" aria-selected="false" aria-controls="salida-panel-devoluciones" tabindex="-1" data-output-detail-tab="devoluciones">Devoluciones <span>{{ $devolucionesUtilizables->count() }}</span></button>
        <button type="button" id="salida-tab-malogrados" class="output-detail-tab" role="tab" aria-selected="false" aria-controls="salida-panel-malogrados" tabindex="-1" data-output-detail-tab="malogrados">Malogrados <span>{{ $materialesMalogrados->count() }}</span></button>
        <button type="button" id="salida-tab-anulacion" class="output-detail-tab" role="tab" aria-selected="false" aria-controls="salida-panel-anulacion" tabindex="-1" data-output-detail-tab="anulacion">Anulación</button>
    </div>

    <div id="salida-panel-salida" role="tabpanel" aria-labelledby="salida-tab-salida" data-output-detail-panel="salida">@include('notas_salida.partials._show_salida')</div>
    <div id="salida-panel-planificados" role="tabpanel" aria-labelledby="salida-tab-planificados" data-output-detail-panel="planificados" hidden>@include('notas_salida.partials._show_planificados')</div>
    <div id="salida-panel-adicionales" role="tabpanel" aria-labelledby="salida-tab-adicionales" data-output-detail-panel="adicionales" hidden>@include('notas_salida.partials._show_adicionales')</div>
    <div id="salida-panel-devoluciones" role="tabpanel" aria-labelledby="salida-tab-devoluciones" data-output-detail-panel="devoluciones" hidden>@include('notas_salida.partials._show_devoluciones')</div>
    <div id="salida-panel-malogrados" role="tabpanel" aria-labelledby="salida-tab-malogrados" data-output-detail-panel="malogrados" hidden>@include('notas_salida.partials._show_malogrados')</div>
    <div id="salida-panel-anulacion" role="tabpanel" aria-labelledby="salida-tab-anulacion" data-output-detail-panel="anulacion" hidden>@include('notas_salida.partials._show_anulacion')</div>

    @include('notas_salida.partials._show_anulacion_modal')
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/nota-salida-detail.js') }}" defer></script>
@endpush
