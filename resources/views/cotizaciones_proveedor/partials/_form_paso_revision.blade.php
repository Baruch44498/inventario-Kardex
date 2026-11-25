        <section id="paso-resumen-cotizacion"
            class="panel supplier-quote-review supplier-quote-step-panel"
            data-quote-step-panel="3" hidden>
            <header class="supplier-quote-review__heading">
                <div>
                    <p class="eyebrow">Paso 3 de 3</p>
                    <h2>Revisa antes de registrar</h2>
                    <p>Confirma que el proveedor, los productos y el total sean correctos.</p>
                </div>
                <span class="supplier-quote-review__status" data-review-status>
                    <span data-review-status-text>{{ $totalDocumento !== null ? 'Validando importes' : 'Listo para registrar' }}</span>
                </span>
            </header>

            <div class="supplier-quote-review__grid">
                <div>
                    <span>Proveedor</span>
                    <strong data-review-provider>Por seleccionar</strong>
                </div>
                <div>
                    <span>N.º de cotización</span>
                    <strong data-review-document>Sin número externo</strong>
                </div>
                <div>
                    <span>Fecha</span>
                    <strong data-review-date>—</strong>
                </div>
                <div>
                    <span>Moneda</span>
                    <strong data-review-currency>PEN</strong>
                </div>
                <div>
                    <span>Productos</span>
                    <strong data-review-products>1 producto</strong>
                </div>
                <div class="supplier-quote-review__total">
                    <span>Total final</span>
                    <strong data-review-total>—</strong>
                </div>
            </div>

            @if ($totalDocumento !== null)
                <section class="supplier-quote-reconciliation {{ ($conciliacionInicial['estado'] ?? null) === 'COINCIDE' ? 'is-match' : 'has-difference' }}"
                    data-quote-reconciliation aria-live="polite">
                    <header class="supplier-quote-reconciliation__heading">
                        <div>
                            <p class="eyebrow">Control contra el documento original</p>
                            <h3>Conciliación de importes</h3>
                        </div>
                        <span class="supplier-quote-reconciliation__status" data-reconciliation-status>
                            {{ match ($conciliacionInicial['estado'] ?? null) {
                                'COINCIDE' => 'Importes conciliados',
                                'AJUSTE_REDONDEO' => 'Ajuste pendiente',
                                default => 'Requiere revisión',
                            } }}
                        </span>
                    </header>

                    <div class="supplier-quote-reconciliation__grid">
                        <div>
                            <span>Total del documento</span>
                            <strong data-document-total-output>—</strong>
                        </div>
                        <div>
                            <span>Total calculado</span>
                            <strong data-system-total-output>—</strong>
                        </div>
                        <div>
                            <span>Diferencia</span>
                            <strong data-reconciliation-difference>—</strong>
                        </div>
                        <div>
                            <span>Interpretación inicial</span>
                            <strong>{{ $conciliacionInicial['interpretacion'] ?? 'Revisar IGV y precios' }}</strong>
                        </div>
                        <div>
                            <span>Ajuste por redondeo</span>
                            <strong data-rounding-adjustment-output>—</strong>
                        </div>
                        <div>
                            <span>Total final</span>
                            <strong data-reconciled-total-output>—</strong>
                        </div>
                    </div>

                    <p class="supplier-quote-reconciliation__message" data-reconciliation-message>
                        El total calculado debe coincidir con el importe declarado por el proveedor.
                    </p>
                    <p class="supplier-quote-reconciliation__rule">
                        El sistema no registrará la cotización mientras exista una diferencia.
                    </p>

                    <div class="supplier-quote-reconciliation__confirmation"
                        data-rounding-adjustment-confirmation hidden>
                        <input type="hidden" name="ajuste_redondeo_confirmado" value="0">
                        <label>
                            <input type="checkbox" name="ajuste_redondeo_confirmado" value="1"
                                @checked($ajusteConfirmadoInicial)
                                data-rounding-adjustment-checkbox>
                            <span>
                                <strong>Confirmo el ajuste por redondeo propuesto.</strong>
                                <small>
                                    El sistema conservará el cálculo de cada línea y registrará en
                                    la cabecera el ajuste, el total documental, mi usuario y la fecha.
                                    Solo se permiten diferencias de hasta S/ 0.05 o US$ 0.05.
                                </small>
                            </span>
                        </label>
                    </div>
                    @error('reconciliacion_documento')
                        <p class="field-error supplier-quote-reconciliation__error">{{ $message }}</p>
                    @enderror
                    @error('ajuste_redondeo_confirmado')
                        <p class="field-error supplier-quote-reconciliation__error">{{ $message }}</p>
                    @enderror
                </section>
            @endif

            <div class="supplier-quote-review__notice">
                <x-ui.icon name="info" :size="19" />
                <p>
                    La cotización se guardará en el historial de precios. No genera
                    entrada de inventario ni confirma una compra.
                </p>
            </div>

            <div class="form-actions supplier-quote-step-actions">
                <button type="button" class="button button--ghost"
                    data-previous-quote-step="2">
                    Volver a productos
                </button>
                <button type="submit" class="button button--primary" data-submit-supplier-quote>
                    <x-ui.icon name="check" :size="18" />
                    {{ $editando ? 'Guardar cambios' : 'Registrar cotización' }}
                </button>
            </div>
        </section>
