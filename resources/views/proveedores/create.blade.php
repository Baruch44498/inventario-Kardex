@extends('layouts.app')

@section('title', 'Nuevo proveedor')
@section('page-kicker', 'Proveedores')
@section('page-title', 'Nuevo proveedor')

@section('content')
    <a href="{{ route('proveedores.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a proveedores
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Registro logístico</p>
            <h1>Registrar proveedor</h1>
            <p>Guarda sus datos para registrar y comparar los precios que ofrece.</p>
        </div>
    </section>

    <section class="panel form-panel supplier-form-panel">
        <form method="POST" action="{{ route('proveedores.store') }}" data-dirty-form>
            @csrf
            @include('proveedores._form')
        </form>
    </section>
@endsection
