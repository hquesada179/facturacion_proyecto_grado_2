import { describe, expect, it } from 'vitest';
import { parseAssistantMarkdown, renderAssistantMarkdown } from './assistant.js';

/**
 * Walks every element in the rendered subtree and collects any attribute
 * name starting with "on" (onerror, onclick, onload, ...). Used as a
 * blanket assertion that no event-handler attribute ever survives into the
 * DOM, regardless of how it was spelled in the source message.
 */
function collectEventHandlerAttributes(root) {
    const found = [];
    const walker = [root, ...root.querySelectorAll('*')];

    walker.forEach((el) => {
        Array.from(el.attributes ?? []).forEach((attr) => {
            if (attr.name.toLowerCase().startsWith('on')) {
                found.push(attr.name);
            }
        });
    });

    return found;
}

describe('parseAssistantMarkdown (pure AST, no DOM)', () => {
    it('parses **bold** as a bold token', () => {
        const blocks = parseAssistantMarkdown('**hola**');
        expect(blocks).toEqual([
            { type: 'paragraph', lines: [[{ type: 'bold', value: 'hola' }]] },
        ]);
    });

    it('never produces an "html" token type for a literal <script> tag', () => {
        const blocks = parseAssistantMarkdown('<script>alert(1)</script>');
        const tokenTypes = blocks.flatMap((block) =>
            block.type === 'paragraph' ? block.lines.flat().map((t) => t.type) : []
        );

        expect(tokenTypes.every((type) => ['text', 'bold', 'italic', 'code'].includes(type))).toBe(true);
    });
});

describe('renderAssistantMarkdown (DOM output)', () => {
    it('renders **texto** as a <strong> element with no literal asterisks visible', () => {
        const container = renderAssistantMarkdown('Hola **mundo**, bienvenido');
        const strong = container.querySelector('strong');

        expect(strong).not.toBeNull();
        expect(strong.textContent).toBe('mundo');
        expect(container.textContent).not.toContain('**');
        expect(container.textContent).toContain('Hola mundo, bienvenido');
    });

    it('renders _texto_ / *texto* as an <em> element', () => {
        const container = renderAssistantMarkdown('Esto es *importante* de verdad');
        const em = container.querySelector('em');

        expect(em).not.toBeNull();
        expect(em.textContent).toBe('importante');
    });

    it('renders inline `code` as a <code> element', () => {
        const container = renderAssistantMarkdown('Usa el campo `payment_method`');
        const code = container.querySelector('code');

        expect(code).not.toBeNull();
        expect(code.textContent).toBe('payment_method');
    });

    it('renders an unordered list as <ul><li>', () => {
        const container = renderAssistantMarkdown('Pasos:\n- Selecciona el cliente\n- Agrega productos\n- Valida la factura');
        const list = container.querySelector('ul');

        expect(list).not.toBeNull();
        expect(list.tagName).toBe('UL');
        expect(Array.from(list.querySelectorAll('li')).map((li) => li.textContent)).toEqual([
            'Selecciona el cliente',
            'Agrega productos',
            'Valida la factura',
        ]);
    });

    it('renders an ordered list as <ol><li>', () => {
        const container = renderAssistantMarkdown('1. Cliente\n2. Productos\n3. Validación');
        const list = container.querySelector('ol');

        expect(list).not.toBeNull();
        expect(list.tagName).toBe('OL');
        expect(list.querySelectorAll('li')).toHaveLength(3);
    });

    it('separates paragraphs on blank lines and keeps single newlines as <br> within one paragraph', () => {
        const container = renderAssistantMarkdown('Primer párrafo.\n\nSegundo párrafo con\nun salto de línea interno.');
        const paragraphs = container.querySelectorAll('p');

        expect(paragraphs).toHaveLength(2);
        expect(paragraphs[0].textContent).toBe('Primer párrafo.');
        expect(paragraphs[1].querySelectorAll('br')).toHaveLength(1);
        expect(paragraphs[1].textContent).toBe('Segundo párrafo conun salto de línea interno.');
    });

    it('never executes or creates a real <script> element from a malicious message', () => {
        const container = renderAssistantMarkdown('<script>alert(1)</script>');

        expect(container.querySelector('script')).toBeNull();
        expect(container.textContent).toContain('<script>alert(1)</script>');
        expect(collectEventHandlerAttributes(container)).toEqual([]);
    });

    it('never creates an <img> element or interprets onerror from a malicious message', () => {
        const container = renderAssistantMarkdown('<img src=x onerror=alert(1)>');

        expect(container.querySelector('img')).toBeNull();
        expect(container.textContent).toContain('<img src=x onerror=alert(1)>');
        expect(collectEventHandlerAttributes(container)).toEqual([]);
    });

    it('never interprets onclick or any other on* attribute from a malicious message', () => {
        const container = renderAssistantMarkdown('<div onclick="alert(1)">click me</div>');

        expect(container.querySelector('div[onclick]')).toBeNull();
        expect(collectEventHandlerAttributes(container)).toEqual([]);
        expect(container.textContent).toContain('<div onclick="alert(1)">click me</div>');
    });

    it('never produces a real <a> element or interprets a javascript: URL', () => {
        const container = renderAssistantMarkdown('[haz clic aquí](javascript:alert(1))');

        expect(container.querySelector('a')).toBeNull();
        expect(container.textContent).toContain('javascript:alert(1)');
    });

    it('renders valid Markdown mixed with malicious HTML: the Markdown part renders, the HTML stays inert text', () => {
        const container = renderAssistantMarkdown('**Aviso importante**: <img src=x onerror=alert(1)> revisa el documento');
        const strong = container.querySelector('strong');

        expect(strong).not.toBeNull();
        expect(strong.textContent).toBe('Aviso importante');
        expect(container.querySelector('img')).toBeNull();
        expect(collectEventHandlerAttributes(container)).toEqual([]);
        expect(container.textContent).toContain('<img src=x onerror=alert(1)>');
    });

    it('handles an empty or null message without throwing', () => {
        expect(() => renderAssistantMarkdown('')).not.toThrow();
        expect(() => renderAssistantMarkdown(null)).not.toThrow();
        expect(() => renderAssistantMarkdown(undefined)).not.toThrow();
    });
});
