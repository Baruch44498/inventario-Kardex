@extends('layouts.app')

@section('title', 'Importar cotización de proveedor')
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Importación asistida')

@section('content')
    <a href="{{ route('requerimientos-compra.show', $requisicion) }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver al requerimiento
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">17.1.2 · Documento del proveedor</p>
            <h1>Importar cotización</h1>
            <p>Sube un Excel o PDF digital. El sistema extrae datos, pero no registra nada hasta que Logística revise y confirme la vista previa.</p>
        </div>
    </section>

    @if ($errors->any())
        <div class="notice notice--danger notice--block" role="alert">
            <x-ui.icon name="error" :size="18" />
            <div><strong>No se pudo procesar el documento.</strong><span>{{ $errors->first() }}</span></div>
        </div>
    @endif

    <section class="panel supplier-quote-import-panel">
        <div class="panel-heading supplier-quote-import-panel__heading">
            <div>
                <p class="eyebrow">Requerimiento {{ $requisicion->codigo }}</p>
                <h2>Documento recibido del proveedor</h2>
                <p>Formatos admitidos: Excel (.xlsx, .xls, .csv) y PDF con texto seleccionable. Los escaneos quedan fuera de este bloque.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('cotizaciones-proveedor.importacion.store') }}" enctype="multipart/form-data" class="supplier-quote-import-form" data-dirty-form>
            @csrf
            <input type="hidden" name="requisicion_id" value="{{ $requisicion->id }}">
            <input type="hidden" name="proveedor_id" value="{{ old('proveedor_id', $proveedor?->id) }}">

            <div class="supplier-quote-import-context">
                <div>
                    <span>Requerimiento</span>
                    <strong>{{ $requisicion->codigo }}</strong>
                </div>
                <div>
                    <span>Productos solicitados</span>
                    <strong>{{ $requisicion->detalles->count() }}</strong>
                </div>
                <div>
                    <span>Proveedor inicial</span>
                    <strong>{{ $proveedor?->nombreVisible() ?? 'Se intentará detectar por RUC' }}</strong>
                </div>
            </div>

            <div class="form-field supplier-quote-import-file">
                <label for="documento">Archivo de cotización <span class="required-mark">*</span></label>
                <input id="documento" name="documento" type="file" accept=".xlsx,.xls,.csv,.pdf" required>
                <small>Máximo 15 MB. Un PDF escaneado sin texto no será guardado como cotización.</small>
                @error('documento')<small class="field-error">{{ $message }}</small>@enderror
            </div>

            <div class="notice notice--warning notice--block">
                <x-ui.icon name="warning" :size="18" />
                <div>
                    <strong>La revisión humana es obligatoria.</strong>
                    <span>La extracción puede interpretar de forma distinta cantidades, precios, IGV o descripciones según el formato del proveedor. Confirma cada línea antes de registrar.</span>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('requerimientos-compra.show', $requisicion) }}" class="button button--ghost">Cancelar</a>
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="upload" :size="17" /> Extraer y revisar
                </button>
            </div>
        </form>
    </section>
@endsection
