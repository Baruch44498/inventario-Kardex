@extends('layouts.app')

@section('title', 'Editar requerimiento de compra')
@section('page-kicker', 'Almacén')
@section('page-title', 'Editar requerimiento')

@section('content')
    <a href="{{ route('requerimientos-compra.show', $requerimiento) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver al requerimiento
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Borrador {{ $requerimiento->codigo }}</p>
            <h1>Editar requerimiento de compra</h1>
            <p>Puedes corregirlo mientras siga en borrador. Después de enviarlo a Logística la necesidad queda congelada como documento recibido.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('requerimientos-compra.update', $requerimiento) }}" data-dirty-form>
        @csrf
        @method('PUT')
        @include('requerimientos_compra._form')
    </form>
@endsection
