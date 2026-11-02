import { afterEach, describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
import { initAssistantPanels } from './assistant.js';

/**
 * This is the test the previous "it still looks the same in the browser"
 * report asked for: NOT a hand-written DOM approximation of what
 * assistant-panel.blade.php renders, but the literal output of that Blade
 * component for both real routes that embed it — `/asistente` (mode=full)
 * and the sidebar panel on `/dashboard` (mode=sidebar). The fixtures were
 * captured once via `php artisan test` against the real routes and are
 * committed under __fixtures__/ so this suite never depends on a running
 * app or a database — only on the exact markup Fiscora actually serves.
 *
 * Regenerate a fixture if assistant-panel.blade.php's structure changes:
 *   render the route, extract the outer <div data-assistant-panel>...</div>,
 *   overwrite the matching file here.
 */

const FIXTURES = {
    full: readFileSync('resources/js/__fixtures__/assistant-panel-full.html', 'utf8'),
    sidebar: readFileSync('resources/js/__fixtures__/assistant-panel-sidebar.html', 'utf8'),
};

function mountFixture(name) {
    document.head.innerHTML = '<meta name="csrf-token" content="test-csrf-token">';
    document.body.innerHTML = FIXTURES[name];

    global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: () => Promise.resolve({ conversation_id: 1, message: 'Tienes 3 facturas hoy.', components: [] }),
    });

    initAssistantPanels();

    const panel = document.querySelector('[data-assistant-panel]');

    return {
        panel,
        body: panel.querySelector('[data-assistant-body]'),
        minimizeButton: panel.querySelector('[data-assistant-minimize-toggle]'),
        expandButton: panel.querySelector('[data-assistant-expand-toggle]'),
        form: panel.querySelector('[data-assistant-form]'),
        input: panel.querySelector('[data-assistant-input]'),
        messagesEl: panel.querySelector('[data-assistant-messages]'),
        quickActions: panel.querySelectorAll('[data-assistant-quick-action]'),
    };
}

describe.each([
    ['full-page panel (/asistente)', 'full'],
    ['sidebar panel (/dashboard)', 'sidebar'],
])('AssistantPanel against the real %s markup', (_label, fixtureName) => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('minimize button exists with the exact attribute assistant.js selects on', () => {
        const { minimizeButton } = mountFixture(fixtureName);

        expect(minimizeButton).not.toBeNull();
        expect(minimizeButton.hasAttribute('data-assistant-minimize-toggle')).toBe(true);
        expect(minimizeButton.getAttribute('type')).toBe('button');
    });

    it('clicking minimize collapses the panel: data-minimized=true, body hidden, icon/aria-label switch to restore, big min-height is neutralized', () => {
        const { panel, minimizeButton, body } = mountFixture(fixtureName);
        const icon = minimizeButton.querySelector('.material-symbols-outlined');

        expect(panel.dataset.minimized).toBeUndefined();
        expect(body.classList.contains('hidden')).toBe(false);

        minimizeButton.click();

        expect(panel.dataset.minimized).toBe('true');
        expect(body.classList.contains('hidden')).toBe(true);
        expect(icon.textContent).toBe('add');
        expect(minimizeButton.getAttribute('aria-label')).toBe('Restaurar asistente');
        // jsdom has no real layout engine, so the actual rendered pixel
        // height can't be asserted from here — this inline style is the
        // mechanism that neutralizes the min-h-[520px]/min-h-[640px]
        // utility regardless of it, and it's the strongest check
        // available outside a real browser.
        expect(panel.style.minHeight).toBe('0px');
        expect(panel.style.height).toBe('auto');
        // The actual root cause on /asistente: the panel is a grid item
        // and its container has no `align-items`, so height:auto alone
        // still resolves to "fill the stretched grid row". align-self is
        // what opts this one item out of being stretched.
        expect(panel.style.alignSelf).toBe('start');
    });

    it('clicking restore brings the body back, clears the collapsed state, and the conversation already on screen stays intact', async () => {
        const { form, input, minimizeButton, body, panel, messagesEl } = mountFixture(fixtureName);

        input.value = '¿Cuántas facturas tengo hoy?';
        form.dispatchEvent(new Event('submit', { cancelable: true }));
        await vi.waitFor(() => expect(messagesEl.textContent).toContain('Tienes 3 facturas hoy.'));

        minimizeButton.click();
        expect(panel.dataset.minimized).toBe('true');

        minimizeButton.click();

        expect(panel.dataset.minimized).toBe('false');
        expect(body.classList.contains('hidden')).toBe(false);
        expect(panel.style.minHeight).toBe('');
        expect(panel.style.height).toBe('');
        expect(panel.style.alignSelf).toBe('');
        expect(messagesEl.textContent).toContain('¿Cuántas facturas tengo hoy?');
        expect(messagesEl.textContent).toContain('Tienes 3 facturas hoy.');
    });

    it('a quick action on this exact real markup sends exactly one request and renders the reply', async () => {
        const { quickActions, messagesEl } = mountFixture(fixtureName);

        expect(quickActions.length).toBeGreaterThan(0);
        quickActions[0].click();

        await vi.waitFor(() => expect(messagesEl.textContent).toContain('Tienes 3 facturas hoy.'));
        expect(global.fetch).toHaveBeenCalledTimes(1);
    });
});

/**
 * Root-cause-specific coverage: the panel mounted inside its REAL parent —
 * assistant/index.blade.php's `grid grid-cols-1 lg:grid-cols-[360px_1fr]
 * gap-lg min-h-[calc(100vh-12rem)]` container (captured as a fixture,
 * including the "Alcance seguro" sibling). That container declares no
 * `align-items`, so it defaults to CSS Grid's own `align-items: stretch`
 * — which is exactly what silently defeated the previous
 * height:auto/min-height:0-only fix. jsdom has no layout engine, so it
 * can't measure the resulting pixel height, but it DOES resolve which
 * inline style ends up on the element, which is the real mechanism that
 * opts the panel out of that stretch in an actual browser.
 */
describe('AssistantPanel inside the real /asistente grid wrapper (align-items: stretch by default)', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    function mountGridWrapper() {
        document.head.innerHTML = '<meta name="csrf-token" content="test-csrf-token">';
        document.body.innerHTML = readFileSync('resources/js/__fixtures__/assistant-grid-wrapper.html', 'utf8');

        initAssistantPanels();

        const panel = document.querySelector('[data-assistant-panel]');

        return {
            wrapper: document.querySelector('.grid'),
            panel,
            minimizeButton: panel.querySelector('[data-assistant-minimize-toggle]'),
            body: panel.querySelector('[data-assistant-body]'),
        };
    }

    it('does not touch align-self in the normal state — the existing design must stay exactly as it is', () => {
        const { wrapper, panel } = mountGridWrapper();

        expect(wrapper.classList.contains('grid')).toBe(true);
        expect(wrapper.className).not.toMatch(/items-/); // confirms the real container has no align-items override
        expect(panel.style.alignSelf).toBe('');
    });

    it('minimizing inside this real grid container sets align-self:start, the one property that opts the item out of the container default stretch', () => {
        const { panel, minimizeButton, body } = mountGridWrapper();

        minimizeButton.click();

        expect(panel.dataset.minimized).toBe('true');
        expect(body.classList.contains('hidden')).toBe(true);
        expect(panel.style.alignSelf).toBe('start');
        expect(panel.style.height).toBe('auto');
        expect(panel.style.minHeight).toBe('0px');
    });

    it('restoring inside the real grid container returns to the untouched default (no align-self), matching the original design', () => {
        const { panel, minimizeButton } = mountGridWrapper();

        minimizeButton.click();
        minimizeButton.click();

        expect(panel.dataset.minimized).toBe('false');
        expect(panel.style.alignSelf).toBe('');
        expect(panel.style.height).toBe('');
        expect(panel.style.minHeight).toBe('');
    });
});
