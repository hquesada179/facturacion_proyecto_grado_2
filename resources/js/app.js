import { initAssistantPanels } from './assistant.js';
import { initNotificationCenter } from './notifications.js';
import { initThemeToggle } from './theme.js';
import { initLandingNav, initLandingReveals, initLandingTilt } from './landing.js';

// Progressive enhancement flag for the public landing's scroll-reveal
// (see app.css's `.js [data-reveal]` rule) — set as early as this module
// runs, so content is never hidden if JS fails to load at all.
document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', () => {
    initAssistantPanels();
    initNotificationCenter();
    initThemeToggle();
    initLandingReveals();
    initLandingNav();
    initLandingTilt();

    // UI/UX 2.0-A: mobile sidebar drawer — purely presentational, toggles
    // a single attribute that resources/css/app.css reads to slide the
    // existing <nav> on/off screen. No route, permission, or link below
    // it ever changes.
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarBackdrops = document.querySelectorAll('[data-sidebar-backdrop]');

    sidebarToggle?.addEventListener('click', () => {
        document.documentElement.toggleAttribute('data-sidebar-open');
    });

    sidebarBackdrops.forEach((element) => {
        element.addEventListener('click', () => {
            document.documentElement.removeAttribute('data-sidebar-open');
        });
    });

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
