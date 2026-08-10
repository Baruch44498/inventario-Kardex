@extends('layouts.app')

@section('title', 'Nuevo requerimiento de compra')
@section('page-kicker', 'Almacén')
@section('page-title', 'Nuevo requerimiento de compra')

@section('content')
    <a href="{{ route('requerimientos-compra.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a requerimientos
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Almacén → Logística</p>
            <h1>Crear requerimiento de compra</h1>
            <p>Registra una necesidad real de abastecimiento. Logística recibirá los productos y los contactos de proveedores conocidos para comenzar a cotizar.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('requerimientos-compra.store') }}" data-dirty-form>
        @csrf
        @include('requerimientos_compra._form')
    </form>
@endsection
