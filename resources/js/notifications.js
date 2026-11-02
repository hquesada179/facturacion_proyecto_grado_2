/**
 * UI/UX 2.0-C — bell dropdown behaviour. The server renders the full
 * notification list on every page load (see NotificationCenterService);
 * this module only ever: toggles the panel open/closed, and tracks a
 * "seen at" timestamp in localStorage so the badge/unread dots are
 * visually accurate without any new database column. Nothing here
 * fabricates notification data.
 */

const SEEN_AT_PREFIX = 'fiscora:notifications:seen:';

function storageKeyFor(userId) {
    return `${SEEN_AT_PREFIX}${userId || 'anon'}`;
}

function readSeenAt(userId) {
    const raw = window.localStorage.getItem(storageKeyFor(userId));
    const parsed = raw ? Number(raw) : 0;

    return Number.isFinite(parsed) ? parsed : 0;
}

function writeSeenAt(userId, timestamp) {
    window.localStorage.setItem(storageKeyFor(userId), String(timestamp));
}

function closePanel(toggle, panel) {
    panel.classList.add('hidden');
    toggle.setAttribute('aria-expanded', 'false');
}

function openPanel(toggle, panel) {
    panel.classList.remove('hidden');
    toggle.setAttribute('aria-expanded', 'true');
}

function refreshUnreadState(root) {
    const userId = root.dataset.userId || '';
    const seenAt = readSeenAt(userId);
    const items = root.querySelectorAll('[data-notification-item]');
    let unread = 0;

    items.forEach((item) => {
        const timestamp = Date.parse(item.dataset.timestamp || '');
        const isUnread = Number.isFinite(timestamp) && timestamp > seenAt;
        const dot = item.querySelector('[data-notification-unread-dot]');

        if (dot) {
            dot.hidden = !isUnread;
        }

        if (isUnread) {
            unread += 1;
        }
    });

    const badge = root.querySelector('[data-notification-badge]');

    if (!badge) {
        return;
    }

    if (unread > 0) {
        badge.textContent = unread > 9 ? '9+' : String(unread);
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

export function initNotificationCenter() {
    const roots = Array.from(document.querySelectorAll('[data-notification-center]'));

    if (roots.length === 0) {
        return;
    }

    const refreshAll = () => roots.forEach((root) => refreshUnreadState(root));

    roots.forEach((root) => {
        // Guards against double-binding if initNotificationCenter() is ever
        // called more than once for the same page (e.g. a stray second
        // DOMContentLoaded listener) — without this, a single click would
        // open then immediately re-close the panel via duplicate handlers.
        if (root.dataset.notificationInitialized === 'true') {
            return;
        }

        root.dataset.notificationInitialized = 'true';

        const toggle = root.querySelector('[data-notification-toggle]');
        const panel = root.querySelector('[data-notification-panel]');
        const markReadButton = root.querySelector('[data-notification-mark-read]');

        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';

            if (isOpen) {
                closePanel(toggle, panel);
            } else {
                roots.forEach((otherRoot) => {
                    if (otherRoot === root) {
                        return;
                    }

                    const otherToggle = otherRoot.querySelector('[data-notification-toggle]');
                    const otherPanel = otherRoot.querySelector('[data-notification-panel]');

                    if (otherToggle && otherPanel) {
                        closePanel(otherToggle, otherPanel);
                    }
                });

                openPanel(toggle, panel);
            }
        });

        panel.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        markReadButton?.addEventListener('click', (event) => {
            event.preventDefault();
            writeSeenAt(root.dataset.userId || '', Date.now());
            refreshAll();
        });
    });

    document.addEventListener('click', () => {
        roots.forEach((root) => {
            const toggle = root.querySelector('[data-notification-toggle]');
            const panel = root.querySelector('[data-notification-panel]');

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
            const toggle = root.querySelector('[data-notification-toggle]');
            const panel = root.querySelector('[data-notification-panel]');

            if (toggle && panel && toggle.getAttribute('aria-expanded') === 'true') {
                closePanel(toggle, panel);
                toggle.focus();
            }
        });
    });

    refreshAll();
}
