@extends('layouts.app')

@section('title', 'Nueva nota de ingreso')
@section('page-kicker', 'Notas de ingreso')
@section('page-title', 'Nueva nota de ingreso')

@section('content')
<div class="document-flow-page" data-document-note-wizard data-initial-step="{{ $pasoActual }}">
    <a href="{{ route('notas-ingreso.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a notas de ingreso
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Entrada física</p>
            <h1>Registrar nota de ingreso</h1>
            <p>Recibe compras, devoluciones, retornos de material o reposiciones manteniendo la referencia física original.</p>
        </div>
    </section>

    <x-ui.workflow-stepper :steps="$pasosRegistro" :current="$pasoActual" />

    @if ($errors->any())
        <div class="notice notice--danger notice--block" role="alert">
            <x-ui.icon name="error" :size="18" />
            <div><strong>Revisa la información del formulario.</strong><span>{{ $errors->first() }}</span></div>
        </div>
    @endif

    @include('notas_ingreso.partials._create_origen')

    @if ($origenListo)
        @include('notas_ingreso.partials._create_contexto')

        <form method="POST" action="{{ route('notas-ingreso.store') }}" class="entry-form" data-dirty-form data-loading-form data-note-wizard-form>
            @csrf
            <input type="hidden" name="motivo_ingreso" value="{{ $motivo }}">
            @if ($orden)<input type="hidden" name="orden_compra_id" value="{{ $orden->id }}">@endif
            @if ($notaSalida)<input type="hidden" name="nota_salida_id" value="{{ $notaSalida->id }}">@endif
            @if ($proforma)<input type="hidden" name="proforma_id" value="{{ $proforma->id }}">@endif

            @include('notas_ingreso.partials._create_datos')
            @include('notas_ingreso.partials._create_productos')
            @include('notas_ingreso.partials._create_confirmacion')

            <div class="form-actions form-actions--sticky note-wizard-actions" data-note-wizard-actions>
                <a href="{{ route('notas-ingreso.index') }}" class="button button--ghost">Cancelar</a>
                <button type="button" class="button button--ghost" data-note-wizard-prev>Cambiar origen</button>
                <button type="button" class="button button--primary" data-note-wizard-next data-step2-label="Continuar a productos" data-step3-label="Revisar confirmación">Continuar a productos</button>
                <button type="submit" class="button button--primary" data-note-wizard-submit data-submit-button data-loading-text="Confirmando ingreso..." @disabled($filas->isEmpty()) hidden>
                    <span data-submit-label>Confirmar nota de ingreso</span>
                </button>
            </div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/nota-ingreso-form.js') }}" defer></script>
    <script src="{{ asset('js/document-note-wizard.js') }}" defer></script>
@endpush
