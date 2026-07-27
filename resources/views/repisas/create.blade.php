@extends('layouts.app')

@section('title', 'Nueva repisa')
@section('page-kicker', 'Repisas')
@section('page-title', 'Nueva repisa')

@section('content')
    <a href="{{ route('repisas.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a repisas
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Ubicación física</p>
            <h1>Registrar repisa</h1>
            <p>Utiliza el código físico que aparece en el almacén.</p>
        </div>
    </section>

    <section class="panel form-panel form-panel--narrow">
        <form method="POST" action="{{ route('repisas.store') }}" data-loading-form data-dirty-form>
            @include('repisas._form')
        </form>
    </section>
@endsection
