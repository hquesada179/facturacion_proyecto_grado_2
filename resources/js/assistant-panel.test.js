import { afterEach, describe, expect, it, vi } from 'vitest';
import { initAssistantPanels } from './assistant.js';

/**
 * IA 2.0-C UX coverage for the panel shell itself (expand/minimize,
 * quick-action wiring, message persistence across turns, scroll-to-bottom).
 * These exercise real DOM nodes built from the exact `data-assistant-*`
 * attributes `assistant-panel.blade.php` renders — not a reimplementation
 * of the markup — so a renamed attribute here would fail loudly instead of
 * silently drifting from the template.
 */

function buildPanel({ mode = 'sidebar' } = {}) {
    document.head.innerHTML = '<meta name="csrf-token" content="test-csrf-token">';

    const expandButtonHtml = mode === 'full' ? '' : `
        <button type="button" data-assistant-expand-toggle aria-label="Expandir asistente" title="Expandir asistente">
            <span class="material-symbols-outlined">open_in_full</span>
        </button>
    `;

    document.body.innerHTML = `
        <div
            data-assistant-panel
            data-screen="dashboard"
            data-resource-type=""
            data-resource-id=""
            data-message-url="/assistant/message"
            data-confirm-url="/assistant/confirm"
            data-feedback-url-template="/assistant/messages/__ID__/feedback"
        >
            <div>
                ${expandButtonHtml}
                <button type="button" data-assistant-minimize-toggle aria-label="Minimizar asistente" title="Minimizar asistente">
                    <span class="material-symbols-outlined">remove</span>
                </button>
            </div>

            <div data-assistant-body>
                <div data-assistant-quick-actions>
                    <button type="button" data-assistant-quick-action data-type="message" data-value="¿Cuántas facturas tengo hoy?">Resumen de hoy</button>
                    <button type="button" data-assistant-quick-action data-type="focus" data-value="Busca la factura ">Buscar factura</button>
                    <button type="button" data-assistant-quick-action data-type="anchor" data-value="#validation-summary">Volver al campo problemático</button>
                </div>

                <div data-assistant-messages></div>

                <div class="hidden" data-assistant-loading></div>

                <form data-assistant-form>
                    <input type="text" data-assistant-input>
                    <button type="submit">Enviar</button>
                </form>
            </div>
        </div>

        <div id="validation-summary"></div>
    `;

    initAssistantPanels();

    return {
        panel: document.querySelector('[data-assistant-panel]'),
        body: document.querySelector('[data-assistant-body]'),
        expandButton: document.querySelector('[data-assistant-expand-toggle]'),
        minimizeButton: document.querySelector('[data-assistant-minimize-toggle]'),
        messagesEl: document.querySelector('[data-assistant-messages]'),
        loadingEl: document.querySelector('[data-assistant-loading]'),
        form: document.querySelector('[data-assistant-form]'),
        input: document.querySelector('[data-assistant-input]'),
        quickActions: document.querySelectorAll('[data-assistant-quick-action]'),
    };
}

function mockFetchOnce(responseBody, { ok = true, status = 200 } = {}) {
    global.fetch = vi.fn().mockResolvedValue({
        ok,
        status,
        json: () => Promise.resolve(responseBody),
    });
}

describe('AssistantPanel — expand/minimize', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('expand toggle flips state, icon and aria-label on each click', () => {
        const { expandButton, panel } = buildPanel();
        const icon = expandButton.querySelector('.material-symbols-outlined');

        expect(panel.classList.contains('fixed')).toBe(false);
        expect(expandButton.getAttribute('aria-label')).toBe('Expandir asistente');

        expandButton.click();

        expect(panel.classList.contains('fixed')).toBe(true);
        expect(icon.textContent).toBe('close_fullscreen');
        expect(expandButton.getAttribute('aria-label')).toBe('Contraer asistente');

        expandButton.click();

        expect(panel.classList.contains('fixed')).toBe(false);
        expect(icon.textContent).toBe('open_in_full');
        expect(expandButton.getAttribute('aria-label')).toBe('Expandir asistente');
    });

    it('never throws when the expand toggle is absent (full-page mode has no expand button)', () => {
        expect(() => buildPanel({ mode: 'full' })).not.toThrow();
        expect(document.querySelector('[data-assistant-expand-toggle]')).toBeNull();
    });

    it('minimize toggle hides and reopens the body, toggling icon and aria-label', () => {
        const { minimizeButton, body } = buildPanel();
        const icon = minimizeButton.querySelector('.material-symbols-outlined');

        expect(body.classList.contains('hidden')).toBe(false);

        minimizeButton.click();

        expect(body.classList.contains('hidden')).toBe(true);
        expect(icon.textContent).toBe('add');
        expect(minimizeButton.getAttribute('aria-label')).toBe('Reabrir asistente');

        minimizeButton.click();

        expect(body.classList.contains('hidden')).toBe(false);
        expect(icon.textContent).toBe('remove');
        expect(minimizeButton.getAttribute('aria-label')).toBe('Minimizar asistente');
    });

    it('minimizing does not clear previously received messages', async () => {
        const { minimizeButton, messagesEl, form, input } = buildPanel();
        mockFetchOnce({ conversation_id: 7, message: 'Tienes 3 facturas hoy.', components: [] });

        input.value = '¿Cuántas facturas tengo hoy?';
        form.dispatchEvent(new Event('submit', { cancelable: true }));

        await vi.waitFor(() => expect(messagesEl.textContent).toContain('Tienes 3 facturas hoy.'));

        minimizeButton.click();
        minimizeButton.click();

        expect(messagesEl.textContent).toContain('Tienes 3 facturas hoy.');
        expect(messagesEl.textContent).toContain('¿Cuántas facturas tengo hoy?');
    });
});

describe('AssistantPanel — quick actions', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('a "message" quick action sends its fixed value as a chat message', async () => {
        const { quickActions, messagesEl } = buildPanel();
        mockFetchOnce({ conversation_id: 1, message: 'Tienes 3 facturas hoy.', components: [] });

        quickActions[0].click();

        expect(messagesEl.textContent).toContain('¿Cuántas facturas tengo hoy?');
        await vi.waitFor(() => expect(messagesEl.textContent).toContain('Tienes 3 facturas hoy.'));
        expect(global.fetch).toHaveBeenCalledWith('/assistant/message', expect.objectContaining({ method: 'POST' }));
    });

    it('a "focus" quick action pre-fills the input and focuses it without sending anything', () => {
        const { quickActions, input } = buildPanel();
        global.fetch = vi.fn();

        quickActions[1].click();

        expect(input.value).toBe('Busca la factura ');
        expect(document.activeElement).toBe(input);
        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('an "anchor" quick action scrolls the referenced element into view without sending anything', () => {
        const { quickActions } = buildPanel();
        global.fetch = vi.fn();
        const target = document.querySelector('#validation-summary');
        target.scrollIntoView = vi.fn();

        quickActions[2].click();

        expect(target.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'center' });
        expect(global.fetch).not.toHaveBeenCalled();
    });
});

describe('AssistantPanel — conversation flow and scroll', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('persists both the user bubble and the assistant reply, in order, across multiple turns', async () => {
        const { form, input, messagesEl } = buildPanel();
        mockFetchOnce({ conversation_id: 1, message: 'Primera respuesta.', components: [] });

        input.value = 'primera pregunta';
        form.dispatchEvent(new Event('submit', { cancelable: true }));
        await vi.waitFor(() => expect(messagesEl.textContent).toContain('Primera respuesta.'));

        mockFetchOnce({ conversation_id: 1, message: 'Segunda respuesta.', components: [] });
        input.value = 'segunda pregunta';
        form.dispatchEvent(new Event('submit', { cancelable: true }));
        await vi.waitFor(() => expect(messagesEl.textContent).toContain('Segunda respuesta.'));

        const bubbles = Array.from(messagesEl.children).map((node) => node.textContent);
        expect(bubbles).toEqual([
            'primera pregunta',
            expect.stringContaining('Primera respuesta.'),
            'segunda pregunta',
            expect.stringContaining('Segunda respuesta.'),
        ]);
    });

    it('clears the input immediately on submit so the previous message cannot be resubmitted', () => {
        const { form, input } = buildPanel();
        mockFetchOnce({ conversation_id: 1, message: 'ok', components: [] });

        input.value = 'hola';
        form.dispatchEvent(new Event('submit', { cancelable: true }));

        expect(input.value).toBe('');
    });

    it('ignores a submit with only whitespace and never calls the backend', () => {
        const { form, input, messagesEl } = buildPanel();
        global.fetch = vi.fn();

        input.value = '   ';
        form.dispatchEvent(new Event('submit', { cancelable: true }));

        expect(global.fetch).not.toHaveBeenCalled();
        expect(messagesEl.children).toHaveLength(0);
    });

    it('shows and then hides the loading indicator while a message round-trips', async () => {
        const { form, input, loadingEl } = buildPanel();
        mockFetchOnce({ conversation_id: 1, message: 'listo', components: [] });

        input.value = 'hola';
        form.dispatchEvent(new Event('submit', { cancelable: true }));

        expect(loadingEl.classList.contains('flex')).toBe(true);

        await vi.waitFor(() => expect(loadingEl.classList.contains('hidden')).toBe(true));
        expect(loadingEl.classList.contains('flex')).toBe(false);
    });

    it('shows a non-disruptive system note and still hides loading when the request fails', async () => {
        global.fetch = vi.fn().mockRejectedValue(new Error('network down'));
        const { form, input, messagesEl, loadingEl } = buildPanel();

        input.value = 'hola';
        form.dispatchEvent(new Event('submit', { cancelable: true }));

        await vi.waitFor(() => expect(loadingEl.classList.contains('hidden')).toBe(true));
        expect(messagesEl.textContent).toContain('no está disponible temporalmente');
    });

    it('surfaces the 429 rate-limit message distinctly without throwing', async () => {
        mockFetchOnce({}, { ok: false, status: 429 });
        const { form, input, messagesEl } = buildPanel();

        input.value = 'hola';
        form.dispatchEvent(new Event('submit', { cancelable: true }));

        await vi.waitFor(() => expect(messagesEl.textContent).toContain('demasiadas preguntas'));
    });

    it('scrolls the messages container to the bottom after each new message', async () => {
        const { form, input, messagesEl } = buildPanel();
        mockFetchOnce({ conversation_id: 1, message: 'ok', components: [] });

        Object.defineProperty(messagesEl, 'scrollHeight', { value: 500, configurable: true });
        messagesEl.scrollTop = 0;

        input.value = 'hola';
        form.dispatchEvent(new Event('submit', { cancelable: true }));

        expect(messagesEl.scrollTop).toBe(500);

        await vi.waitFor(() => expect(messagesEl.textContent).toContain('ok'));
        expect(messagesEl.scrollTop).toBe(500);
    });

    it('carries the conversation_id returned by the first reply into the next request body', async () => {
        const { form, input, messagesEl } = buildPanel();
        mockFetchOnce({ conversation_id: 42, message: 'ok', components: [] });

        input.value = 'primera';
        form.dispatchEvent(new Event('submit', { cancelable: true }));
        // Wait for the first round-trip to fully resolve (not just for fetch
        // to have been called) — conversation_id is only captured once the
        // first response's JSON has been processed.
        await vi.waitFor(() => expect(messagesEl.textContent).toContain('ok'));

        mockFetchOnce({ conversation_id: 42, message: 'ok2', components: [] });
        input.value = 'segunda';
        form.dispatchEvent(new Event('submit', { cancelable: true }));
        await vi.waitFor(() => expect(global.fetch).toHaveBeenCalledTimes(1));

        const secondCallBody = JSON.parse(global.fetch.mock.calls[0][1].body);
        expect(secondCallBody.conversation_id).toBe(42);
    });
});
