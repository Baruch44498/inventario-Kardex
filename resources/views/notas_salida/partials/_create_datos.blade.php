<section id="paso-datos" class="panel form-panel" data-flow-step-section="2">
    <div class="panel-heading"><p class="eyebrow">Datos de entrega</p><h2>Documento y responsable</h2></div>
    <div class="form-grid form-grid--entry-header">
        <div class="form-field generated-code-form-field"><span>Código de nota</span><div class="generated-code-field"><span class="generated-code-field__icon"><x-ui.icon name="hash" :size="18" /></span><strong class="generated-code-field__value">NS-###-{{ now()->format('y') }}</strong><span class="badge badge--info">Automático</span></div></div>
        <div class="form-field"><label for="fecha_salida">Fecha de salida <span class="required-mark">*</span></label><input id="fecha_salida" name="fecha_salida" type="date" value="{{ old('fecha_salida', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>

        @if ($motivo === 'ORDEN_OPERACION')
            <div class="form-field form-field--span-2">
                <label for="recibido_por_empleado_id">Empleado que recibe <span class="required-mark">*</span></label>
                <select id="recibido_por_empleado_id" name="recibido_por_empleado_id" required><option value="">Selecciona nombre y DNI</option>@foreach ($empleadosActivos as $empleado)<option value="{{ $empleado->id }}" @selected((int) old('recibido_por_empleado_id') === $empleado->id)>{{ $empleado->nombre_completo }} — DNI {{ $empleado->dni }}</option>@endforeach</select>
                <small>Se guardarán el nombre y DNI actuales como fotografía histórica de la entrega.</small>
            </div>
            @if ($empleadosActivos->isEmpty())<div class="notice notice--warning notice--block form-field--span-2"><x-ui.icon name="warning" :size="18" /><span>Primero registra y activa al empleado que recibirá los productos.</span></div>@endif
        @else
            <div class="form-field form-field--span-2"><label for="entregado_a">Entregado a <span class="required-mark">*</span></label><input id="entregado_a" name="entregado_a" type="text" value="{{ old('entregado_a', $proforma?->cliente?->nombreVisible()) }}" maxlength="150" placeholder="Persona o empresa que recibe" required><small>Para herramientas, identifica a la persona responsable mientras permanezcan fuera del Almacén.</small></div>
        @endif

        <div class="form-field form-field--span-2"><label for="observacion">Observación general</label><textarea id="observacion" name="observacion" rows="3" maxlength="500" placeholder="Destino, referencia o indicación adicional">{{ old('observacion') }}</textarea></div>
    </div>
</section>
