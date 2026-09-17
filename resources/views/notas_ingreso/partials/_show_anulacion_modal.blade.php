@if ($puedeAnular)
    <div class="modal-backdrop" data-entry-cancel-modal @if (! $errors->has('motivo_anulacion') && ! $errors->has('estado')) hidden @endif>
        <section class="confirmation-modal output-cancel-modal" role="dialog" aria-modal="true" aria-labelledby="entry-cancel-title" aria-describedby="entry-cancel-description" tabindex="-1">
            <span class="confirmation-modal__icon confirmation-modal__icon--danger"><x-ui.icon name="warning" :size="25" /></span>

            <div class="confirmation-modal__content">
                <h2 id="entry-cancel-title">¿Anular recepción de compra?</h2>
                <p id="entry-cancel-description">El sistema retirará las cantidades del inventario, registrará movimientos de reversa y devolverá el saldo a la OC y al requerimiento.</p>
            </div>

            @error('estado')
                <div class="notice notice--danger notice--block"><x-ui.icon name="error" :size="18" /><span>{{ $message }}</span></div>
            @enderror

            <form method="POST" action="{{ route('notas-ingreso.anular', $nota->id) }}" data-loading-form class="output-cancel-form">
                @csrf
                @method('PATCH')

                <label class="form-field">
                    <span>Motivo de anulación <span class="required-mark">*</span></span>
                    <textarea name="motivo_anulacion" rows="4" maxlength="500" required placeholder="Explica por qué se corrige esta recepción" @class(['is-invalid' => $errors->has('motivo_anulacion')])>{{ old('motivo_anulacion') }}</textarea>
                    @error('motivo_anulacion')<small class="field-error" role="alert">{{ $message }}</small>@enderror
                </label>

                <div class="confirmation-modal__actions">
                    <button type="button" class="button button--ghost" data-close-entry-cancel>Mantener recepción</button>
                    <button type="submit" class="button button--danger" data-submit-button data-loading-text="Anulando recepción...">
                        <span data-submit-icon><x-ui.icon name="error" :size="17" /></span>
                        <span class="button-spinner" data-submit-spinner hidden></span>
                        <span data-submit-label>Anular y revertir ingreso</span>
                    </button>
                </div>
            </form>
        </section>
    </div>
@endif
