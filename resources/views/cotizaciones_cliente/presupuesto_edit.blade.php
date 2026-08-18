@extends('layouts.app')

@section('title', 'Editar presupuesto '.$cotizacion->codigo)
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Editar partida presupuestal')

@section('content')
    <a href="{{ route('cotizaciones-cliente.presupuesto.show', $cotizacion) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver al presupuesto de {{ $cotizacion->codigo }}
    </a>

    <section class="panel">
        <header class="panel-heading">
            <p class="eyebrow">Uso interno · Partida #{{ $partida->id }}</p>
            <h1>Editar costo estimado</h1>
            <p>Al guardar, el sistema reemplazará los importes derivados con el nuevo cálculo PEN/USD.</p>
        </header>
        @include('cotizaciones_cliente._presupuesto_form', [
            'accion' => route('cotizacion-presupuestos.update', $partida),
            'prefijo' => 'editar_presupuesto_'.$partida->id,
        ])
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/presupuesto-cotizacion.js') }}" defer></script>
@endpush
