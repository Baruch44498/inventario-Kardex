<section class="panel supplier-quote-import-panel supplier-quote-entry-panel">
    <div class="panel-heading supplier-quote-import-panel__heading">
        <div>
            <p class="eyebrow">Ingreso asistido</p>
            <h2>Precargar desde el documento del proveedor</h2>
            <p>Sube un Excel o PDF digital. Después regresarás al mismo formulario para revisar cada dato antes de registrar.</p>
        </div>
    </div>

    @if ($errors->has('documento') || $errors->has('detalle_ids'))
        <div class="notice notice--danger notice--block supplier-quote-import-error" role="alert">
            <x-ui.icon name="error" :size="18" />
            <div>
                <strong>No se pudo procesar el documento.</strong>
                <span>{{ $errors->first('documento') ?: $errors->first('detalle_ids') }}</span>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('cotizaciones-proveedor.importacion.store') }}"
        enctype="multipart/form-data" class="supplier-quote-import-form" data-dirty-form>
        @csrf
        <input type="hidden" name="requisicion_id" value="{{ $requisicionSeleccionada?->id }}">
        <input type="hidden" name="proveedor_id" value="{{ old('proveedor_id', $proveedorSeleccionado?->id) }}">

        @foreach ($detalleIdsContexto as $detalleId)
            <input type="hidden" name="detalle_ids[]" value="{{ $detalleId }}">
        @endforeach

        <div class="supplier-quote-import-context">
            <div>
                <span>Requerimiento</span>
                <strong>{{ $requisicionSeleccionada?->codigo ?? 'Sin requerimiento' }}</strong>
            </div>
            <div>
                <span>Alcance</span>
                <strong>
                    @if ($requisicionSeleccionada)
                        {{ $detalleIdsContexto->isNotEmpty() ? $detalleIdsContexto->count().' línea(s) seleccionada(s)' : 'Todos los productos detectables' }}
                    @else
                        Vinculación manual con catálogo
                    @endif
                </strong>
            </div>
            <div>
                <span>Proveedor inicial</span>
                <strong>{{ $proveedorSeleccionado?->nombreVisible() ?? 'Se intentará detectar por RUC' }}</strong>
            </div>
        </div>

        <div class="form-field supplier-quote-import-file">
            <label for="documento">Archivo de cotización <span class="required-mark">*</span></label>
            <input id="documento" name="documento" type="file" accept=".xlsx,.xls,.csv,.pdf" required>
            <small>Máximo 15 MB. El PDF debe contener texto seleccionable; este bloque no procesa escaneos.</small>
            @error('documento')<small class="field-error">{{ $message }}</small>@enderror
        </div>

        <div class="notice notice--warning notice--block">
            <x-ui.icon name="warning" :size="18" />
            <div>
                <strong>La revisión humana es obligatoria.</strong>
                <span>Los datos no confirmados —incluidos cantidad, producto e IGV— quedarán pendientes en vez de completarse mediante supuestos.</span>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('cotizaciones-proveedor.create', [...$contextoRegistro, 'modo' => 'manual']) }}"
                class="button button--ghost">Continuar manualmente</a>
            <button type="submit" class="button button--primary">
                <x-ui.icon name="upload" :size="17" /> Extraer y precargar formulario
            </button>
        </div>
    </form>
</section>
