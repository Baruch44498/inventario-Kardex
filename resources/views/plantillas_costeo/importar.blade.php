@extends('layouts.app')

@section('title', 'Importar plantilla de costos')
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Importar Excel de costos')

@section('content')
    <a href="{{ route('plantillas-costeo.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a plantillas
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">19.0.6 R2.2 · Importación asistida</p>
            <h1>Convertir un Excel en plantilla reutilizable</h1>
            <p>El archivo se revisa antes de crear la plantilla. Ningún material nuevo entra automáticamente al catálogo.</p>
        </div>
    </section>

    <section class="notice notice--info notice--block">
        <x-ui.icon name="clipboard" :size="20" />
        <div>
            <strong>El mismo formato sirve para OM, OS y OP</strong>
            <span>Primero eliges el tipo de trabajo. Después el sistema lee grupos, cantidades, costos con IGV, margen y tipo de cambio del Excel.</span>
        </div>
    </section>

    <section class="panel">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Paso 1 de 3</p>
                <h2>Datos de la nueva plantilla</h2>
                <p>Luego podrás revisar cada material no reconocido antes de confirmar.</p>
            </div>
        </header>

        <form method="POST" action="{{ route('plantillas-costeo.importaciones.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="operation-form-grid">
                <label class="form-field">
                    <span>Tipo de orden <span class="required-mark">*</span></span>
                    <select name="tipo_orden_id" required>
                        <option value="">Selecciona OM, OS u OP</option>
                        @foreach ($tiposOrden as $tipo)
                            <option value="{{ $tipo->id }}" @selected((int) old('tipo_orden_id') === $tipo->id)>
                                {{ $tipo->codigo }} · {{ $tipo->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('tipo_orden_id')<small class="field-error">{{ $message }}</small>@enderror
                </label>

                <label class="form-field">
                    <span>Nombre de la plantilla <span class="required-mark">*</span></span>
                    <input type="text" name="nombre" minlength="5" maxlength="180" value="{{ old('nombre') }}" placeholder="Ej. Cisterna 5000 galones SCH-40" required>
                    @error('nombre')<small class="field-error">{{ $message }}</small>@enderror
                </label>

                <label class="form-field form-field--span-2">
                    <span>Descripción</span>
                    <input type="text" name="descripcion" maxlength="500" value="{{ old('descripcion') }}" placeholder="Modelo, capacidad y alcance que ayudan a reconocerla">
                    @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
                </label>

                <label class="form-field form-field--span-2">
                    <span>Archivo Excel <span class="required-mark">*</span></span>
                    <input type="file" name="documento" accept=".xlsx,.xls" required>
                    <small>Máximo 15 MB. Se usa la hoja activa y el formato CANT. / DESCRIPCIÓN / COSTO / T.C.</small>
                    @error('documento')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </div>

            <div class="form-actions">
                <a href="{{ route('plantillas-costeo.index') }}" class="button button--ghost">Cancelar</a>
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="check-circle" :size="17" /> Leer y revisar Excel
                </button>
            </div>
        </form>
    </section>
@endsection
