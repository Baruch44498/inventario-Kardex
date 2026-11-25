@extends('layouts.app')

@section('title', 'Plantillas de costeo')
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Plantillas reutilizables')

@section('content')
    <a href="{{ route('cotizaciones-cliente.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a cotizaciones
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Uso interno</p>
            <h1>Plantillas de hojas de costos</h1>
            <p>Reutiliza modelos completos de fabricación, mantenimiento o servicio sin volver a escribir todas sus partidas.</p>
        </div>
        <a href="{{ route('plantillas-costeo.importaciones.create') }}" class="button button--primary">
            <x-ui.icon name="clipboard" :size="17" /> Importar Excel de costos
        </a>
    </section>

    @if ($importacionesBorrador->isNotEmpty())
        <section class="panel">
            <header class="supplier-panel-heading">
                <div><p class="eyebrow">Trabajo pendiente</p><h2>Importaciones por terminar</h2><p>Puedes continuar la revisión sin volver a subir el archivo.</p></div>
            </header>
            <div class="table-wrap table-wrap--cards">
                <table class="data-table data-table--cards" role="table">
                    <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Nombre</th><th role="columnheader" scope="col">Tipo</th><th role="columnheader" scope="col">Pendientes</th><th role="columnheader" scope="col">Acción</th></tr></thead>
                    <tbody role="rowgroup">
                        @foreach ($importacionesBorrador as $importacion)
                            <tr role="row">
                                <td role="cell" class="table-card__wide" data-label="Nombre"><strong>{{ $importacion->nombre }}</strong><span>{{ $importacion->nombre_original }}</span></td>
                                <td role="cell" data-label="Tipo">{{ $importacion->tipoOrden?->codigo }}</td>
                                <td role="cell" data-label="Pendientes">{{ $importacion->pendientes_count }}</td>
                                <td role="cell" class="table-card__wide" data-label="Acción"><a href="{{ route('plantillas-costeo.importaciones.show', $importacion) }}" class="button button--ghost button--small">Continuar revisión</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="notice notice--info notice--block">
        <x-ui.icon name="clipboard" :size="20" />
        <div>
            <strong>Una plantilla conserva el detalle, no solamente el total</strong>
            <span>Conserva áreas, materiales, personal, servicios y sus campos. Al aplicarla se copian las cantidades y costos; el margen, el tipo de cambio y el IGV de venta se calculan con las reglas de la nueva cotización.</span>
        </div>
    </section>

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Catálogo interno</p>
                <h2>Plantillas activas</h2>
                <p>Las plantillas se crean desde una hoja de costos ya completada y se reutilizan en órdenes OM, OS u OP.</p>
            </div>
        </header>
        <div class="table-wrap table-wrap--cards">
            <table class="data-table data-table--cards" role="table">
                <thead role="rowgroup">
                    <tr role="row"><th role="columnheader" scope="col">Plantilla</th><th role="columnheader" scope="col">Tipo</th><th role="columnheader" scope="col" class="text-right">Partidas</th><th role="columnheader" scope="col">Origen</th><th role="columnheader" scope="col">Creada por</th></tr>
                </thead>
                <tbody role="rowgroup">
                    @forelse ($plantillas as $plantilla)
                        <tr role="row">
                            <td role="cell" class="table-card__wide" data-label="Plantilla"><strong><a href="{{ route('plantillas-costeo.show', $plantilla) }}">{{ $plantilla->nombre }}</a></strong><span>{{ $plantilla->descripcion ?: 'Sin descripción adicional' }}</span><a href="{{ route('plantillas-costeo.show', $plantilla) }}" class="button button--ghost button--small">Ver áreas y partidas</a></td>
                            <td role="cell" data-label="Tipo"><span class="type-chip">{{ $plantilla->tipoOrden?->codigo }}</span> {{ $plantilla->tipoOrden?->nombre }}</td>
                            <td role="cell" class="text-right" data-label="Partidas"><strong>{{ $plantilla->partidas_count }}</strong><span>{{ $plantilla->areas_count }} áreas</span></td>
                            <td role="cell" class="table-card__wide" data-label="Origen">{{ $plantilla->origen === 'EXCEL' ? 'Importada desde Excel' : 'Hoja de costos del sistema' }}</td>
                            <td role="cell" data-label="Creada por">{{ $plantilla->creadoPor?->nombreVisible() }}</td>
                        </tr>
                    @empty
                        <tr role="row"><td role="cell" colspan="5">Todavía no hay plantillas. Completa una hoja de costos y usa “Guardar como plantilla”.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
