@extends('layouts.app')

@section('title', 'Nueva proforma')
@section('page-kicker', 'Proformas')
@section('page-title', 'Nueva proforma')

@section('content')
    <a href="{{ route('proformas.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a proformas
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Preparada por Almacén</p>
            <h1>Nueva proforma</h1>
            <p>Selecciona productos del inventario. El precio mostrado es una sugerencia y Logística podrá negociarlo.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('proformas.store') }}" data-dirty-form data-loading-form>
        @csrf
        @include('proformas._form')
    </form>
@endsection
