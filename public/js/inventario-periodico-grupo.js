document.querySelectorAll('[data-select-periodic-shelves]').forEach((button) => {
    button.addEventListener('click', () => {
        const inputs = button.closest('form').querySelectorAll('input[name="repisa_ids[]"]');
        const select = Array.from(inputs).some((input) => !input.checked);
        inputs.forEach((input) => { input.checked = select; });
        button.textContent = select ? 'Quitar selección' : 'Seleccionar todas';
    });
});
