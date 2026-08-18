@extends('layouts.app')

@section('title', 'Presupuesto interno '.$cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Presupuesto interno')

@section('content')
    <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a {{ $cotizacion->codigo }}
    </a>

    <section class="supplier-quote-hero commercial-document-hero">
        <div>
            <p class="eyebrow">Uso interno · {{ $cotizacion->codigo }}</p>
            <h1>Presupuesto de ejecución</h1>
            <p>{{ $cotizacion->cliente_nombre }} · referencia cruzada en PEN y USD</p>
        </div>
        <x-ui.status-badge :tone="$cotizacion->tonoEstadoVisual()" class="badge--large">
            {{ $cotizacion->estadoVisual() }}
        </x-ui.status-badge>
    </section>

    <section class="notice notice--warning notice--block">
        <x-ui.icon name="warning" :size="20" />
        <div>
            <strong>Esta información no forma parte de la cotización comercial</strong>
            <span>Los importes son costos internos estimados. En la comparación futura se usarán los valores netos en soles; los totales con IGV quedan visibles para control financiero.</span>
        </div>
    </section>

    <section class="summary-strip summary-strip--four" aria-label="Resumen del presupuesto interno">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="clipboard" :size="21" /></span>
            <div><span>Partidas vigentes</span><strong>{{ $resumen['lineas_vigentes'] }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="quotes" :size="21" /></span>
            <div><span>Neto estimado PEN</span><strong>S/ {{ number_format($resumen['neto_soles'], 2) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="activity" :size="21" /></span>
            <div><span>Neto estimado USD</span><strong>US$ {{ number_format($resumen['neto_dolares'], 2) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning"><x-ui.icon name="warning" :size="21" /></span>
            <div><span>IGV estimado PEN</span><strong>S/ {{ number_format($resumen['igv_soles'], 2) }}</strong></div>
        </article>
    </section>

    @if ($cotizacion->esEditable())
        <section class="panel">
            <header class="panel-heading">
                <p class="eyebrow">Nueva partida</p>
                <h2>Agregar costo estimado</h2>
                <p>El sistema recalcula todos los importes; no guarda totales escritos por el navegador.</p>
            </header>
            @include('cotizaciones_cliente._presupuesto_form', [
                'accion' => route('cotizaciones-cliente.presupuesto.store', $cotizacion),
                'prefijo' => 'nuevo_presupuesto',
            ])
        </section>
    @else
        <section class="notice notice--info notice--block">
            <x-ui.icon name="lock" :size="19" />
            <div><strong>Presupuesto congelado</strong><span>La cotización ya no está abierta. Puedes consultar el presupuesto, pero no modificarlo.</span></div>
        </section>
    @endif

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Resumen comparable</p>
                <h2>Costos netos por tipo</h2>
                <p>Esta tabla será la base de estimado vs real en la fase 19.0.6.</p>
            </div>
        </header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tipo</th><th class="text-right">Partidas</th><th class="text-right">Neto PEN</th><th class="text-right">IGV PEN</th><th class="text-right">Total PEN</th><th class="text-right">Neto USD</th></tr></thead>
                <tbody>
                    @foreach ($resumen['por_tipo'] as $tipo => $grupo)
                        @continue($grupo['lineas'] === 0)
                        <tr>
                            <td><strong>{{ $grupo['nombre'] }}</strong></td>
                            <td class="text-right">{{ $grupo['lineas'] }}</td>
                            <td class="text-right"><x-ui.money :value="$grupo['neto_soles']" currency="PEN" /></td>
                            <td class="text-right"><x-ui.money :value="$grupo['igv_soles']" currency="PEN" /></td>
                            <td class="text-right"><strong><x-ui.money :value="$grupo['total_soles']" currency="PEN" /></strong></td>
                            <td class="text-right"><x-ui.money :value="$grupo['neto_dolares']" currency="USD" /></td>
                        </tr>
                    @endforeach
                    @if ($resumen['lineas_vigentes'] === 0)
                        <tr><td colspan="6">Aún no hay partidas vigentes en el presupuesto interno.</td></tr>
                    @endif
                </tbody>
                @if ($resumen['lineas_vigentes'] > 0)
                    <tfoot><tr><th>Total</th><th></th><th class="text-right"><x-ui.money :value="$resumen['neto_soles']" currency="PEN" /></th><th class="text-right"><x-ui.money :value="$resumen['igv_soles']" currency="PEN" /></th><th class="text-right"><x-ui.money :value="$resumen['total_soles']" currency="PEN" /></th><th class="text-right"><x-ui.money :value="$resumen['neto_dolares']" currency="USD" /></th></tr></tfoot>
                @endif
            </table>
        </div>
    </section>

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div><p class="eyebrow">Detalle y auditoría</p><h2>Partidas presupuestales</h2><p>Las partidas anuladas se conservan y dejan de sumar.</p></div>
        </header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tipo / concepto</th><th>Cálculo original</th><th class="text-right">Neto PEN</th><th class="text-right">IGV PEN</th><th class="text-right">Total PEN</th><th class="text-right">Neto USD</th><th>Estado / acciones</th></tr></thead>
                <tbody>
                    @forelse ($partidas as $item)
                        <tr @class(['is-muted' => ! $item->estaVigente()])>
                            <td>
                                <strong>{{ $item->tipoVisible() }}</strong>
                                <span>{{ $item->descripcion }}</span>
                                @if ($item->producto)<span>{{ $item->producto->codigo }} · vinculado a inventario</span>@endif
                                @if ($item->observacion)<span>{{ $item->observacion }}</span>@endif
                            </td>
                            <td>
                                <strong>{{ number_format((float) $item->cantidad, 3) }} {{ $item->unidadVisible() }} × {{ $item->moneda === 'USD' ? 'US$' : 'S/' }} {{ number_format((float) $item->costo_unitario, 2) }}</strong>
                                <span>{{ \App\Models\CotizacionPresupuesto::MODOS_IGV[$item->igv_modo] ?? $item->igv_modo }} · TC {{ number_format((float) $item->tipo_cambio, 4) }}</span>
                                @if ((float) $item->carga_social_porcentaje > 0)<span>Carga social {{ number_format((float) $item->carga_social_porcentaje, 2) }} %</span>@endif
                            </td>
                            <td class="text-right"><x-ui.money :value="$item->costo_neto_soles" currency="PEN" /></td>
                            <td class="text-right"><x-ui.money :value="$item->igv_soles" currency="PEN" /></td>
                            <td class="text-right"><strong><x-ui.money :value="$item->costo_total_soles" currency="PEN" /></strong></td>
                            <td class="text-right"><x-ui.money :value="$item->costo_neto_dolares" currency="USD" /></td>
                            <td>
                                <x-ui.status-badge :tone="$item->estaVigente() ? 'success' : 'danger'">{{ ucfirst(strtolower($item->estado)) }}</x-ui.status-badge>
                                <span>{{ $item->registradoPor?->nombreVisible() }} · {{ $item->registrado_en?->format('d/m/Y H:i') }}</span>
                                @if ($item->estaVigente() && $cotizacion->esEditable())
                                    <a href="{{ route('cotizacion-presupuestos.edit', $item) }}" class="button button--ghost">Editar</a>
                                    <form method="POST" action="{{ route('cotizacion-presupuestos.anular', $item) }}" data-confirm="¿Anular esta partida y excluirla de los totales?">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="motivo_anulacion" minlength="5" maxlength="500" placeholder="Motivo de anulación" required>
                                        <button type="submit" class="button button--danger">Anular</button>
                                    </form>
                                @elseif (! $item->estaVigente())
                                    <span>{{ $item->motivo_anulacion }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No se han registrado partidas presupuestales.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/presupuesto-cotizacion.js') }}" defer></script>
@endpush
