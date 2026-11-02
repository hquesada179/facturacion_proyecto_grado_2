import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initThemeToggle } from './theme.js';

/**
 * UI/UX 2.0-C coverage for the light/dark toggle. Builds the exact
 * `data-theme-toggle` / `data-theme-icon` markup topbar.blade.php renders,
 * so a renamed attribute here fails loudly instead of silently drifting
 * from the template.
 */

function buildToggle() {
    document.body.innerHTML = `
        <button type="button" data-theme-toggle aria-pressed="false" aria-label="Cambiar a modo oscuro">
            <span class="material-symbols-outlined" data-theme-icon>dark_mode</span>
        </button>
    `;

    return {
        button: document.querySelector('[data-theme-toggle]'),
        icon: document.querySelector('[data-theme-icon]'),
    };
}

function mockSystemPrefersDark(matches) {
    window.matchMedia = vi.fn().mockReturnValue({ matches });
}

describe('ThemeToggle', () => {
    beforeEach(() => {
        window.localStorage.clear();
        delete document.documentElement.dataset.theme;
        window.matchMedia = undefined;
    });

    afterEach(() => {
        document.body.innerHTML = '';
        delete document.documentElement.dataset.theme;
        vi.restoreAllMocks();
    });

    it('defaults to light when there is no stored preference and no system preference', () => {
        buildToggle();
        initThemeToggle();

        expect(document.documentElement.dataset.theme).toBe('light');
    });

    it('respects the system preference on first visit when dark is preferred', () => {
        mockSystemPrefersDark(true);
        buildToggle();
        initThemeToggle();

        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('an explicit stored preference overrides the system preference', () => {
        mockSystemPrefersDark(true);
        window.localStorage.setItem('fiscora:theme', 'light');
        buildToggle();
        initThemeToggle();

        expect(document.documentElement.dataset.theme).toBe('light');
    });

    it('toggles the theme, the icon and aria state on click', () => {
        const { button, icon } = buildToggle();
        initThemeToggle();

        expect(document.documentElement.dataset.theme).toBe('light');
        expect(icon.textContent).toBe('dark_mode');
        expect(button.getAttribute('aria-pressed')).toBe('false');

        button.click();

        expect(document.documentElement.dataset.theme).toBe('dark');
        expect(icon.textContent).toBe('light_mode');
        expect(button.getAttribute('aria-pressed')).toBe('true');
        expect(button.getAttribute('aria-label')).toBe('Cambiar a modo claro');

        button.click();

        expect(document.documentElement.dataset.theme).toBe('light');
        expect(icon.textContent).toBe('dark_mode');
        expect(button.getAttribute('aria-pressed')).toBe('false');
    });

    it('persists the choice across a simulated reload', () => {
        const { button } = buildToggle();
        initThemeToggle();

        button.click();
        expect(window.localStorage.getItem('fiscora:theme')).toBe('dark');

        document.body.innerHTML = '';
        delete document.documentElement.dataset.theme;
        buildToggle();
        initThemeToggle();

        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('keeps every toggle button on the page in sync (desktop + mobile)', () => {
        document.body.innerHTML = `
            <button type="button" data-theme-toggle aria-pressed="false" aria-label="Cambiar a modo oscuro">
                <span class="material-symbols-outlined" data-theme-icon>dark_mode</span>
            </button>
            <button type="button" data-theme-toggle aria-pressed="false" aria-label="Cambiar a modo oscuro">
                <span class="material-symbols-outlined" data-theme-icon>dark_mode</span>
            </button>
        `;

        const [desktopButton, mobileButton] = document.querySelectorAll('[data-theme-toggle]');
        initThemeToggle();

        desktopButton.click();

        expect(mobileButton.getAttribute('aria-pressed')).toBe('true');
        expect(mobileButton.querySelector('[data-theme-icon]').textContent).toBe('light_mode');
    });

    it('never throws and keeps working when initialized more than once', () => {
        const { button } = buildToggle();

        expect(() => {
            initThemeToggle();
            initThemeToggle();
        }).not.toThrow();

        button.click();

        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('does not break when there is no toggle button on the page', () => {
        document.body.innerHTML = '<div>Sin campana ni toggle</div>';

        expect(() => initThemeToggle()).not.toThrow();
        expect(document.documentElement.dataset.theme).toBe('light');
    });
});
