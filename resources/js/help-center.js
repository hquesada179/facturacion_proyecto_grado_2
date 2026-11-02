/**
 * Help center popover — same open/close/Escape/click-outside pattern as
 * notifications.js's bell dropdown, just toggling the `.open` class this
 * component's own CSS expects instead of Tailwind's `hidden` utility.
 *
 * Every action here either starts the real product tour (tour.js),
 * restores the real checklist (checklist.js), or focuses the real
 * assistant panel already rendered on the page — nothing here invents a
 * parallel assistant or a new page.
 */

function closePanel(toggle, panel) {
    panel.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
}

function openPanel(toggle, panel) {
    panel.classList.add('open');
    toggle.setAttribute('aria-expanded', 'true');
}

function focusAssistantPanel() {
    const panel = document.querySelector('[data-assistant-panel]');

    if (!panel) {
        return;
    }

    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });

    const input = panel.querySelector('[data-assistant-input]');
    input?.focus();
}

export function initHelpCenter() {
    const roots = Array.from(document.querySelectorAll('[data-help-center]'));

    if (roots.length === 0) {
        return;
    }

    roots.forEach((root) => {
        if (root.dataset.helpCenterInitialized === 'true') {
            return;
        }

        root.dataset.helpCenterInitialized = 'true';

        const toggle = root.querySelector('[data-help-toggle]');
        const panel = root.querySelector('[data-help-panel]');

        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';

            if (isOpen) {
                closePanel(toggle, panel);
            } else {
                openPanel(toggle, panel);
            }
        });

        panel.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        panel.querySelectorAll('[data-help-action]').forEach((item) => {
            item.addEventListener('click', () => {
                const action = item.dataset.helpAction;

                if (action === 'start-tour') {
                    window.dispatchEvent(new CustomEvent('fiscora:tour:start'));
                    closePanel(toggle, panel);
                } else if (action === 'show-checklist') {
                    window.dispatchEvent(new CustomEvent('fiscora:checklist:show'));
                    closePanel(toggle, panel);
                } else if (action === 'focus-assistant') {
                    focusAssistantPanel();
                    closePanel(toggle, panel);
                } else if (action === 'toggle-about') {
                    const about = panel.querySelector('[data-help-about]');
                    const expanded = item.getAttribute('aria-expanded') === 'true';
                    item.setAttribute('aria-expanded', String(!expanded));
                    about?.classList.toggle('open', !expanded);
                }
            });
        });
    });

    document.addEventListener('click', () => {
        roots.forEach((root) => {
            const toggle = root.querySelector('[data-help-toggle]');
            const panel = root.querySelector('[data-help-panel]');

            if (toggle && panel) {
                closePanel(toggle, panel);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        roots.forEach((root) => {
            const toggle = root.querySelector('[data-help-toggle]');
            const panel = root.querySelector('[data-help-panel]');

            if (toggle && panel && toggle.getAttribute('aria-expanded') === 'true') {
                closePanel(toggle, panel);
                toggle.focus();
            }
        });
    });
}
