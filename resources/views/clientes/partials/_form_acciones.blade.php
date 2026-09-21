<div class="form-actions">
    <button
        type="button"
        class="button button--ghost"
        data-cancel-form
        data-cancel-url="{{ $editando
            ? route('clientes.show', $cliente->id)
            : route('clientes.index') }}"
    >
        Cancelar
    </button>

    <button type="submit" class="button button--primary">
        <x-ui.icon name="check" :size="18" />
        {{ $editando
            ? 'Guardar cambios'
            : 'Registrar cliente' }}
    </button>
</div>
