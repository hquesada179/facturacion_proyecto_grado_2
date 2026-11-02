/**
 * UI/UX 3.1 — desktop/tablet sidebar collapse. Mirrors theme.js exactly:
 * a single localStorage key, read/write with the same try/catch fallback,
 * and a pure attribute toggle on <html> that resources/css/app.css reads
 * to resize the sidebar/content column (see the "SIDEBAR COLLAPSE" rules
 * there). The first paint is handled by an inline <script> in
 * components/layouts/app.blade.php's <head>, same reasoning as the
 * theme script right next to it — this module only has to agree with
 * that script on the same storage key and keep every
 * [data-sidebar-collapse-toggle] button's icon/label in sync afterward.
 *
 * Mobile is untouched: the toggle button is `hidden md:flex` in markup,
 * so it can never be clicked below the md breakpoint, and the CSS rules
 * this drives are themselves scoped to `min-width: 768px` — resizing
 * down to mobile with the sidebar collapsed still shows the full
 * off-canvas drawer with labels, because that's a separate
 * [data-sidebar-open] mechanism this never reads or writes.
 */

const STORAGE_KEY = 'fiscora.sidebar.collapsed';

function writeStoredCollapsed(collapsed) {
    try {
        window.localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
    } catch (error) {
        // Private browsing / storage disabled — the collapsed state still
        // applies for this page load, it just won't persist across visits
        // or screens, same documented fallback as the theme toggle.
    }
}

function applyToggleButtons(collapsed) {
    document.querySelectorAll('[data-sidebar-collapse-toggle]').forEach((button) => {
        const icon = button.querySelector('[data-sidebar-collapse-icon]');

        if (icon) {
            icon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
        }

        const label = collapsed ? 'Expandir barra lateral' : 'Colapsar barra lateral';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
    });
}

export function initSidebarCollapse() {
    // The inline <head> script already set the attribute before first
    // paint when the stored value was 'true' — read it back from the DOM
    // (not localStorage again) so this stays in sync even if storage
    // was unavailable at that point but became readable later.
    applyToggleButtons(document.documentElement.hasAttribute('data-sidebar-collapsed'));

    document.querySelectorAll('[data-sidebar-collapse-toggle]').forEach((button) => {
        if (button.dataset.sidebarCollapseInitialized === 'true') {
            return;
        }

        button.dataset.sidebarCollapseInitialized = 'true';

        button.addEventListener('click', () => {
            const next = !document.documentElement.hasAttribute('data-sidebar-collapsed');
            document.documentElement.toggleAttribute('data-sidebar-collapsed', next);
            writeStoredCollapsed(next);
            applyToggleButtons(next);
        });
    });
}
