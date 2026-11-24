@php
    $esTraslado = $tipo === 'TRASLADO';
    $retomar = old('tipo') === $tipo;
    $destinoPrevio = $retomar ? $destinoAnterior : null;
    $pasoCantidad = $producto->permite_fraccionamiento ? '0.001' : '1';
@endphp

<section id="{{ $seccionId }}" class="panel form-panel registro-almacen__panel" aria-labelledby="{{ $seccionId }}-titulo">
    <div class="panel-heading">
        <h2 id="{{ $seccionId }}-titulo">{{ $titulo }}</h2>
        <p>{{ $esTraslado ? 'Mueve una cantidad entre dos repisas del mismo almacén.' : 'Registra una existencia física que todavía no figure en el sistema. Revisa las ubicaciones de arriba para evitar duplicarla.' }}</p>
    </div>
    <form class="stack-form" method="POST" action="{{ route('registro-almacen.operaciones.store', $producto) }}"
        data-dirty-form data-confirm="{{ $esTraslado ? '¿Registrar el traslado de stock entre estas repisas?' : '¿Registrar estas unidades como saldo inicial en la repisa?' }}">
        @csrf
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <input type="hidden" name="solicitud" value="{{ $retomar ? (old('solicitud') ?: \Illuminate\Support\Str::uuid()) : \Illuminate\Support\Str::uuid() }}">
        <div class="form-grid form-grid--two">
            @if ($esTraslado)
                <label class="form-field">
                    <span>Repisa de origen</span>
                    <select name="origen_id" required>
                        <option value="">Selecciona la repisa</option>
                        @foreach ($origenes as $origen)
                            <option value="{{ $origen->repisa_id }}" @selected($retomar && (int) old('origen_id') === (int) $origen->repisa_id)>
                                {{ $origen->repisa->codigo }} · {{ $origen->stock_actual }} {{ $unidad }}
                            </option>
                        @endforeach
                    </select>
                    @if ($retomar) @error('origen_id')<small class="field-error">{{ $message }}</small>@enderror @endif
                </label>
            @endif

            <div class="form-field">
                <label for="{{ $tipo }}_destino_busqueda">Repisa de destino</label>
                <x-ui.remote-combobox
                    name="destino_id"
                    :search-id="$tipo.'_destino_busqueda'"
                    :value-id="$tipo.'_destino_id'"
                    :search-url="route('catalogos.repisas.buscar')"
                    :selected-id="$destinoPrevio?->id"
                    :selected-label="$destinoPrevio?->codigo ?? ''"
                    placeholder="Busca por código o descripción"
                    required
                />
                @if ($retomar) @error('destino_id')<small class="field-error">{{ $message }}</small>@enderror @endif
            </div>

            <label class="form-field">
                <span>Cantidad ({{ $unidad }})</span>
                <input type="number" name="cantidad" inputmode="decimal" min="{{ $pasoCantidad }}" step="{{ $pasoCantidad }}" value="{{ $retomar ? old('cantidad') : '' }}" required>
                @if ($retomar) @error('cantidad')<small class="field-error">{{ $message }}</small>@enderror @endif
            </label>
            @unless ($esTraslado)
                <label class="form-field">
                    <span>Costo unitario inicial en soles</span>
                    <input type="number" name="costo_inicial" min="0" step="0.0001" inputmode="decimal" value="{{ $retomar ? old('costo_inicial') : '' }}">
                    <small>Si todavía no está validado, puedes dejarlo vacío. El saldo empezará valorizado en S/ 0.</small>
                    @if ($retomar) @error('costo_inicial')<small class="field-error">{{ $message }}</small>@enderror @endif
                </label>
            @endunless
            <label class="form-field">
                <span>Motivo</span>
                <input type="text" name="observacion" maxlength="240" value="{{ $retomar ? old('observacion') : '' }}" required>
                @if ($retomar) @error('observacion')<small class="field-error">{{ $message }}</small>@enderror @endif
            </label>
        </div>
        <label class="switch-field registro-almacen__switch">
            <input type="checkbox" name="confirmar_compartida" value="1" @checked($retomar && old('confirmar_compartida'))>
            <span class="switch-field__control"></span>
            <span>Confirmo que se puede compartir la repisa si ya contiene otro producto.</span>
        </label>
        @if ($retomar) @error('confirmar_compartida')<small class="field-error">{{ $message }}</small>@enderror @endif
        <div class="form-actions"><button type="submit" class="button button--primary">{{ $titulo }}</button></div>
    </form>
</section>
