@extends('layouts.app')

@section('title', $requerimiento->codigo)
@section('page-kicker', 'Requerimiento de compra')
@section('page-title', $requerimiento->codigo)

@section('content')
    @php
        $estadoClase = match ($requerimiento->estado) {
            'ATENDIDA' => 'success',
            'COTIZANDO', 'EN_REVISION' => 'warning',
            'ANULADA' => 'danger',
            'ENVIADA' => 'info',
            default => 'neutral',
        };
        $prioridadClase = match ($requerimiento->prioridad) {
            'URGENTE' => 'danger',
            'ALTA' => 'warning',
            'BAJA' => 'neutral',
            default => 'info',
        };
        $abastecimientoClase = $requerimiento->claseEstadoAbastecimiento();
        $pestanaInicial = match (true) {
            $requerimiento->esBorrador(), $requerimiento->estaAnulada() => 'productos',
            in_array($requerimiento->estado, ['ENVIADA', 'EN_REVISION', 'COTIZANDO'], true) => 'gestion',
            default => 'abastecimiento',
        };
    @endphp

    <div
        class="purchase-requirement-page purchase-requirement-page--show"
        data-purchase-requirement-tabs
        data-default-tab="{{ $pestanaInicial }}"
    >
        @include('requerimientos_compra.partials._show_encabezado')
        @include('requerimientos_compra.partials._show_abastecimiento')
        @include('requerimientos_compra.partials._show_gestion')
        @include('requerimientos_compra.partials._show_historial')
        @include('requerimientos_compra.partials._show_productos')
        @include('requerimientos_compra.partials._show_proveedores')
        @include('requerimientos_compra.partials._show_cotizaciones')
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/purchase-requirement-tabs.js') }}" defer></script>
@endpush
