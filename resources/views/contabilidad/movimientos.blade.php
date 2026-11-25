@extends('layouts.app')

@section('title', 'Movimientos de tesorería')
@section('page-kicker', 'Contabilidad')
@section('page-title', 'Movimientos de tesorería')

@section('content')
    @php
        $hoy = today();
        $rangos = [
            'Hoy' => [$hoy->toDateString(), $hoy->toDateString()],
            'Esta semana' => [$hoy->copy()->startOfWeek()->toDateString(), $hoy->copy()->endOfWeek()->toDateString()],
            'Este mes' => [$hoy->copy()->startOfMonth()->toDateString(), $hoy->copy()->endOfMonth()->toDateString()],
            'Mes anterior' => [$hoy->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $hoy->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        ];
        $hayFiltros = collect(request()->only(['desde', 'hasta', 'tipo', 'moneda']))->contains(fn ($valor) => filled($valor));
    @endphp
    <div class="treasury-page">
        <section class="module-header">
            <div>
                <p class="eyebrow">Cobros y pagos registrados</p>
                <h1>Movimientos de tesorería</h1>
                <p>Importes vigentes por fecha de operación.</p>
            </div>
            <a href="{{ route('tesoreria.movimientos.csv', request()->only(['desde', 'hasta', 'tipo', 'moneda', 'orden'])) }}" class="button button--ghost" data-file-download>Descargar CSV de movimientos</a>
        </section>

        <section class="panel treasury-panel" aria-label="Filtros de movimientos">
            <form method="GET" action="{{ route('tesoreria.movimientos.index') }}" class="treasury-filters">
                <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
                <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
                <label class="form-field"><span>Tipo</span><select name="tipo"><option value="">Ambos</option><option value="COBRO" @selected(request('tipo') === 'COBRO')>Cobros</option><option value="PAGO" @selected(request('tipo') === 'PAGO')>Pagos</option></select></label>
                <label class="form-field"><span>Moneda</span><select name="moneda"><option value="">Ambas</option><option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option><option value="USD" @selected(request('moneda') === 'USD')>USD</option></select></label>
                <label class="form-field"><span>Ordenar por</span><select name="orden"><option value="fecha_desc" @selected(request('orden', 'fecha_desc') === 'fecha_desc')>Fecha reciente</option><option value="fecha_asc" @selected(request('orden') === 'fecha_asc')>Fecha antigua</option><option value="monto_desc" @selected(request('orden') === 'monto_desc')>Mayor importe</option></select></label>
                <div class="treasury-filters__actions"><button type="submit" class="button button--primary">Filtrar</button><a href="{{ route('tesoreria.movimientos.index') }}" class="button button--ghost">Limpiar</a></div>
            </form>
            @error('desde') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            @error('hasta') <p class="field-error" role="alert">{{ $message }}</p> @enderror
            <nav class="treasury-ranges" aria-label="Rangos de fecha">
                <span>Ir a:</span>
                @foreach ($rangos as $etiqueta => [$inicio, $fin])
                    <a href="{{ route('tesoreria.movimientos.index', array_merge(request()->only(['tipo', 'moneda', 'orden']), ['desde' => $inicio, 'hasta' => $fin])) }}" @if (request('desde') === $inicio && request('hasta') === $fin) aria-current="page" @endif>{{ $etiqueta }}</a>
                @endforeach
            </nav>
        </section>

        <section class="panel treasury-panel treasury-summary" aria-label="Resumen de movimientos filtrados">
            <h2>Resumen de movimientos</h2>
            @foreach (['PEN', 'USD'] as $moneda)
                @if ($resumen->has($moneda) || request('moneda') === $moneda)
                    @php
                        $cobrado = (float) ($resumen->get($moneda)?->firstWhere('tipo', 'COBRO')?->total ?? 0);
                        $pagado = (float) ($resumen->get($moneda)?->firstWhere('tipo', 'PAGO')?->total ?? 0);
                        $neto = $cobrado - $pagado;
                    @endphp
                    <div class="treasury-summary__group">
                        <h3>{{ $moneda }}</h3>
                        <div class="treasury-summary__cards">
                            <div class="metric-card metric-card--summary"><span>Cobros</span><strong><x-ui.money :value="$cobrado" :currency="$moneda" /></strong></div>
                            <div class="metric-card metric-card--summary"><span>Pagos</span><strong><x-ui.money :value="$pagado" :currency="$moneda" /></strong></div>
                            <div class="metric-card metric-card--summary metric-card--highlight treasury-metric treasury-metric--{{ $neto < 0 ? 'negative' : ($neto > 0 ? 'positive' : 'neutral') }}">
                                <span>Neto</span><strong>{{ $neto < 0 ? '−' : ($neto > 0 ? '+' : '') }}<x-ui.money :value="abs($neto)" :currency="$moneda" /></strong>
                                <small>{{ $neto < 0 ? 'Salidas mayores' : ($neto > 0 ? 'Entradas mayores' : 'Cobros y pagos iguales') }}</small>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
            @if ($resumen->isEmpty() && ! in_array(request('moneda'), ['PEN', 'USD'], true))
                <p class="treasury-summary__empty">No hay importes para mostrar con estos filtros.</p>
            @endif
            <p class="treasury-summary__note">El neto es cobros menos pagos; no representa el saldo bancario.</p>
        </section>

        <section class="panel treasury-panel" aria-label="Detalle de movimientos">
            @if ($registros->isNotEmpty())
                <div class="table-wrap table-wrap--responsive treasury-table-wrap" data-responsive-table>
                    <table class="data-table data-table--responsive treasury-table">
                        <thead><tr><th>Fecha</th><th>Tipo</th><th>Documento</th><th>Tercero</th><th>Medio</th><th>Referencia</th><th class="text-right">Importe</th></tr></thead>
                        <tbody>
                            @foreach ($registros as $registro)
                                <tr>
                                    <td data-label="Fecha">{{ \Illuminate\Support\Carbon::parse($registro->fecha)->format('d/m/Y') }}</td>
                                    <td data-label="Tipo"><span class="badge badge--{{ $registro->tipo === 'COBRO' ? 'success' : 'info' }}">{{ $registro->tipo === 'COBRO' ? 'Cobro' : 'Pago' }}</span></td>
                                    <td data-label="Documento">
                                        @if ($registro->tipo === 'COBRO')
                                            <a class="table-primary-link" href="{{ route('cuentas-cobrar.show', $registro->documento_id) }}">{{ $registro->documento }}</a>
                                        @else
                                            <a class="table-primary-link" href="{{ route('facturas-proveedor.show', $registro->documento_id) }}">{{ $registro->serie }}-{{ $registro->documento }}</a>
                                        @endif
                                    </td>
                                    <td data-label="Tercero">{{ $registro->tercero }}</td>
                                    <td data-label="Medio">{{ ucfirst(str_replace('_', ' ', strtolower($registro->medio))) }}</td>
                                    <td data-label="Referencia">{{ $registro->referencia ?: '—' }}</td>
                                    <td class="text-right" data-label="Importe"><span class="treasury-amount treasury-amount--{{ $registro->tipo === 'COBRO' ? 'in' : 'out' }}">{{ $registro->tipo === 'COBRO' ? '+' : '−' }}<x-ui.money :value="$registro->monto" :currency="$registro->moneda" /></span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-ui.pagination :paginator="$registros" />
            @else
                <div class="empty-table-state treasury-empty">
                    <strong>No hay movimientos vigentes {{ $hayFiltros ? 'con estos filtros' : 'todavía' }}</strong>
                    <span>{{ $hayFiltros ? 'Prueba con otro rango o quita los filtros. Esta vista muestra cobros y pagos registrados, no facturas pendientes.' : 'Los cobros y pagos registrados aparecerán aquí. Las facturas pendientes se consultan en Cuentas por pagar.' }}</span>
                    @if ($hayFiltros)<a href="{{ route('tesoreria.movimientos.index') }}" class="button button--ghost">Limpiar filtros</a>@endif
                </div>
            @endif
        </section>
    </div>
@endsection
