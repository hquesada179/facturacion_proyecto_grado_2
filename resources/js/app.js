import { initAssistantPanels } from './assistant.js';

document.addEventListener('DOMContentLoaded', () => {
    initAssistantPanels();

    document.querySelectorAll('[data-demo-submit]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const target = document.getElementById(form.dataset.demoSubmit);

            if (target) {
                target.classList.remove('hidden');
                target.classList.add('flex');
            }
        });
    });

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            const icon = button.querySelector('.material-symbols-outlined');

            if (! input) {
                return;
            }

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            if (icon) {
                icon.textContent = isHidden ? 'visibility_off' : 'visibility';
            }
        });
    });
});
