@extends('layouts.app')

@section('title', 'Editar proveedor')
@section('page-kicker', 'Proveedores')
@section('page-title', 'Editar proveedor')

@section('content')
    <a href="{{ route('proveedores.show', $proveedor->id) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver al proveedor
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Proveedor registrado</p>
            <h1>{{ $proveedor->nombreVisible() }}</h1>
            <p>Los cambios no afectan las cotizaciones ni el historial registrado.</p>
        </div>
    </section>

    <section class="panel form-panel supplier-form-panel">
        <form method="POST" action="{{ route('proveedores.update', $proveedor->id) }}" data-dirty-form>
            @csrf
            @method('PUT')
            @include('proveedores._form')
        </form>
    </section>
@endsection
