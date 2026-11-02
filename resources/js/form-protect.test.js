import { describe, expect, it } from 'vitest';
import { initSubmitProtection } from './form-protect.js';

/**
 * Generic double-submit guard used by the register and OTP forms (and
 * reusable by any future plain form that opts in via
 * `data-protect-submit`).
 */

function buildForm() {
    document.body.innerHTML = `
        <form data-protect-submit>
            <button type="submit"><span data-submit-label>Enviar</span></button>
        </form>
    `;

    return document.querySelector('form');
}

describe('initSubmitProtection', () => {
    it('disables the submit button and swaps its label on submit', () => {
        const form = buildForm();
        initSubmitProtection();

        form.dispatchEvent(new Event('submit'));

        const button = form.querySelector('button');
        expect(button.disabled).toBe(true);
        expect(button.querySelector('[data-submit-label]').textContent).toBe('Procesando…');
    });

    it('is idempotent — calling init twice never attaches a second listener', () => {
        const form = buildForm();
        initSubmitProtection();
        initSubmitProtection();

        form.dispatchEvent(new Event('submit'));

        expect(form.querySelector('button').disabled).toBe(true);
    });

    it('does nothing for forms without the opt-in attribute', () => {
        document.body.innerHTML = `
            <form>
                <button type="submit"><span data-submit-label>Enviar</span></button>
            </form>
        `;
        initSubmitProtection();

        const form = document.querySelector('form');
        form.dispatchEvent(new Event('submit'));

        expect(form.querySelector('button').disabled).toBe(false);
    });
});
