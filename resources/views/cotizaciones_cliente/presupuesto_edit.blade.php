@extends('layouts.app')

@section('title', 'Editar presupuesto '.$cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Editar partida presupuestal')

@section('content')
<div class="budget-edit-page">
    <a href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'paso' => 'revision', 'grupo_partidas' => request('grupo_partidas'), 'partidas_page' => request('partidas_page')]) }}#detalle-area-presupuesto" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver al presupuesto de {{ $cotizacion->codigo }}
    </a>

    <section class="panel">
        <header class="panel-heading">
            <p class="eyebrow">Uso interno · Partida #{{ $partida->id }}</p>
            <h1>Editar costo estimado</h1>
            <p>Al guardar, el sistema reemplazará los importes derivados con el nuevo cálculo PEN/USD.</p>
        </header>
        @include('cotizaciones_cliente._presupuesto_form', [
            'accion' => route('cotizacion-presupuestos.update', ['presupuesto' => $partida, 'grupo_partidas' => request('grupo_partidas'), 'partidas_page' => request('partidas_page')]),
            'prefijo' => 'editar_presupuesto_'.$partida->id,
        ])
    </section>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/presupuesto-cotizacion.js') }}" defer></script>
@endpush
