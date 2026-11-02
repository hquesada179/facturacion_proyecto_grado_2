/**
 * IA 2.0-A — minimal, dependency-free Markdown-subset renderer.
 *
 * Security model: this module never assigns to `innerHTML` and never builds
 * a tag name from model-provided text. Every piece of text that comes from
 * the assistant's message is placed either via `document.createTextNode`
 * or as the `.textContent` of an element whose tag name is chosen from a
 * fixed, hardcoded set (`p`, `br`, `ul`, `ol`, `li`, `strong`, `em`,
 * `code`). Because of that, strings like `<script>...</script>` or
 * `<img onerror=...>` can only ever become inert text — they are never
 * parsed as markup and never create a real `<script>`/`<img>` element.
 * Links are intentionally not supported in this phase (see the IA 2.0-A
 * report) specifically to avoid introducing an `href`/`javascript:` surface
 * before it is needed.
 */

const ASSISTANT_INLINE_PATTERN = /`([^`]+)`|\*\*([^*]+)\*\*|__([^_]+)__|\*([^*]+)\*|_([^_]+)_/g;

function matchAssistantListItem(line) {
    const unordered = line.match(/^\s*[-*+]\s+(.+)$/);

    if (unordered) {
        return { ordered: false, content: unordered[1] };
    }

    const ordered = line.match(/^\s*\d+[.)]\s+(.+)$/);

    if (ordered) {
        return { ordered: true, content: ordered[1] };
    }

    return null;
}

function parseAssistantInline(text) {
    const tokens = [];
    let lastIndex = 0;
    let match;

    ASSISTANT_INLINE_PATTERN.lastIndex = 0;

    while ((match = ASSISTANT_INLINE_PATTERN.exec(text)) !== null) {
        if (match.index > lastIndex) {
            tokens.push({ type: 'text', value: text.slice(lastIndex, match.index) });
        }

        if (match[1] !== undefined) {
            tokens.push({ type: 'code', value: match[1] });
        } else if (match[2] !== undefined || match[3] !== undefined) {
            tokens.push({ type: 'bold', value: match[2] ?? match[3] });
        } else {
            tokens.push({ type: 'italic', value: match[4] ?? match[5] });
        }

        lastIndex = ASSISTANT_INLINE_PATTERN.lastIndex;
    }

    if (lastIndex < text.length) {
        tokens.push({ type: 'text', value: text.slice(lastIndex) });
    }

    return tokens;
}

/**
 * Pure parser (no DOM access) — splits the raw assistant message into a
 * small block AST. Kept dependency-free from `document` so it can be unit
 * tested under plain Node as well as in the browser.
 */
export function parseAssistantMarkdown(text) {
    const source = String(text ?? '').replace(/\r\n/g, '\n');
    const lines = source.split('\n');
    const blocks = [];
    let paragraphLines = [];

    const flushParagraph = () => {
        if (paragraphLines.length === 0) {
            return;
        }

        blocks.push({ type: 'paragraph', lines: paragraphLines.map(parseAssistantInline) });
        paragraphLines = [];
    };

    let i = 0;

    while (i < lines.length) {
        const line = lines[i];

        if (line.trim() === '') {
            flushParagraph();
            i += 1;
            continue;
        }

        const item = matchAssistantListItem(line);

        if (item) {
            flushParagraph();
            const ordered = item.ordered;
            const items = [];

            while (i < lines.length) {
                const current = matchAssistantListItem(lines[i]);

                if (! current || current.ordered !== ordered) {
                    break;
                }

                items.push(parseAssistantInline(current.content));
                i += 1;
            }

            blocks.push({ type: 'list', ordered, items });
            continue;
        }

        paragraphLines.push(line);
        i += 1;
    }

    flushParagraph();

    return blocks;
}

function appendAssistantTokens(parent, tokens) {
    tokens.forEach((token) => {
        if (token.type === 'text') {
            parent.appendChild(document.createTextNode(token.value));
            return;
        }

        const tagName = token.type === 'bold' ? 'strong' : token.type === 'italic' ? 'em' : 'code';
        const element = document.createElement(tagName);
        element.textContent = token.value;
        parent.appendChild(element);
    });
}

/**
 * Builds real DOM nodes from the parsed block AST. See the module-level
 * comment above for the exact XSS-safety guarantee this relies on.
 */
export function renderAssistantMarkdown(text) {
    const container = document.createElement('div');
    container.className = 'assistant-markdown space-y-xs';

    const blocks = parseAssistantMarkdown(text);

    if (blocks.length === 0) {
        container.appendChild(document.createElement('p'));
        return container;
    }

    blocks.forEach((block) => {
        if (block.type === 'list') {
            const list = document.createElement(block.ordered ? 'ol' : 'ul');
            list.className = block.ordered
                ? 'list-decimal list-inside space-y-xs'
                : 'list-disc list-inside space-y-xs';

            block.items.forEach((tokens) => {
                const li = document.createElement('li');
                appendAssistantTokens(li, tokens);
                list.appendChild(li);
            });

            container.appendChild(list);
            return;
        }

        const p = document.createElement('p');
        block.lines.forEach((tokens, index) => {
            if (index > 0) {
                p.appendChild(document.createElement('br'));
            }
            appendAssistantTokens(p, tokens);
        });
        container.appendChild(p);
    });

    return container;
}

/**
 * IA 2.0-B — renders the `components` array (invoice/customer/product/
 * credit-note lists, metric summaries, traceability summaries) as compact
 * cards instead of a JSON blob. Every `href` rendered here was already
 * built server-side (Laravel's `route()`, inside AssistantComponentBuilder)
 * from an item the user was already authorized to see — this module never
 * constructs a URL itself and never reads a URL out of the model's text.
 * A missing url/pdf_url/traceability_url on an item simply means that
 * action isn't available for it (wrong status, or the role lacks the
 * policy) — the corresponding button is omitted, never disabled-but-shown.
 */

function formatAssistantMoney(value) {
    const amount = Math.round(Number(value) || 0);

    return '$'+amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function formatAssistantDate(value) {
    if (! value) {
        return '';
    }

    const [year, month, day] = String(value).slice(0, 10).split('-');

    return (year && month && day) ? `${day}/${month}/${year}` : String(value);
}

function assistantCardShell() {
    const card = document.createElement('div');
    card.className = 'rounded-lg border border-outline-variant/40 bg-surface-container-low p-sm';

    return card;
}

function assistantCardLine(text, className) {
    const p = document.createElement('p');
    p.className = className;
    p.textContent = text;

    return p;
}

function assistantCardActions(actions) {
    const row = document.createElement('div');
    row.className = 'mt-xs flex flex-wrap gap-xs';

    actions
        .filter((action) => action.href)
        .forEach((action) => {
            const link = document.createElement('a');
            link.href = action.href;
            link.textContent = action.label;
            link.className = 'px-sm py-xs rounded-full border border-outline-variant font-label-sm text-label-sm hover:border-primary hover:text-primary';

            if (action.newTab) {
                link.target = '_blank';
                link.rel = 'noopener';
            }

            row.appendChild(link);
        });

    return row;
}

function buildAssistantInvoiceCard(item) {
    const card = assistantCardShell();
    card.appendChild(assistantCardLine('Factura '+(item.number ?? ''), 'font-label-md text-label-md text-on-surface'));

    if (item.status_label) {
        card.appendChild(assistantCardLine(item.status_label, 'font-body-sm text-body-sm text-on-surface-variant'));
    }

    if (item.customer) {
        card.appendChild(assistantCardLine(item.customer, 'font-body-sm text-body-sm text-on-surface-variant'));
    }

    if (item.total !== null && item.total !== undefined) {
        card.appendChild(assistantCardLine(formatAssistantMoney(item.total), 'font-label-md text-label-md text-on-surface'));
    }

    if (item.date) {
        card.appendChild(assistantCardLine(formatAssistantDate(item.date), 'font-label-sm text-label-sm text-on-surface-variant'));
    }

    card.appendChild(assistantCardActions([
        { label: 'Ver factura', href: item.url },
        { label: 'PDF', href: item.pdf_url, newTab: true },
        { label: 'Trazabilidad', href: item.traceability_url },
    ]));

    return card;
}

function buildAssistantCreditNoteCard(item) {
    const card = assistantCardShell();
    card.appendChild(assistantCardLine('Nota crédito '+(item.number ?? ''), 'font-label-md text-label-md text-on-surface'));

    if (item.status_label) {
        card.appendChild(assistantCardLine(item.status_label, 'font-body-sm text-body-sm text-on-surface-variant'));
    }

    if (item.customer) {
        card.appendChild(assistantCardLine(item.customer, 'font-body-sm text-body-sm text-on-surface-variant'));
    }

    if (item.invoice_number) {
        card.appendChild(assistantCardLine('Factura origen: '+item.invoice_number, 'font-label-sm text-label-sm text-on-surface-variant'));
    }

    if (item.total !== null && item.total !== undefined) {
        card.appendChild(assistantCardLine(formatAssistantMoney(item.total), 'font-label-md text-label-md text-on-surface'));
    }

    if (item.date) {
        card.appendChild(assistantCardLine(formatAssistantDate(item.date), 'font-label-sm text-label-sm text-on-surface-variant'));
    }

    card.appendChild(assistantCardActions([
        { label: 'Ver nota crédito', href: item.url },
        { label: 'PDF', href: item.pdf_url, newTab: true },
    ]));

    return card;
}

function buildAssistantCustomerCard(item) {
    const card = assistantCardShell();
    card.appendChild(assistantCardLine(item.name ?? '', 'font-label-md text-label-md text-on-surface'));

    if (item.identification) {
        card.appendChild(assistantCardLine(item.identification, 'font-body-sm text-body-sm text-on-surface-variant'));
    }

    if (item.status) {
        card.appendChild(assistantCardLine(item.status, 'font-label-sm text-label-sm text-on-surface-variant'));
    }

    if (item.documents_count !== null && item.documents_count !== undefined) {
        card.appendChild(assistantCardLine(item.documents_count+' documento(s)', 'font-label-sm text-label-sm text-on-surface-variant'));
    }

    card.appendChild(assistantCardActions([{ label: 'Ver cliente', href: item.url }]));

    return card;
}

function buildAssistantProductCard(item) {
    const card = assistantCardShell();
    card.appendChild(assistantCardLine(item.name ?? '', 'font-label-md text-label-md text-on-surface'));

    if (item.sku) {
        card.appendChild(assistantCardLine(item.sku, 'font-body-sm text-body-sm text-on-surface-variant'));
    }

    if (item.price !== null && item.price !== undefined) {
        card.appendChild(assistantCardLine(formatAssistantMoney(item.price), 'font-label-md text-label-md text-on-surface'));
    }

    if (item.taxes) {
        card.appendChild(assistantCardLine(item.taxes, 'font-label-sm text-label-sm text-on-surface-variant'));
    }

    card.appendChild(assistantCardActions([{ label: 'Ver producto', href: item.url }]));

    return card;
}

function renderAssistantDocumentList(component, buildCard) {
    const items = Array.isArray(component.items) ? component.items : [];

    if (items.length === 0) {
        return null;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'space-y-xs';

    items.forEach((item) => wrapper.appendChild(buildCard(item)));

    const total = component.total_matches ?? items.length;
    const showing = component.showing ?? items.length;

    if (total > showing) {
        wrapper.appendChild(assistantCardLine(
            `Mostrando ${showing} de ${total} resultados.`,
            'font-label-sm text-label-sm text-on-surface-variant',
        ));
    }

    return wrapper;
}

function renderAssistantMetricSummary(component) {
    const items = Array.isArray(component.items) ? component.items : [];

    if (items.length === 0) {
        return null;
    }

    const card = assistantCardShell();
    card.classList.add('grid', 'grid-cols-2', 'gap-xs');

    if (component.title) {
        const title = assistantCardLine(component.title, 'font-label-md text-label-md text-on-surface mb-xs');
        title.classList.add('col-span-2');
        card.appendChild(title);
    }

    items.forEach((metric) => {
        const box = document.createElement('div');
        box.appendChild(assistantCardLine(metric.label ?? '', 'font-label-sm text-label-sm text-on-surface-variant'));
        box.appendChild(assistantCardLine(metric.value ?? '', 'font-title-sm text-title-sm text-on-surface'));
        card.appendChild(box);
    });

    return card;
}

function renderAssistantTraceabilitySummary(component) {
    const items = Array.isArray(component.items) ? component.items : [];

    if (items.length === 0) {
        return null;
    }

    const card = assistantCardShell();
    card.appendChild(assistantCardLine(
        'Trazabilidad — '+(component.invoice_number ?? ''),
        'font-label-md text-label-md text-on-surface mb-xs',
    ));

    const list = document.createElement('ol');
    list.className = 'list-decimal list-inside space-y-xs font-body-sm text-body-sm text-on-surface-variant';

    items.forEach((event) => {
        const li = document.createElement('li');
        li.textContent = `${event.date}: ${event.description}`;
        list.appendChild(li);
    });

    card.appendChild(list);

    const total = component.total_events ?? items.length;

    if (total > items.length) {
        card.appendChild(assistantCardLine(
            `Mostrando ${items.length} de ${total} eventos.`,
            'font-label-sm text-label-sm text-on-surface-variant mt-xs',
        ));
    }

    return card;
}

function renderAssistantComponent(component) {
    switch (component?.type) {
        case 'invoice_list':
            return renderAssistantDocumentList(component, buildAssistantInvoiceCard);
        case 'credit_note_list':
            return renderAssistantDocumentList(component, buildAssistantCreditNoteCard);
        case 'customer_list':
            return renderAssistantDocumentList(component, buildAssistantCustomerCard);
        case 'product_list':
            return renderAssistantDocumentList(component, buildAssistantProductCard);
        case 'metric_summary':
            return renderAssistantMetricSummary(component);
        case 'traceability_summary':
            return renderAssistantTraceabilitySummary(component);
        default:
            return null;
    }
}

export function renderAssistantComponents(components) {
    const container = document.createElement('div');
    container.className = 'assistant-components space-y-sm';

    (Array.isArray(components) ? components : []).forEach((component) => {
        const node = renderAssistantComponent(component);

        if (node) {
            container.appendChild(node);
        }
    });

    return container;
}

class AssistantPanel {
    constructor(root) {
        this.root = root;
        this.messagesEl = root.querySelector('[data-assistant-messages]');
        this.formEl = root.querySelector('[data-assistant-form]');
        this.inputEl = root.querySelector('[data-assistant-input]');
        this.loadingEl = root.querySelector('[data-assistant-loading]');

        this.messageUrl = root.dataset.messageUrl;
        this.confirmUrl = root.dataset.confirmUrl;
        this.feedbackUrlTemplate = root.dataset.feedbackUrlTemplate;
        this.screen = root.dataset.screen || 'unknown';
        this.resourceType = root.dataset.resourceType || null;
        this.resourceId = root.dataset.resourceId || null;
        this.conversationId = null;
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        this.expanded = false;
        this.minimized = false;

        this.bindQuickActions();
        this.bindForm();
        this.bindHeaderControls();
    }

    bindQuickActions() {
        this.root.querySelectorAll('[data-assistant-quick-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const { type, value } = button.dataset;

                if (type === 'anchor') {
                    document.querySelector(value)?.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    return;
                }

                if (type === 'focus') {
                    // Pre-fills the input (e.g. "Busca el cliente ") and
                    // lets the user type the actual name — there is no
                    // fixed message to send for a bare "search" action.
                    this.inputEl.value = value;
                    this.inputEl.focus();
                    this.inputEl.setSelectionRange(value.length, value.length);

                    return;
                }

                this.sendMessage(value);
            });
        });
    }

    bindHeaderControls() {
        const expandButton = this.root.querySelector('[data-assistant-expand-toggle]');
        const minimizeButton = this.root.querySelector('[data-assistant-minimize-toggle]');
        const body = this.root.querySelector('[data-assistant-body]');
        const expandedClasses = ['fixed', 'inset-4', 'md:inset-x-10', 'md:inset-y-10', 'z-50', 'shadow-2xl', 'max-w-3xl', 'mx-auto', 'w-auto'];

        expandButton?.addEventListener('click', () => {
            this.expanded = !this.expanded;
            expandedClasses.forEach((className) => this.root.classList.toggle(className, this.expanded));

            const icon = expandButton.querySelector('.material-symbols-outlined');
            icon.textContent = this.expanded ? 'close_fullscreen' : 'open_in_full';
            expandButton.setAttribute('aria-label', this.expanded ? 'Contraer asistente' : 'Expandir asistente');
            expandButton.setAttribute('title', expandButton.getAttribute('aria-label'));
        });

        minimizeButton?.addEventListener('click', () => {
            this.minimized = !this.minimized;
            body?.classList.toggle('hidden', this.minimized);

            const icon = minimizeButton.querySelector('.material-symbols-outlined');
            icon.textContent = this.minimized ? 'add' : 'remove';
            minimizeButton.setAttribute('aria-label', this.minimized ? 'Reabrir asistente' : 'Minimizar asistente');
            minimizeButton.setAttribute('title', minimizeButton.getAttribute('aria-label'));
        });
    }

    bindForm() {
        this.formEl.addEventListener('submit', (event) => {
            event.preventDefault();

            const value = this.inputEl.value.trim();

            if (! value) {
                return;
            }

            this.inputEl.value = '';
            this.sendMessage(value);
        });
    }

    async sendMessage(message) {
        this.appendUserMessage(message);
        this.setLoading(true);

        try {
            const response = await fetch(this.messageUrl, {
                method: 'POST',
                headers: this.headers(),
                body: JSON.stringify({
                    message,
                    screen: this.screen,
                    resource_type: this.resourceType,
                    resource_id: this.resourceId ? Number(this.resourceId) : null,
                    conversation_id: this.conversationId,
                }),
            });

            if (response.status === 429) {
                this.appendSystemNote('Has hecho demasiadas preguntas en poco tiempo. Intenta de nuevo en un minuto.', true);

                return;
            }

            const data = await response.json();

            if (! response.ok) {
                throw new Error(data.message || 'El asistente no está disponible temporalmente.');
            }

            this.conversationId = data.conversation_id || this.conversationId;
            this.appendAssistantMessage(data);
        } catch (error) {
            this.appendSystemNote('El asistente no está disponible temporalmente. Las funciones de facturación continúan funcionando normalmente.', true);
        } finally {
            this.setLoading(false);
        }
    }

    async confirmAction(token, button, card) {
        button.disabled = true;
        button.textContent = 'Confirmando...';

        try {
            const response = await fetch(this.confirmUrl, {
                method: 'POST',
                headers: this.headers(),
                body: JSON.stringify({ token }),
            });

            const data = await response.json();

            if (! response.ok) {
                throw new Error(data.message || 'No se pudo confirmar la acción.');
            }

            card.remove();
            this.appendSystemNote(data.message);
        } catch (error) {
            button.disabled = false;
            button.textContent = 'Confirmar';
            this.appendSystemNote(error.message || 'No se pudo confirmar la acción.', true);
        }
    }

    async sendFeedback(messageId, feedback, container) {
        const url = this.feedbackUrlTemplate.replace('__ID__', messageId);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: this.headers(),
                body: JSON.stringify({ feedback }),
            });

            if (! response.ok) {
                throw new Error();
            }

            container.textContent = 'Gracias por tu feedback.';
        } catch (error) {
            // Feedback is best-effort and must never disrupt the invoicing flow.
        }
    }

    appendUserMessage(text) {
        const bubble = document.createElement('div');
        bubble.className = 'ml-auto max-w-[85%] bg-primary text-on-primary rounded-lg px-md py-sm';
        bubble.textContent = text;
        this.messagesEl.appendChild(bubble);
        this.scrollToBottom();
    }

    appendAssistantMessage(data) {
        const wrapper = document.createElement('div');
        wrapper.className = 'max-w-[90%] bg-surface rounded-lg px-md py-sm border border-outline-variant/40';

        const content = renderAssistantMarkdown(data.message);
        content.classList.add('text-on-surface');
        wrapper.appendChild(content);

        const components = Array.isArray(data.components) ? data.components : [];

        if (components.length) {
            wrapper.appendChild(renderAssistantComponents(components));
        }

        if (Array.isArray(data.suggestions) && data.suggestions.length) {
            wrapper.appendChild(this.buildSuggestions(data.suggestions));
        }

        if (data.requires_confirmation && Array.isArray(data.actions)) {
            data.actions
                .filter((action) => action.token)
                .forEach((action) => wrapper.appendChild(this.buildConfirmationCard(action)));
        }

        if (data.message_id) {
            wrapper.appendChild(this.buildFeedbackControls(data.message_id));
        }

        this.messagesEl.appendChild(wrapper);
        this.scrollToBottom();
    }

    buildSuggestions(suggestions) {
        const container = document.createElement('div');
        container.className = 'mt-sm flex flex-wrap gap-xs';

        suggestions.forEach((suggestion) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'px-sm py-xs rounded-full border border-outline-variant font-label-sm text-label-sm hover:border-primary hover:text-primary';
            chip.textContent = suggestion.label;
            chip.addEventListener('click', () => this.sendMessage(suggestion.message));
            container.appendChild(chip);
        });

        return container;
    }

    buildConfirmationCard(action) {
        const card = document.createElement('div');
        card.className = 'mt-sm rounded-lg border border-primary/40 bg-primary/5 p-sm';

        const summary = document.createElement('p');
        summary.className = 'font-label-md text-label-md text-on-surface mb-xs';
        summary.textContent = action.summary;
        card.appendChild(summary);

        const confirmButton = document.createElement('button');
        confirmButton.type = 'button';
        confirmButton.className = 'px-md py-xs rounded-lg bg-primary text-on-primary font-label-sm text-label-sm';
        confirmButton.textContent = 'Confirmar';
        confirmButton.addEventListener('click', () => this.confirmAction(action.token, confirmButton, card));
        card.appendChild(confirmButton);

        return card;
    }

    buildFeedbackControls(messageId) {
        const container = document.createElement('div');
        container.className = 'mt-sm flex items-center gap-xs text-on-surface-variant';

        const up = document.createElement('button');
        up.type = 'button';
        up.setAttribute('aria-label', 'Respuesta útil');
        up.className = 'p-xs rounded-full hover:bg-surface-container-highest hover:text-primary';
        up.innerHTML = '<span class="material-symbols-outlined text-base">thumb_up</span>';
        up.addEventListener('click', () => this.sendFeedback(messageId, 'useful', container));

        const down = document.createElement('button');
        down.type = 'button';
        down.setAttribute('aria-label', 'Respuesta no útil');
        down.className = 'p-xs rounded-full hover:bg-surface-container-highest hover:text-error';
        down.innerHTML = '<span class="material-symbols-outlined text-base">thumb_down</span>';
        down.addEventListener('click', () => this.sendFeedback(messageId, 'not_useful', container));

        container.append(up, down);

        return container;
    }

    appendSystemNote(text, isError = false) {
        const note = document.createElement('div');
        note.className = isError
            ? 'text-[#9b1c1c] bg-[#fde8e8] rounded-lg px-md py-sm'
            : 'text-on-surface-variant';
        note.textContent = text;
        this.messagesEl.appendChild(note);
        this.scrollToBottom();
    }

    setLoading(isLoading) {
        this.loadingEl.classList.toggle('hidden', ! isLoading);
        this.loadingEl.classList.toggle('flex', isLoading);
    }

    scrollToBottom() {
        this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
    }

    headers() {
        return {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': this.csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }
}

export function initAssistantPanels() {
    document.querySelectorAll('[data-assistant-panel]').forEach((root) => new AssistantPanel(root));
}
