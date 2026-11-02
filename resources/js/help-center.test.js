import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { initHelpCenter } from './help-center.js';

/**
 * Coverage for the "?" topbar popover. Builds the exact `data-help-*`
 * markup help-center.blade.php renders, so a renamed attribute here
 * fails loudly instead of silently drifting from the template.
 */

function buildHelpCenter() {
    document.body.innerHTML = `
        <div data-help-center>
            <button type="button" data-help-toggle aria-haspopup="true" aria-expanded="false" aria-label="Centro de ayuda">
                <span class="material-symbols-outlined">help_outline</span>
            </button>
            <div class="help-center-panel" data-help-panel role="menu">
                <button type="button" class="help-center-item" data-help-action="start-tour">Ver tutorial de Fiscora</button>
                <button type="button" class="help-center-item" data-help-action="show-checklist">Primeros pasos</button>
                <button type="button" class="help-center-item" data-help-action="focus-assistant">Cómo usar el Asistente IA</button>
                <button type="button" class="help-center-item" data-help-action="toggle-about" aria-expanded="false">Acerca del prototipo</button>
                <p class="help-center-about" data-help-about>Texto del prototipo.</p>
            </div>
        </div>
    `;

    return {
        toggle: document.querySelector('[data-help-toggle]'),
        panel: document.querySelector('[data-help-panel]'),
    };
}

describe('HelpCenter', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('opens the panel on click and sets aria-expanded', () => {
        const { toggle, panel } = buildHelpCenter();
        initHelpCenter();

        toggle.click();

        expect(panel.classList.contains('open')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');
    });

    it('closes when clicking the toggle again', () => {
        const { toggle, panel } = buildHelpCenter();
        initHelpCenter();

        toggle.click();
        toggle.click();

        expect(panel.classList.contains('open')).toBe(false);
    });

    it('closes when clicking outside the panel', () => {
        const { toggle, panel } = buildHelpCenter();
        initHelpCenter();

        toggle.click();
        document.body.click();

        expect(panel.classList.contains('open')).toBe(false);
    });

    it('closes on Escape and refocuses the toggle button', () => {
        const { toggle, panel } = buildHelpCenter();
        initHelpCenter();

        toggle.click();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(panel.classList.contains('open')).toBe(false);
        expect(document.activeElement).toBe(toggle);
    });

    it('"Ver tutorial" dispatches the real tour-start event and closes the panel', () => {
        const { toggle, panel } = buildHelpCenter();
        initHelpCenter();
        toggle.click();

        let received = false;
        window.addEventListener('fiscora:tour:start', () => {
            received = true;
        });

        panel.querySelector('[data-help-action="start-tour"]').click();

        expect(received).toBe(true);
        expect(panel.classList.contains('open')).toBe(false);
    });

    it('"Primeros pasos" dispatches the real checklist-show event', () => {
        const { toggle, panel } = buildHelpCenter();
        initHelpCenter();
        toggle.click();

        let received = false;
        window.addEventListener('fiscora:checklist:show', () => {
            received = true;
        });

        panel.querySelector('[data-help-action="show-checklist"]').click();

        expect(received).toBe(true);
    });

    it('"Acerca del prototipo" reveals the disclosure text in place without navigating', () => {
        const { panel } = buildHelpCenter();
        initHelpCenter();

        const toggleAbout = panel.querySelector('[data-help-action="toggle-about"]');
        const about = panel.querySelector('[data-help-about]');

        toggleAbout.click();

        expect(about.classList.contains('open')).toBe(true);
        expect(toggleAbout.getAttribute('aria-expanded')).toBe('true');
    });

    it('never throws when there is no help center on the page', () => {
        document.body.innerHTML = '<div>Sin centro de ayuda</div>';

        expect(() => initHelpCenter()).not.toThrow();
    });

    it('never throws when initialized more than once', () => {
        const { toggle } = buildHelpCenter();

        expect(() => {
            initHelpCenter();
            initHelpCenter();
        }).not.toThrow();

        expect(() => toggle.click()).not.toThrow();
    });
});
