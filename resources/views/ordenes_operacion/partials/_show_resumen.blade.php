        <section class="operation-show-grid">
            <article class="panel operation-context-card" id="contexto">
                <div class="panel-heading operation-card-heading">
                    <p class="eyebrow">Datos principales</p>
                    <h2>Contexto de la orden</h2>
                </div>

                <div class="operation-context-description">
                    <span class="operation-context-description__icon">
                        <x-ui.icon name="clipboard" :size="20" />
                    </span>
                    <div>
                        <span>Descripción del trabajo</span>
                        <p>
                            {{ $tieneDescripcion
                                ? $descripcion
                                : 'Sin descripción registrada.' }}
                        </p>
                    </div>
                </div>

                <dl class="operation-info-grid">
                    <div class="operation-info-item">
                        <dt>Tipo</dt>
                        <dd>
                            {{ $orden->tipoOrden?->codigo }}
                            · {{ $orden->tipoOrden?->nombre }}
                        </dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Fecha de apertura</dt>
                        <dd>{{ $orden->fecha_apertura?->format('d/m/Y') }}</dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Cliente</dt>
                        <dd>
                            {{ $orden->cliente?->razon_social ?? 'Sin cliente asociado' }}
                        </dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Vehículo</dt>
                        <dd>{{ $vehiculo }}</dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Ubicación de referencia</dt>
                        <dd>
                            @if ($orden->clienteDireccion)
                                {{ $orden->clienteDireccion->destino
                                    ?: $orden->clienteDireccion->direccion }}

                                @if ($orden->clienteDireccion->ciudad)
                                    <small>
                                        · {{ $orden->clienteDireccion->ciudad }}
                                    </small>
                                @endif
                            @else
                                Sin ubicación asociada
                            @endif

                            <small>· Atención y recojo en HIDROIL</small>
                        </dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Creado por</dt>
                        <dd>{{ $orden->creador?->username ?? '—' }}</dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Creación</dt>
                        <dd>{{ $orden->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Activación</dt>
                        <dd>
                            @if ($orden->iniciado_en)
                                {{ $orden->iniciado_en->format('d/m/Y H:i') }}
                                <small>· {{ $orden->iniciador?->username ?? 'Usuario' }}</small>
                            @else
                                Pendiente
                            @endif
                        </dd>
                    </div>

                    <div class="operation-info-item">
                        <dt>Cierre</dt>
                        <dd>
                            @if ($orden->cerrado_en)
                                {{ $orden->cerrado_en->format('d/m/Y H:i') }}
                                <small>· {{ $orden->cerrador?->username ?? 'Usuario' }}</small>
                            @else
                                Pendiente
                            @endif
                        </dd>
                    </div>

                    @if ($orden->observacion_cierre)
                        <div class="operation-info-item">
                            <dt>Resultado del cierre</dt>
                            <dd>{{ $orden->observacion_cierre }}</dd>
                        </div>
                    @endif
                </dl>
            </article>

            <aside class="panel operation-lifecycle-card">
                <div class="panel-heading operation-card-heading">
                    <p class="eyebrow">Ciclo de vida</p>
                    <h2>Acciones de estado</h2>
                </div>

                <div class="operation-lifecycle-status">
                    <span class="operation-lifecycle-status__icon">
                        <x-ui.icon name="activity" :size="25" />
                    </span>
                    <div>
                        <span>Estado actual</span>
                        <strong>{{ str_replace('_', ' ', $orden->estado) }}</strong>
                    </div>
                </div>

                <div class="operation-lifecycle-actions">
                    @if ($puedeGestionarEstado && $orden->estaAbierta())
                        <form
                            method="POST"
                            action="{{ route('ordenes-operacion.iniciar', $orden->id) }}"
                            data-loading-form
                        >
                            @csrf
                            @method('PATCH')

                            <button
                                class="button button--primary button--block"
                                data-submit-button
                                data-loading-text="Activando orden..."
                                data-confirm="¿Activar esta orden? Se congelará la previsión actual y se reservarán automáticamente los materiales requeridos. El stock físico no cambiará."
                            >
                                <span data-submit-icon>
                                    <x-ui.icon name="activity" :size="17" />
                                </span>
                                <span
                                    class="button-spinner"
                                    data-submit-spinner
                                    hidden
                                ></span>
                                <span data-submit-label>Activar orden</span>
                            </button>
                        </form>
                    @endif

                    @if ($puedeGestionarEstado && $orden->estaEnProceso())
                        @if ($puedeCerrarOperacion)
                            <form
                                method="POST"
                                action="{{ route('ordenes-operacion.cerrar', $orden->id) }}"
                                data-loading-form
                            >
                                @csrf
                                @method('PATCH')

                                <label class="form-field">
                                    <span>Observación final (opcional)</span>
                                    <textarea name="observacion_cierre" rows="3" maxlength="500" placeholder="Resultado, pruebas o entrega realizada">{{ old('observacion_cierre') }}</textarea>
                                    @error('observacion_cierre')<small class="field-error">{{ $message }}</small>@enderror
                                </label>

                                <button
                                    class="button button--ghost button--block"
                                    data-submit-button
                                    data-loading-text="Cerrando orden..."
                                    data-confirm="¿Cerrar esta orden? Se congelarán el costo real y la rentabilidad, y luego quedará como solo lectura."
                                >
                                    <span data-submit-icon>
                                        <x-ui.icon name="check-circle" :size="17" />
                                    </span>
                                    <span class="button-spinner" data-submit-spinner hidden></span>
                                    <span data-submit-label>Cerrar orden</span>
                                </button>
                            </form>
                        @else
                            <div class="notice notice--warning notice--block">
                                <x-ui.icon name="warning" :size="18" />
                                <span>
                                    Para cerrar:
                                    @if (! $avanceCompleto)
                                        registra el avance operativo en 100%.
                                    @endif
                                    @if (! $sinHerramientasPendientes)
                                        Confirma la devolución de las herramientas pendientes.
                                    @endif
                                </span>
                            </div>
                        @endif

                        @error('cierre')
                            <div class="notice notice--danger notice--block">
                                <x-ui.icon name="error" :size="18" />
                                <span>{{ $message }}</span>
                            </div>
                        @enderror

                        @if ($puedeAnularOrden)
                            <button
                                type="button"
                                class="button button--danger button--block"
                                data-open-order-cancel
                            >
                                <x-ui.icon name="error" :size="17" />
                                Anular orden
                            </button>
                        @endif
                    @endif
                </div>

                @if ($orden->estaCerrada())
                    <div class="notice notice--success notice--block">
                        <x-ui.icon name="check-circle" :size="18" />
                        <span>
                            La orden está cerrada y conserva su historial como solo lectura.
                            El resultado económico quedó congelado al momento del cierre.
                        </span>
                    </div>
                @endif
            </aside>
        </section>

