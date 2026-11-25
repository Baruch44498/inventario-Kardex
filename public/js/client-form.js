(() => {
    'use strict';

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
})();
