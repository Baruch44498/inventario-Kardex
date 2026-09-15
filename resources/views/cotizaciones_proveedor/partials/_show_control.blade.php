    @if ($cotizacion->estaAnulada())
        <section class="notice notice--danger notice--block supplier-quote-cancelled">
            <x-ui.icon name="error" :size="20" />
            <div>
                <strong>Cotización invalidada</strong>
                <p>
                    {{ $cotizacion->motivo_anulacion }}
                    @if ($cotizacion->anulado_en)
                        · {{ $cotizacion->anulado_en->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>
        </section>
    @elseif ($cotizacion->puedeInvalidar())
        <section class="supplier-quote-danger-zone">
            <div>
                <p class="eyebrow">Control documental</p>
                <h2>Invalidar por error de registro</h2>
                <p>No la invalides solo porque no fue elegida: en ese caso debe permanecer como referencia histórica.</p>
            </div>

            <form method="POST"
                action="{{ route('cotizaciones-proveedor.anular', $cotizacion->id) }}"
                class="supplier-quote-cancel-form"
                data-confirm="¿Confirmas invalidar esta cotización por un error de registro?">
                @csrf
                @method('PATCH')
                <input type="text" name="motivo_anulacion" maxlength="500"
                    minlength="5" required placeholder="Describe el error de registro">
                <button type="submit" class="button button--danger">
                    <x-ui.icon name="error" :size="17" /> Invalidar
                </button>
            </form>
        </section>
    @endif

    @if (! $cotizacion->estaAnulada() && ! $cotizacion->puedeInvalidar())
        <section class="notice notice--info notice--block supplier-quote-control-complete">
            <x-ui.icon name="check-circle" :size="20" />
            <div>
                <strong>Sin acciones documentales pendientes</strong>
                <p>La cotización conserva su trazabilidad y ya no admite cambios de control.</p>
            </div>
        </section>
    @endif
