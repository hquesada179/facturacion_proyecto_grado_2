import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { initNotificationCenter } from './notifications.js';

/**
 * UI/UX 2.0-C coverage for the bell dropdown. Builds real DOM nodes from
 * the exact `data-notification-*` attributes `notification-center.blade.php`
 * renders, so a renamed attribute here fails loudly instead of silently
 * drifting from the template.
 */

function notificationItemHtml({ id, timestamp, title = 'Factura emitida' } = {}) {
    return `
        <a href="/facturas/${id}" data-notification-item data-timestamp="${timestamp}">
            <span class="w-8 h-8">
                <span class="material-symbols-outlined">description</span>
            </span>
            <span class="flex-1">
                <span>${title}</span>
                <span>Descripción de prueba</span>
                <span>hace 1 minuto</span>
            </span>
            <span data-notification-unread-dot hidden></span>
        </a>
    `;
}

function buildBell({ userId = '1', items = [] } = {}) {
    const list = items.length
        ? items.map((item) => notificationItemHtml(item)).join('')
        : '<div data-notification-empty><p>No tienes notificaciones por ahora.</p></div>';

    document.body.innerHTML = `
        <div data-notification-center data-user-id="${userId}">
            <button type="button" data-notification-toggle aria-haspopup="true" aria-expanded="false" aria-label="Notificaciones">
                <span class="material-symbols-outlined">notifications</span>
                <span class="hidden" data-notification-badge>0</span>
            </button>
            <div class="hidden" data-notification-panel role="menu">
                <div>
                    <p>Notificaciones</p>
                    <button type="button" data-notification-mark-read>Marcar todo como leído</button>
                </div>
                <div data-notification-list>${list}</div>
            </div>
        </div>

        <div id="outside-area">Fuera del dropdown</div>
    `;

    return {
        root: document.querySelector('[data-notification-center]'),
        toggle: document.querySelector('[data-notification-toggle]'),
        panel: document.querySelector('[data-notification-panel]'),
        badge: document.querySelector('[data-notification-badge]'),
        markReadButton: document.querySelector('[data-notification-mark-read]'),
        outsideArea: document.querySelector('#outside-area'),
    };
}

describe('NotificationCenter — open/close', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('starts closed and opens on toggle click', () => {
        const { toggle, panel } = buildBell({ items: [{ id: 1, timestamp: new Date().toISOString() }] });
        initNotificationCenter();

        expect(panel.classList.contains('hidden')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('false');

        toggle.click();

        expect(panel.classList.contains('hidden')).toBe(false);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');
    });

    it('closes again on a second toggle click', () => {
        const { toggle, panel } = buildBell({ items: [{ id: 1, timestamp: new Date().toISOString() }] });
        initNotificationCenter();

        toggle.click();
        toggle.click();

        expect(panel.classList.contains('hidden')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });

    it('closes when clicking outside the dropdown', () => {
        const { toggle, panel, outsideArea } = buildBell({ items: [{ id: 1, timestamp: new Date().toISOString() }] });
        initNotificationCenter();

        toggle.click();
        expect(panel.classList.contains('hidden')).toBe(false);

        outsideArea.click();

        expect(panel.classList.contains('hidden')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });

    it('closes on Escape and returns focus to the toggle', () => {
        const { toggle, panel } = buildBell({ items: [{ id: 1, timestamp: new Date().toISOString() }] });
        initNotificationCenter();

        toggle.click();
        expect(panel.classList.contains('hidden')).toBe(false);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(panel.classList.contains('hidden')).toBe(true);
        expect(document.activeElement).toBe(toggle);
    });

    it('clicking inside the panel does not close it', () => {
        const { toggle, panel } = buildBell({ items: [{ id: 1, timestamp: new Date().toISOString() }] });
        initNotificationCenter();

        toggle.click();
        panel.click();

        expect(panel.classList.contains('hidden')).toBe(false);
    });

    it('does not double-toggle when initialized more than once', () => {
        const { toggle, panel } = buildBell({ items: [{ id: 1, timestamp: new Date().toISOString() }] });
        initNotificationCenter();
        initNotificationCenter();

        toggle.click();

        expect(panel.classList.contains('hidden')).toBe(false);
    });

    it('renders the empty state without throwing when there are no items', () => {
        buildBell({ items: [] });

        expect(() => initNotificationCenter()).not.toThrow();
        expect(document.querySelector('[data-notification-empty]')).not.toBeNull();
    });
});

describe('NotificationCenter — unread badge', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('shows a badge with the unread count on first load', () => {
        const { badge } = buildBell({
            userId: '42',
            items: [
                { id: 1, timestamp: new Date().toISOString() },
                { id: 2, timestamp: new Date(Date.now() - 60_000).toISOString() },
            ],
        });

        initNotificationCenter();

        expect(badge.classList.contains('hidden')).toBe(false);
        expect(badge.textContent).toBe('2');
    });

    it('marks every item as read and hides the badge', () => {
        const { markReadButton, badge, root } = buildBell({
            userId: '42',
            items: [{ id: 1, timestamp: new Date().toISOString() }],
        });

        initNotificationCenter();
        expect(badge.classList.contains('hidden')).toBe(false);

        markReadButton.click();

        expect(badge.classList.contains('hidden')).toBe(true);
        root.querySelectorAll('[data-notification-unread-dot]').forEach((dot) => {
            expect(dot.hidden).toBe(true);
        });
    });

    it('hides the badge on a later load once everything has already been seen', () => {
        const pastTimestamp = new Date(Date.now() - 120_000).toISOString();
        window.localStorage.setItem('fiscora:notifications:seen:7', String(Date.now()));

        const { badge } = buildBell({ userId: '7', items: [{ id: 1, timestamp: pastTimestamp }] });

        initNotificationCenter();

        expect(badge.classList.contains('hidden')).toBe(true);
    });

    it('keeps unread state isolated per user id', () => {
        window.localStorage.setItem('fiscora:notifications:seen:1', String(Date.now()));

        const { badge } = buildBell({
            userId: '2',
            items: [{ id: 1, timestamp: new Date().toISOString() }],
        });

        initNotificationCenter();

        expect(badge.classList.contains('hidden')).toBe(false);
    });
});
