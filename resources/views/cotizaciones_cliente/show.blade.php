@extends('layouts.app')

@section('title', $cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Cotización al cliente')

@section('content')
    @php
        $esUltima = $cotizacion->version === (int) $versiones->max('version');
        $puedeGestionar = auth()->user()->puede('proformas.cotizar');
        $puedeAprobar = $puedeGestionar && $cotizacion->puedeConvertirseEnOrden();
        $puedeCerrarParaCobro = $puedeGestionar
            && $cotizacion->proforma_id !== null
            && $cotizacion->estado === 'ABIERTA';
        $puedeAnular = $puedeGestionar
            && ! $cotizacion->estaAnulada()
            && $cotizacion->estado !== 'CONVERTIDA_EN_ORDEN';
        $componentes = $cotizacion->componentes;
        $esMultiComponente = $componentes->count() > 1;
        $codigoTipoOrden = $cotizacion->tipoOrden?->codigo
            ?: $componentes->first()?->tipoOrden?->codigo;
        $esProduccion = $codigoTipoOrden === 'OP';
        $esServicioMantenimiento = in_array($codigoTipoOrden, ['OM', 'OS'], true);
        $valorizaDesdeCosteo = $cotizacion->detalles->contains('origen_costeo', true);
        $estructuraPendiente = ! $cotizacion->proforma_id
            && $cotizacion->detalles->isEmpty();
    @endphp

    <div class="commercial-quote-detail" data-commercial-quote-tabs-root>
    <a href="{{ $cotizacion->proforma
        ? route('proformas.show', $cotizacion->proforma)
        : route('cotizaciones-cliente.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        {{ $cotizacion->proforma
            ? 'Volver a '.$cotizacion->proforma->codigo
            : 'Volver a cotizaciones' }}
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div>
            <p class="eyebrow">{{ $cotizacion->origenVisible() }} · Versión {{ $cotizacion->version }}</p>
            <h1>{{ $cotizacion->codigo }}</h1>
            <p>{{ $cotizacion->cliente_nombre }} · {{ $cotizacion->fecha_emision->format('d/m/Y') }} · {{ $cotizacion->moneda }}</p>
        </div>
        <div class="supplier-quote-hero__actions">
            <x-ui.status-badge :tone="$cotizacion->tonoEstadoVisual()" class="badge--large">
                {{ $cotizacion->estadoVisual() }}
            </x-ui.status-badge>
            @if (auth()->user()->puede('proformas.cotizar') && $cotizacion->esEditable())
                <a href="{{ $estructuraPendiente
                    ? route('cotizaciones-cliente.presupuesto.show', $cotizacion)
                    : ($valorizaDesdeCosteo
                        ? route('cotizaciones-cliente.presupuesto.show', $cotizacion)
                        : route('cotizaciones-cliente.edit', $cotizacion)) }}" class="button button--primary">
                    <x-ui.icon name="edit" :size="17" />
                    {{ $estructuraPendiente
                        ? 'Cargar áreas y costos'
                        : ($valorizaDesdeCosteo ? 'Editar hoja de costos' : 'Continuar cotizando') }}
                </a>
            @endif
        </div>
    </section>

        <nav class="commercial-quote-detail-tabs" role="tablist" aria-label="Secciones de la cotización" data-commercial-quote-tabs>
            <button id="commercial-quote-tab-resumen" type="button" role="tab" aria-selected="true" aria-controls="resumen-comercial" tabindex="0" data-commercial-quote-tab="resumen">
                Resumen comercial
            </button>
            <button id="commercial-quote-tab-areas" type="button" role="tab" aria-selected="false" aria-controls="areas-materiales" tabindex="-1" data-commercial-quote-tab="areas">
                Áreas y materiales
            </button>
            <button id="commercial-quote-tab-presupuesto" type="button" role="tab" aria-selected="false" aria-controls="presupuesto" tabindex="-1" data-commercial-quote-tab="presupuesto">
                Presupuesto
            </button>
            <button id="commercial-quote-tab-versiones" type="button" role="tab" aria-selected="false" aria-controls="versiones-documentos" tabindex="-1" data-commercial-quote-tab="versiones">
                Versiones y documentos
            </button>
        </nav>

        <section id="resumen-comercial" class="commercial-quote-detail-panel" role="tabpanel" aria-labelledby="commercial-quote-tab-resumen" data-commercial-quote-panel="resumen">
            @include('cotizaciones_cliente.partials._show_resumen_comercial')
        </section>

        <section id="areas-materiales" class="commercial-quote-detail-panel" role="tabpanel" aria-labelledby="commercial-quote-tab-areas" data-commercial-quote-panel="areas">
            @include('cotizaciones_cliente.partials._show_areas_materiales')
        </section>

        <section id="presupuesto" class="commercial-quote-detail-panel" role="tabpanel" aria-labelledby="commercial-quote-tab-presupuesto" data-commercial-quote-panel="presupuesto">
            @include('cotizaciones_cliente.partials._show_presupuesto')
        </section>

        <section id="versiones-documentos" class="commercial-quote-detail-panel" role="tabpanel" aria-labelledby="commercial-quote-tab-versiones" data-commercial-quote-panel="versiones">
            @include('cotizaciones_cliente.partials._show_versiones_documentos')
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/commercial-quote-detail-tabs.js') }}" defer></script>
@endpush
