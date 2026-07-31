@php
    $editando = isset($cliente);
    $protegido = (bool) ($cliente->es_mostrador ?? false);
    $tipoActual = old(
        'tipo_documento',
        $cliente->tipo_documento ?? 'RUC'
    );
@endphp

<div class="client-form-sections">
    <section class="form-section">
        <div class="form-section__heading">
            <span class="form-section__icon">
                <x-ui.icon name="id-card" :size="20" />
            </span>
            <div>
                <p class="eyebrow">Identificación</p>
                <h2>Datos del cliente</h2>
            </div>
        </div>

        @if ($protegido)
            <div class="client-system-record">
                <span>
                    <x-ui.icon name="lock" :size="22" />
                </span>
                <div>
                    <strong>Registro protegido del sistema</strong>
                    <p>
                        PÚBLICO GENERAL se utiliza para ventas rápidas.
                        Su identificación y estado no pueden modificarse.
                    </p>
                </div>
            </div>

            <input
                type="hidden"
                name="tipo_cliente_id"
                value="{{ $cliente->tipo_cliente_id }}"
            >
            <input
                type="hidden"
                name="tipo_documento"
                value="SIN_DOCUMENTO"
            >
            <input type="hidden" name="numero_documento" value="">
            <input type="hidden" name="nombres" value="PÚBLICO GENERAL">
            <input
                type="hidden"
                name="razon_social"
                value="PÚBLICO GENERAL"
            >
            <input
                type="hidden"
                name="nombre_comercial"
                value="Venta directa"
            >
        @else
            <div class="form-grid client-form-grid">
                <div class="form-field">
                    <label for="tipo_cliente_id">
                        Tipo de cliente
                        <span class="required-mark">*</span>
                    </label>
                    <select
                        id="tipo_cliente_id"
                        name="tipo_cliente_id"
                        required
                    >
                        <option value="">Selecciona un tipo</option>
                        @foreach ($tipos as $tipo)
                            <option
                                value="{{ $tipo->id }}"
                                @selected(
                                    (int) old(
                                        'tipo_cliente_id',
                                        $cliente->tipo_cliente_id ?? 0
                                    ) === $tipo->id
                                )
                            >
                                {{ $tipo->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('tipo_cliente_id')
                        <small class="field-error">
                            {{ $message }}
                        </small>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="tipo_documento">
                        Tipo de documento
                        <span class="required-mark">*</span>
                    </label>
                    <select
                        id="tipo_documento"
                        name="tipo_documento"
                        required
                        data-client-document-type
                    >
                        @foreach ([
                            'RUC' => 'RUC',
                            'DNI' => 'DNI',
                            'CE' => 'Carné de extranjería',
                            'SIN_DOCUMENTO' => 'Sin documento',
                        ] as $valor => $nombre)
                            <option
                                value="{{ $valor }}"
                                @selected($tipoActual === $valor)
                            >
                                {{ $nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('tipo_documento')
                        <small class="field-error">
                            {{ $message }}
                        </small>
                    @enderror
                </div>

                <div class="form-field">
                    <label for="numero_documento">
                        Número de documento
                        <span
                            class="required-mark"
                            data-client-document-required
                        >*</span>
                    </label>

                    <div class="input-with-icon">
                        <span class="input-with-icon__symbol">
                            <x-ui.icon name="hash" :size="17" />
                        </span>
                        <input
                            id="numero_documento"
                            name="numero_documento"
                            type="text"
                            value="{{ old(
                                'numero_documento',
                                $cliente->numero_documento
                                    ?? $cliente->ruc
                                    ?? ''
                            ) }}"
                            maxlength="12"
                            autocomplete="off"
                            data-client-document-number
                        >
                    </div>

                    <small data-client-document-help>
                        El RUC debe contener 11 dígitos.
                    </small>

                    @error('numero_documento')
                        <small class="field-error">
                            {{ $message }}
                        </small>
                    @enderror
                </div>

                <div
                    class="form-field"
                    data-client-ruc-field
                >
                    <label for="razon_social">
                        Razón social
                        <span class="required-mark">*</span>
                    </label>
                    <div class="input-with-icon">
                        <span class="input-with-icon__symbol">
                            <x-ui.icon name="suppliers" :size="17" />
                        </span>
                        <input
                            id="razon_social"
                            name="razon_social"
                            type="text"
                            value="{{ old(
                                'razon_social',
                                $cliente->razon_social ?? ''
                            ) }}"
                            maxlength="250"
                            placeholder="Nombre legal de la empresa"
                            data-client-ruc-input
                        >
                    </div>
                    @error('razon_social')
                        <small class="field-error">
                            {{ $message }}
                        </small>
                    @enderror
                </div>

                <div
                    class="form-field"
                    data-client-person-field
                >
                    <label
                        for="nombres"
                        data-client-person-label
                    >
                        Nombres
                        <span class="required-mark">*</span>
                    </label>

                    <div class="input-with-icon">
                        <span class="input-with-icon__symbol">
                            <x-ui.icon name="user" :size="17" />
                        </span>
                        <input
                            id="nombres"
                            name="nombres"
                            type="text"
                            value="{{ old(
                                'nombres',
                                $cliente->nombres ?? ''
                            ) }}"
                            maxlength="250"
                            data-client-person-input
                        >
                    </div>

                    <small data-client-person-help>
                        Ingresa únicamente los nombres.
                    </small>

                    @error('nombres')
                        <small class="field-error">
                            {{ $message }}
                        </small>
                    @enderror
                </div>

                <div
                    class="form-field"
                    data-client-dni-field
                >
                    <label for="apellido_paterno">
                        Apellido paterno
                        <span class="required-mark">*</span>
                    </label>
                    <input
                        id="apellido_paterno"
                        name="apellido_paterno"
                        type="text"
                        value="{{ old(
                            'apellido_paterno',
                            $cliente->apellido_paterno ?? ''
                        ) }}"
                        maxlength="100"
                        data-client-dni-input
                    >
                    @error('apellido_paterno')
                        <small class="field-error">
                            {{ $message }}
                        </small>
                    @enderror
                </div>

                <div
                    class="form-field"
                    data-client-dni-field
                >
                    <label for="apellido_materno">
                        Apellido materno
                        <span class="required-mark">*</span>
                    </label>
                    <input
                        id="apellido_materno"
                        name="apellido_materno"
                        type="text"
                        value="{{ old(
                            'apellido_materno',
                            $cliente->apellido_materno ?? ''
                        ) }}"
                        maxlength="100"
                        data-client-dni-input
                    >
                    @error('apellido_materno')
                        <small class="field-error">
                            {{ $message }}
                        </small>
                    @enderror
                </div>

                <div
                    class="form-field form-field--wide"
                    data-client-ruc-field
                >
                    <label for="nombre_comercial">
                        Nombre comercial
                    </label>
                    <input
                        id="nombre_comercial"
                        name="nombre_comercial"
                        type="text"
                        value="{{ old(
                            'nombre_comercial',
                            $cliente->nombre_comercial ?? ''
                        ) }}"
                        maxlength="250"
                        placeholder="Nombre utilizado comercialmente"
                        data-client-ruc-input
                    >
                    @error('nombre_comercial')
                        <small class="field-error">
                            {{ $message }}
                        </small>
                    @enderror
                </div>
            </div>
        @endif
    </section>

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
                        oninput="this.value = this.value.replace(/\D/g, '').slice(0, 9)"
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
</div>

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

@unless ($protegido)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const type = document.querySelector(
                    '[data-client-document-type]'
                );
                const number = document.querySelector(
                    '[data-client-document-number]'
                );
                const numberRequired = document.querySelector(
                    '[data-client-document-required]'
                );
                const numberHelp = document.querySelector(
                    '[data-client-document-help]'
                );
                const rucFields = document.querySelectorAll(
                    '[data-client-ruc-field]'
                );
                const rucInputs = document.querySelectorAll(
                    '[data-client-ruc-input]'
                );
                const personField = document.querySelector(
                    '[data-client-person-field]'
                );
                const personInput = document.querySelector(
                    '[data-client-person-input]'
                );
                const personLabel = document.querySelector(
                    '[data-client-person-label]'
                );
                const personHelp = document.querySelector(
                    '[data-client-person-help]'
                );
                const dniFields = document.querySelectorAll(
                    '[data-client-dni-field]'
                );
                const dniInputs = document.querySelectorAll(
                    '[data-client-dni-input]'
                );

                const toggleGroup = (
                    elements,
                    inputs,
                    visible
                ) => {
                    elements.forEach((element) => {
                        element.hidden = !visible;
                    });

                    inputs.forEach((input) => {
                        input.disabled = !visible;
                    });
                };

                const syncDocument = () => {
                    const documentType = type?.value || 'RUC';
                    const isRuc = documentType === 'RUC';
                    const isDni = documentType === 'DNI';
                    const isCe = documentType === 'CE';
                    const withoutDocument =
                        documentType === 'SIN_DOCUMENTO';

                    toggleGroup(
                        rucFields,
                        rucInputs,
                        isRuc
                    );

                    if (personField && personInput) {
                        personField.hidden = isRuc;
                        personInput.disabled = isRuc;
                        personInput.required = !isRuc;
                    }

                    toggleGroup(
                        dniFields,
                        dniInputs,
                        isDni
                    );

                    dniInputs.forEach((input) => {
                        input.required = isDni;
                    });

                    if (number) {
                        number.disabled = withoutDocument;
                        number.required = !withoutDocument;

                        if (withoutDocument) {
                            number.value = '';
                        }

                        if (isRuc) {
                            number.maxLength = 11;
                            number.inputMode = 'numeric';
                            number.placeholder = '20123456789';
                        } else if (isDni) {
                            number.maxLength = 8;
                            number.inputMode = 'numeric';
                            number.placeholder = '12345678';
                        } else if (isCe) {
                            number.maxLength = 12;
                            number.inputMode = 'text';
                            number.placeholder = 'ABC123456';
                        } else {
                            number.maxLength = 12;
                            number.inputMode = 'text';
                            number.placeholder = '';
                        }
                    }

                    if (numberRequired) {
                        numberRequired.hidden = withoutDocument;
                    }

                    if (numberHelp) {
                        numberHelp.textContent = isRuc
                            ? 'El RUC debe contener exactamente 11 dígitos.'
                            : isDni
                                ? 'El DNI debe contener exactamente 8 dígitos.'
                                : isCe
                                    ? 'Usa entre 9 y 12 caracteres alfanuméricos.'
                                    : 'No se registrará un número de documento.';
                    }

                    if (personLabel) {
                        personLabel.firstChild.textContent = isDni
                            ? 'Nombres '
                            : isCe
                                ? 'Nombres y apellidos completos '
                                : 'Nombre del cliente ';
                    }

                    if (personHelp) {
                        personHelp.textContent = isDni
                            ? 'Ingresa únicamente los nombres.'
                            : isCe
                                ? 'Usa un solo campo porque la estructura del nombre puede variar.'
                                : 'Puedes utilizar PÚBLICO GENERAL o CLIENTES VARIOS.';
                    }

                    if (
                        withoutDocument
                        && personInput
                        && !personInput.value.trim()
                    ) {
                        personInput.value = 'PÚBLICO GENERAL';
                    }
                };

                type?.addEventListener(
                    'change',
                    syncDocument
                );

                syncDocument();
            });
        </script>
    @endpush
@endunless
