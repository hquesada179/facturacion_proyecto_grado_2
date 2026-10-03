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

        this.bindQuickActions();
        this.bindForm();
    }

    bindQuickActions() {
        this.root.querySelectorAll('[data-assistant-quick-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const { type, value } = button.dataset;

                if (type === 'anchor') {
                    document.querySelector(value)?.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    return;
                }

                this.sendMessage(value);
            });
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

        const text = document.createElement('p');
        text.className = 'whitespace-pre-line text-on-surface';
        text.textContent = data.message;
        wrapper.appendChild(text);

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
