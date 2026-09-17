<section id="paso-datos" class="panel form-panel" data-flow-step-section="2">
    <div class="panel-heading"><p class="eyebrow">Datos de recepción</p><h2>Documento y referencia</h2></div>
    <div class="form-grid form-grid--entry-header">
        <div class="form-field generated-code-form-field">
            <span>Código de nota</span>
            <div class="generated-code-field"><span class="generated-code-field__icon"><x-ui.icon name="hash" :size="18" /></span><strong class="generated-code-field__value">NI-###-{{ now()->format('y') }}</strong><span class="badge badge--info">Automático</span></div>
        </div>
        <div class="form-field">
            <label for="fecha_ingreso">Fecha de ingreso <span class="required-mark">*</span></label>
            <input id="fecha_ingreso" name="fecha_ingreso" type="date" value="{{ old('fecha_ingreso', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
        </div>

        @if ($motivo === 'COMPRA')
            <div class="form-field">
                <label for="factura_proveedor_id">Factura vinculada</label>
                <select id="factura_proveedor_id" name="factura_proveedor_id" data-invoice-selector data-reload-url="{{ route('notas-ingreso.create', ['motivo_ingreso' => 'COMPRA', 'orden_compra_id' => $orden->id]) }}">
                    <option value="">Sin factura vinculada</option>
                    @foreach ($facturas as $factura)
                        <option value="{{ $factura->id }}" @selected((int) $facturaSeleccionada?->id === (int) $factura->id)>{{ $factura->tipo_documento }} {{ $factura->serie }}-{{ $factura->numero }}</option>
                    @endforeach
                </select>
                @if ($facturas->isEmpty())
                    <small>No hay factura registrada. El ingreso usará provisionalmente el total autorizado de la OC.</small>
                @endif
            </div>
            <div class="form-field">
                <label for="numero_guia_remision">Guía de remisión</label>
                <input id="numero_guia_remision" name="numero_guia_remision" type="text" value="{{ old('numero_guia_remision') }}" maxlength="60">
            </div>
        @endif

        @if (in_array($motivo, ['DEVOLUCION_HERRAMIENTA', 'RETORNO_MATERIAL', 'DEVOLUCION_MATERIAL_MALOGRADO'], true))
            <div class="form-field form-field--span-2">
                <label for="devuelto_por_empleado_id">Empleado que devuelve <span class="required-mark">*</span></label>
                <select id="devuelto_por_empleado_id" name="devuelto_por_empleado_id" required>
                    <option value="">Selecciona nombre y DNI</option>
                    @foreach ($empleadosActivos as $empleado)
                        <option value="{{ $empleado->id }}" @selected((int) old('devuelto_por_empleado_id') === $empleado->id)>{{ $empleado->nombre_completo }} — DNI {{ $empleado->dni }}</option>
                    @endforeach
                </select>
                <small>Quedará vinculado a la misma orden y área de la Nota de Salida.</small>
            </div>
        @endif

        <div class="form-field form-field--span-2">
            <label for="observacion">Observación general</label>
            <textarea id="observacion" name="observacion" rows="3" maxlength="500" placeholder="Estado de la herramienta, material retornado o referencia de la recepción">{{ old('observacion') }}</textarea>
        </div>
    </div>
</section>
