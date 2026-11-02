/**
 * UI/UX 2.0-C — light/dark toggle. The actual repaint is pure CSS (see
 * app.css's `:root[data-theme='dark']` token overrides); this module only
 * ever decides WHICH theme applies and keeps every `[data-theme-toggle]`
 * button in sync with it. The very first paint is handled by an inline
 * <script> in the <head> (see components/layouts/app.blade.php) so there
 * is no flash of the wrong theme before this module loads — the logic
 * here mirrors that script exactly so both agree on the same theme.
 */

const STORAGE_KEY = 'fiscora:theme';

function systemPrefersDark() {
    try {
        return typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-color-scheme: dark)').matches;
    } catch (error) {
        return false;
    }
}

function readStoredTheme() {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return stored === 'light' || stored === 'dark' ? stored : null;
    } catch (error) {
        return null;
    }
}

function writeStoredTheme(theme) {
    try {
        window.localStorage.setItem(STORAGE_KEY, theme);
    } catch (error) {
        // Private browsing / storage disabled — the theme still applies
        // for this page load, it just won't persist across visits.
    }
}

function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    const isDark = theme === 'dark';

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const icon = button.querySelector('[data-theme-icon]');

        if (icon) {
            icon.textContent = isDark ? 'light_mode' : 'dark_mode';
        }

        button.setAttribute('aria-pressed', String(isDark));
        button.setAttribute('aria-label', isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
    });
}

export function initThemeToggle() {
    const current = document.documentElement.dataset.theme;

    if (current !== 'light' && current !== 'dark') {
        document.documentElement.dataset.theme = readStoredTheme() ?? (systemPrefersDark() ? 'dark' : 'light');
    }

    applyTheme(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light');

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        if (button.dataset.themeInitialized === 'true') {
            return;
        }

        button.dataset.themeInitialized = 'true';

        button.addEventListener('click', () => {
            const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            writeStoredTheme(next);
            applyTheme(next);
        });
    });
}
