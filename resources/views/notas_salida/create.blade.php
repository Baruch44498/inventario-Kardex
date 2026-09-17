@extends('layouts.app')

@section('title', 'Nueva nota de salida')
@section('page-kicker', 'Notas de salida')
@section('page-title', 'Nueva nota de salida')

@section('content')
<div class="document-flow-page" data-document-note-wizard data-initial-step="{{ $pasoActual }}">
    <a href="{{ route('notas-salida.index') }}" class="back-link"><x-ui.icon name="arrow-left" :size="17" /> Volver a notas de salida</a>

    <section class="module-header module-header--compact">
        <div><p class="eyebrow">Movimiento físico</p><h1>Registrar nota de salida</h1><p>Registra lo que realmente deja Almacén y conserva el vínculo con la orden, área, Proforma o motivo interno.</p></div>
    </section>

    <x-ui.workflow-stepper :steps="$pasosRegistro" :current="$pasoActual" />

    @if ($errors->any())
        <div class="notice notice--danger notice--block" role="alert"><x-ui.icon name="error" :size="18" /><div><strong>Revisa la información del formulario.</strong><span>{{ $errors->first() }}</span></div></div>
    @endif

    @include('notas_salida.partials._create_origen')

    @if ($origenListo)
        @include('notas_salida.partials._create_contexto')

        <form method="POST" action="{{ route('notas-salida.store') }}" class="entry-form output-form" data-dirty-form data-loading-form data-output-form data-note-wizard-form>
            @csrf
            <input type="hidden" name="motivo_salida" value="{{ $motivo }}">
            @if ($orden)<input type="hidden" name="orden_operacion_id" value="{{ $orden->id }}"><input type="hidden" name="area_trabajo" value="{{ $areaTrabajo }}">@endif
            @if ($proforma)<input type="hidden" name="proforma_id" value="{{ $proforma->id }}">@endif

            @include('notas_salida.partials._create_datos')
            @include('notas_salida.partials._create_productos')
            @include('notas_salida.partials._create_confirmacion')

            <div class="form-actions form-actions--sticky note-wizard-actions" data-note-wizard-actions>
                <a href="{{ route('notas-salida.index') }}" class="button button--ghost">Cancelar</a>
                <button type="button" class="button button--ghost" data-note-wizard-prev>Cambiar origen</button>
                <button type="button" class="button button--primary" data-note-wizard-next data-step2-label="Continuar a productos" data-step3-label="Revisar confirmación">Continuar a productos</button>
                <button type="submit" class="button button--primary" data-note-wizard-submit data-submit-button data-loading-text="Confirmando salida..." @disabled($filas->isEmpty()) hidden><span data-submit-label>Confirmar nota de salida</span></button>
            </div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/nota-salida-form.js') }}" defer></script>
<script src="{{ asset('js/document-note-wizard.js') }}" defer></script>
@endpush
