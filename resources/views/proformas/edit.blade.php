@extends('layouts.app')

@section('title', 'Editar '.$proforma->codigo)
@section('page-kicker', 'Proformas')
@section('page-title', 'Editar proforma')

@section('content')
    <a href="{{ route('proformas.show', $proforma) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver al detalle
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Borrador de Almacén</p>
            <h1>{{ $proforma->codigo }}</h1>
            <p>Los precios sugeridos se recalculan con el tipo de cliente y el costo actual.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('proformas.update', $proforma) }}" data-dirty-form data-loading-form>
        @csrf
        @method('PUT')
        @include('proformas._form')
    </form>
@endsection
