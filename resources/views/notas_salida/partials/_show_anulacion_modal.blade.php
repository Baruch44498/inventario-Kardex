@if ($nota->estaConfirmada())
<div class="modal-backdrop" data-output-cancel-modal @if (! $errors->has('motivo_anulacion')) hidden @endif>
    <section class="confirmation-modal output-cancel-modal" role="dialog" aria-modal="true" aria-labelledby="output-cancel-title" aria-describedby="output-cancel-description" tabindex="-1">
        <span class="confirmation-modal__icon confirmation-modal__icon--danger"><x-ui.icon name="warning" :size="25" /></span>
        <div class="confirmation-modal__content"><h2 id="output-cancel-title">¿Anular nota de salida?</h2><p id="output-cancel-description">El sistema devolverá las cantidades al inventario y registrará movimientos de reversa.</p></div>
        <form method="POST" action="{{ route('notas-salida.anular', $nota->id) }}" data-loading-form class="output-cancel-form">@csrf @method('PATCH')
            <label class="form-field"><span>Motivo de anulación <span class="required-mark">*</span></span><textarea name="motivo_anulacion" rows="4" maxlength="500" required placeholder="Explica por qué se anula esta salida" @class(['is-invalid' => $errors->has('motivo_anulacion')])>{{ old('motivo_anulacion') }}</textarea>@error('motivo_anulacion')<small class="field-error" role="alert">{{ $message }}</small>@enderror</label>
            <div class="confirmation-modal__actions"><button type="button" class="button button--ghost" data-close-output-cancel>Mantener nota</button><button type="submit" class="button button--danger" data-submit-button data-loading-text="Anulando salida..."><span data-submit-icon><x-ui.icon name="error" :size="17" /></span><span class="button-spinner" data-submit-spinner hidden></span><span data-submit-label>Anular y restituir stock</span></button></div>
        </form>
    </section>
</div>
@endif
