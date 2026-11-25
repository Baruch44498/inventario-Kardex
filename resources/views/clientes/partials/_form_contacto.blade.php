    <section class="form-section">
        <div class="form-section__heading">
            <span class="form-section__icon">
                <x-ui.icon name="phone" :size="20" />
            </span>
            <div>
                <p class="eyebrow">Comunicación</p>
                <h2>Datos de contacto</h2>
            </div>
        </div>

        <div class="form-grid client-form-grid">
            <div class="form-field">
                <label for="contacto">Persona de contacto</label>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="user" :size="17" />
                    </span>
                    <input
                        id="contacto"
                        name="contacto"
                        type="text"
                        value="{{ old(
                            'contacto',
                            $cliente->contacto ?? ''
                        ) }}"
                        maxlength="150"
                        placeholder="Nombre del contacto"
                    >
                </div>
                @error('contacto')
                    <small class="field-error">
                        {{ $message }}
                    </small>
                @enderror
            </div>

            <div class="form-field">
                <label for="telefono">Teléfono</label>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="phone" :size="17" />
                    </span>
                    <input
                        id="telefono"
                        name="telefono"
                        type="text"
                        value="{{ old(
                            'telefono',
                            $cliente->telefono ?? ''
                        ) }}"
                        maxlength="9"
                        inputmode="numeric"
                        pattern="[0-9]{1,9}"
                        autocomplete="tel"
                        placeholder="987654321"
                        title="Ingresa únicamente números, hasta 9 dígitos"
                        data-digits-only="9"
                    >
                </div>
                <small>Solo números, máximo 9 dígitos.</small>
                @error('telefono')
                    <small class="field-error">
                        {{ $message }}
                    </small>
                @enderror
            </div>

            <div class="form-field">
                <label for="correo">Correo</label>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="mail" :size="17" />
                    </span>
                    <input
                        id="correo"
                        name="correo"
                        type="email"
                        value="{{ old(
                            'correo',
                            $cliente->correo ?? ''
                        ) }}"
                        maxlength="150"
                        inputmode="email"
                        autocomplete="email"
                        placeholder="cliente@empresa.com"
                        title="Ingresa un correo que incluya @ y un dominio"
                    >
                </div>
                <small>Debe incluir @ y un dominio, por ejemplo: cliente@empresa.com.</small>
                @error('correo')
                    <small class="field-error">
                        {{ $message }}
                    </small>
                @enderror
            </div>

            <div class="form-field">
                <span>Estado</span>

                @if ($protegido)
                    <input type="hidden" name="estado" value="1">
                @endif

                <label class="switch-field">
                    <input type="hidden" name="estado" value="0">
                    <input
                        type="checkbox"
                        name="estado"
                        value="1"
                        @checked(
                            (bool) old(
                                'estado',
                                $cliente->estado ?? true
                            )
                        )
                        @disabled($protegido)
                    >
                    <span class="switch-control"></span>
                    <span>Cliente activo</span>
                </label>

                @if ($protegido)
                    <small>
                        Este registro permanece activo por diseño.
                    </small>
                @else
                    <small>
                        Los clientes inactivos no aparecerán en nuevas
                        operaciones.
                    </small>
                @endif
            </div>
        </div>
    </section>
