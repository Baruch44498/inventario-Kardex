@extends('layouts.app')
@section('title', $plantilla->nombre)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Plantilla por áreas')
@section('content')
    <a href="{{ route('plantillas-costeo.index') }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a plantillas</a>
    <section class="module-header module-header--compact">
        <div><p class="eyebrow">{{ $plantilla->tipoOrden?->codigo }} · {{ $plantilla->origen === 'EXCEL' ? 'Desde Excel' : 'Desde cotización' }}</p><h1>{{ $plantilla->nombre }}</h1><p>{{ $plantilla->descripcion }}</p></div>
    </section>
    <section class="notice notice--info notice--block">
        <div><strong>{{ $plantilla->partidas->count() }} partidas listas para reutilizar</strong><span>Abre cada área para revisar sus campos. Para usarla, selecciónala desde una cotización sin costos. Allí podrás ajustar cantidades y costos; los porcentajes siguen las reglas de esa cotización.</span></div>
    </section>
    <section class="panel quote-area-builder">
        <header class="panel-heading"><h2>Áreas y costos de la plantilla</h2><p>Las áreas vacías también se conservan. Los costos generales permanecen separados.</p></header>
        <div class="quote-area-accordion">
            @foreach ($grupos as $grupo)
                <details class="quote-area-card">
                    <summary>
                        <span class="quote-area-card__number">{{ $loop->iteration }}</span>
                        <span class="quote-area-card__identity"><strong>{{ $grupo['nombre'] }}</strong><small>{{ $grupo['padre'] ? 'Dentro de '.$grupo['padre'] : 'Sección de la plantilla' }}</small></span>
                        <span>{{ $grupo['partidas']->count() }} partidas</span><span aria-hidden="true">⌄</span>
                    </summary>
                    <div class="quote-area-card__body">
                        @forelse ($grupo['partidas'] as $partida)
                            <details class="table-row-details">
                                <summary><strong>{{ $partida->descripcion }}</strong> · <x-ui.quantity :value="$partida->cantidad" /> {{ $unidades[$partida->unidad] ?? $partida->unidad }} · {{ $partida->moneda }} {{ number_format((float) $partida->costo_unitario, 2) }} por unidad</summary>
                                <div class="table-wrap"><table class="data-table"><tbody>
                                    <tr><th>Tipo de costo</th><td>{{ $tipos[$partida->tipo_costo] ?? $partida->tipo_costo }}</td></tr>
                                    <tr><th>Producto / código</th><td>{{ $partida->producto?->codigo ?: $partida->codigo_referencia ?: 'No corresponde' }} · {{ $partida->producto?->descripcion }}</td></tr>
                                    <tr><th>Ejecución del servicio</th><td>{{ $partida->tipo_costo === 'SERVICIO_TERCERO' ? (['EXTERNO' => 'Externo · solo costo', 'INTERNO_HIDROIL' => 'Interno HIDROIL · OS hija'][$partida->ejecucion_servicio] ?? 'Pendiente de clasificar') : 'No corresponde' }}</td></tr>
                                    <tr><th>Moneda y costo unitario</th><td>{{ $partida->moneda }} {{ number_format((float) $partida->costo_unitario, 2) }}</td></tr>
                                    <tr><th>IGV de compra</th><td>{{ $modosIgv[$partida->igv_modo] ?? $partida->igv_modo }} · {{ number_format($partida->igv_modo === 'NO_APLICA' ? 0 : (float) $partida->igv_porcentaje, 2) }}%</td></tr>
                                    <tr><th>Carga social</th><td>{{ number_format((float) $partida->carga_social_porcentaje, 2) }}%</td></tr>
                                    <tr><th>Tipo de cambio de referencia</th><td>{{ number_format((float) $partida->tipo_cambio, 2) }}</td></tr>
                                    <tr><th>Margen de referencia</th><td>{{ number_format((float) $partida->margen_porcentaje, 2) }}%</td></tr>
                                    <tr><th>IGV de venta de referencia</th><td>{{ number_format((float) $partida->igv_venta_porcentaje, 2) }}%</td></tr>
                                    <tr><th>Observación</th><td>{{ $partida->observacion ?: 'Sin observación' }}</td></tr>
                                </tbody></table></div>
                            </details>
                        @empty
                            <p>Área vacía: podrás completarla al aplicar la plantilla.</p>
                        @endforelse
                    </div>
                </details>
            @endforeach
        </div>
    </section>
@endsection
