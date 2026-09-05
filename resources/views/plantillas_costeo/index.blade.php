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
            <p class="eyebrow">19.0.6 R2.2 · Uso interno</p>
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
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Nombre</th><th>Tipo</th><th>Pendientes</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($importacionesBorrador as $importacion)
                            <tr>
                                <td><strong>{{ $importacion->nombre }}</strong><span>{{ $importacion->nombre_original }}</span></td>
                                <td>{{ $importacion->tipoOrden?->codigo }}</td>
                                <td>{{ $importacion->pendientes_count }}</td>
                                <td><a href="{{ route('plantillas-costeo.importaciones.show', $importacion) }}" class="button button--ghost button--small">Continuar revisión</a></td>
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
            <span>Guarda grupos, materiales, mano de obra, servicios, cantidades, monedas, IGV y márgenes. Al aplicarla, cada partida se vuelve editable dentro de la nueva cotización.</span>
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
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Plantilla</th><th>Tipo</th><th class="text-right">Partidas</th><th>Origen</th><th>Creada por</th></tr>
                </thead>
                <tbody>
                    @forelse ($plantillas as $plantilla)
                        <tr>
                            <td><strong>{{ $plantilla->nombre }}</strong><span>{{ $plantilla->descripcion ?: 'Sin descripción adicional' }}</span></td>
                            <td><span class="type-chip">{{ $plantilla->tipoOrden?->codigo }}</span> {{ $plantilla->tipoOrden?->nombre }}</td>
                            <td class="text-right"><strong>{{ $plantilla->partidas_count }}</strong></td>
                            <td>{{ $plantilla->origen === 'EXCEL' ? 'Importada desde Excel' : 'Hoja de costos del sistema' }}</td>
                            <td>{{ $plantilla->creadoPor?->nombreVisible() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Todavía no hay plantillas. Completa una hoja de costos y usa “Guardar como plantilla”.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
