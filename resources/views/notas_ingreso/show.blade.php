@extends('layouts.app')

@section('title', $nota->codigo)
@section('page-kicker', 'Notas de ingreso')
@section('page-title', $nota->codigo)

@section('content')
    @php
        $estadoClase = match ($nota->estado) {
            'CONFIRMADA' => 'success',
            'ANULADA' => 'danger',
            default => 'warning',
        };
        $origen = match ($nota->motivo_ingreso) {
            'COMPRA' => $nota->ordenCompra?->codigo ?? 'Orden de compra no disponible',
            'DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL', 'DEVOLUCION_MATERIAL_MALOGRADO' => $nota->notaSalidaOrigen?->codigo ?? 'Nota de salida no disponible',
            'REPOSICION_PRESTAMO' => $nota->proforma?->codigo ?? 'Proforma no disponible',
            default => '—',
        };
        $productosDistintos = $nota->detalles->pluck('producto_id')->filter()->unique()->count();
        $unidadesDetalle = $nota->detalles->map(fn ($detalle) => $detalle->producto?->unidadMedida?->codigo)->filter()->unique()->values();
        $puedeTotalizarCantidad = $unidadesDetalle->count() === 1;
        $unidadResumen = $unidadesDetalle->first();
        $facturasPosteriores = $nota->detalles->flatMap(fn ($detalle) => $detalle->facturaProveedorDetalles)->map(fn ($detalleFactura) => $detalleFactura->facturaProveedor)->filter()->unique('id')->values();
        $tabs = [
            'fisico' => ['label' => 'Verdad física', 'icon' => 'inventory', 'partial' => '_show_fisico', 'count' => $nota->detalles->count()],
            'documento' => ['label' => 'Documento adjunto', 'icon' => 'file', 'partial' => '_show_documento', 'count' => null],
            'trazabilidad' => ['label' => 'Trazabilidad', 'icon' => 'movements', 'partial' => '_show_trazabilidad', 'count' => null],
            'anulacion' => ['label' => 'Anulación', 'icon' => 'warning', 'partial' => '_show_anulacion', 'count' => null],
        ];
    @endphp

    <div class="document-flow-page document-flow-page--completed">
        <a href="{{ route('notas-ingreso.index') }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a notas de ingreso</a>

        <section class="module-header module-header--compact entry-show-header">
            <div>
                <p class="eyebrow">{{ $nota->motivoVisible() }}</p>
                <h1>{{ $nota->codigo }}</h1>
                <p>Entrada física vinculada a <strong>{{ $origen }}</strong>.</p>
            </div>
            <span class="badge badge--{{ $estadoClase }} badge--large">{{ $nota->estado }}</span>
        </section>

        <x-ui.workflow-stepper
            :steps="$pasosRegistro"
            :current="5"
            :interactive="false"
            :label="$nota->estaAnulada() ? 'Nota de ingreso anulada' : 'Registro de la Nota de Ingreso completado'"
        />

        <section class="entry-detail-workspace" data-entry-detail-tabs data-default-tab="fisico">
            <div class="entry-detail-tabs" role="tablist" aria-label="Secciones de la nota de ingreso">
                @foreach ($tabs as $tab => $config)
                    <button
                        type="button"
                        id="ingreso-tab-{{ $tab }}"
                        class="entry-detail-tab"
                        role="tab"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                        aria-controls="ingreso-panel-{{ $tab }}"
                        tabindex="{{ $loop->first ? '0' : '-1' }}"
                        data-entry-detail-tab="{{ $tab }}"
                    >
                        <x-ui.icon :name="$config['icon']" :size="17" />
                        <span>{{ $config['label'] }}</span>
                        @if ($config['count'] !== null)<span class="entry-detail-tab__count">{{ $config['count'] }}</span>@endif
                    </button>
                @endforeach
            </div>

            @foreach ($tabs as $tab => $config)
                <div
                    id="ingreso-panel-{{ $tab }}"
                    class="entry-detail-tab-panel"
                    role="tabpanel"
                    aria-labelledby="ingreso-tab-{{ $tab }}"
                    data-entry-detail-panel="{{ $tab }}"
                    @if (! $loop->first) hidden @endif
                >
                    @include('notas_ingreso.partials.'.$config['partial'])
                </div>
            @endforeach
        </section>

        @include('notas_ingreso.partials._show_anulacion_modal')
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nota-ingreso-detail.js') }}" defer></script>
@endpush
