@extends('layouts.app')

@section('title', 'Editar repisa')
@section('page-kicker', 'Repisas')
@section('page-title', 'Editar repisa')

@section('content')
    <a href="{{ route('repisas.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" />
        Volver a repisas
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Ubicación física</p>
            <h1>{{ $repisa->codigo }}</h1>
            <p>Modifica la identificación o el estado de esta ubicación.</p>
        </div>
    </section>

    <section class="panel form-panel form-panel--narrow">
        <form
            method="POST"
            action="{{ route('repisas.update', $repisa->id_repisa) }}"
            data-loading-form
            data-dirty-form
        >
            @include('repisas._form')
        </form>
    </section>
@endsection
