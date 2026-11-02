/**
 * Generic double-submit guard for plain (non-AJAX) forms: disables the
 * submit button and swaps its label to a busy state the instant the
 * form is submitted, so a double click/tap can't fire two POSTs while
 * the page is still navigating away. Pure progressive enhancement —
 * the form still posts normally either way, and a full-page reload
 * (validation error, redirect) naturally resets the button on its own.
 */

const BUSY_LABEL = 'Procesando…';

export function initSubmitProtection() {
    document.querySelectorAll('[data-protect-submit]').forEach((form) => {
        if (form.dataset.submitProtected === 'true') {
            return;
        }

        form.dataset.submitProtected = 'true';

        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;

            const label = button.querySelector('[data-submit-label]');

            if (label) {
                label.textContent = BUSY_LABEL;
            }
        });
    });
}
