import { describe, expect, it } from 'vitest';
import { renderAssistantComponents } from './assistant.js';

describe('renderAssistantComponents', () => {
    it('renders an empty container for an empty components array', () => {
        const container = renderAssistantComponents([]);

        expect(container.children).toHaveLength(0);
    });

    it('renders an invoice_list item as a card with number, status, customer and total', () => {
        const container = renderAssistantComponents([
            {
                type: 'invoice_list',
                total_matches: 1,
                showing: 1,
                items: [{
                    id: 42,
                    number: 'FV-000042',
                    customer: 'Kata SAS',
                    status: 'issued',
                    status_label: 'Emitida',
                    total: 119000,
                    date: '2026-11-01 10:00:00',
                    url: '/facturas/42',
                    pdf_url: '/facturas/42/pdf',
                    traceability_url: '/trazabilidad?q=FV-000042',
                }],
            },
        ]);

        expect(container.textContent).toContain('FV-000042');
        expect(container.textContent).toContain('Emitida');
        expect(container.textContent).toContain('Kata SAS');
        expect(container.textContent).toContain('$119.000');
        expect(container.textContent).toContain('01/11/2026');
    });

    it('only renders action links for URLs that are actually present (role/status-dependent)', () => {
        const container = renderAssistantComponents([
            {
                type: 'invoice_list',
                items: [{
                    id: 1,
                    number: 'FV-000001',
                    status_label: 'Borrador',
                    url: '/facturas/1',
                    pdf_url: null,
                    traceability_url: null,
                }],
            },
        ]);

        const links = container.querySelectorAll('a');
        const hrefs = Array.from(links).map((a) => a.getAttribute('href'));

        expect(hrefs).toEqual(['/facturas/1']);
        expect(container.textContent).not.toContain('PDF');
        expect(container.textContent).not.toContain('Trazabilidad');
    });

    it('never builds a link from anything other than the server-provided url field', () => {
        const container = renderAssistantComponents([
            {
                type: 'invoice_list',
                items: [{
                    id: 1,
                    number: 'javascript:alert(1)',
                    status_label: 'Emitida',
                    url: '/facturas/1',
                }],
            },
        ]);

        const link = container.querySelector('a');

        expect(link.getAttribute('href')).toBe('/facturas/1');
        expect(container.querySelectorAll('a')).toHaveLength(1);
    });

    it('shows a "mostrando X de Y" note when more results exist than are shown', () => {
        const container = renderAssistantComponents([
            {
                type: 'customer_list',
                total_matches: 27,
                showing: 10,
                items: Array.from({ length: 10 }, (_, i) => ({ id: i + 1, name: `Cliente ${i + 1}`, url: `/clientes/${i + 1}` })),
            },
        ]);

        expect(container.textContent).toContain('Mostrando 10 de 27 resultados.');
    });

    it('renders a credit_note_list item with its origin invoice number', () => {
        const container = renderAssistantComponents([
            {
                type: 'credit_note_list',
                items: [{
                    id: 7,
                    number: 'NC-000007',
                    invoice_number: 'FV-000042',
                    status_label: 'Emitida',
                    total: 19000,
                    url: '/notas-credito/7',
                    pdf_url: '/notas-credito/7/pdf',
                }],
            },
        ]);

        expect(container.textContent).toContain('NC-000007');
        expect(container.textContent).toContain('FV-000042');
    });

    it('renders a product_list item with price and taxes', () => {
        const container = renderAssistantComponents([
            {
                type: 'product_list',
                items: [{
                    id: 3,
                    sku: 'SKU-1',
                    name: 'Servicio de soporte',
                    price: 250000,
                    taxes: 'IVA19 19%',
                    url: '/productos-servicios/3',
                }],
            },
        ]);

        expect(container.textContent).toContain('Servicio de soporte');
        expect(container.textContent).toContain('IVA19 19%');
        expect(container.textContent).toContain('$250.000');
    });

    it('renders a metric_summary as label/value pairs', () => {
        const container = renderAssistantComponents([
            {
                type: 'metric_summary',
                title: 'Resumen de documentos',
                items: [
                    { label: 'Facturas totales', value: '12' },
                    { label: 'Clientes', value: '5' },
                ],
            },
        ]);

        expect(container.textContent).toContain('Resumen de documentos');
        expect(container.textContent).toContain('Facturas totales');
        expect(container.textContent).toContain('12');
    });

    it('renders a traceability_summary as an ordered list of events', () => {
        const container = renderAssistantComponents([
            {
                type: 'traceability_summary',
                invoice_number: 'FV-000002',
                total_events: 3,
                items: [
                    { date: '2026-10-01 09:00', description: 'Factura creada', type: 'created' },
                    { date: '2026-10-01 09:05', description: 'Factura validada localmente', type: 'local_validation_passed' },
                ],
            },
        ]);

        expect(container.querySelectorAll('li')).toHaveLength(2);
        expect(container.textContent).toContain('FV-000002');
        expect(container.textContent).toContain('Factura creada');
    });

    it('ignores an unknown component type without throwing', () => {
        expect(() => renderAssistantComponents([{ type: 'something_unexpected' }])).not.toThrow();
    });
});

describe('renderAssistantComponents — action_proposal (IA 2.0-D)', () => {
    const proposal = {
        type: 'action_proposal',
        action: 'create_invoice_draft',
        title: 'Borrador de factura',
        summary: 'Factura para Kata por $238.000.',
        token: 'abc123',
        fields: [{ label: 'Cliente', value: 'Kata' }],
        lines: [{ label: 'Camisa', detail: '×2', amount: '$238.000' }],
        totals: [{ label: 'Total', value: '$238.000' }],
        warnings: ['Esto es solo un ejemplo.'],
        note: 'Aún no se ha realizado ningún cambio.',
    };

    it('renders the title, summary, lines, totals and the "no change yet" notice', () => {
        const container = renderAssistantComponents([proposal]);

        expect(container.textContent).toContain('Borrador de factura');
        expect(container.textContent).toContain('Factura para Kata por $238.000.');
        expect(container.textContent).toContain('Camisa');
        expect(container.textContent).toContain('×2');
        expect(container.textContent).toContain('Total: $238.000');
        expect(container.textContent).toContain('Esto es solo un ejemplo.');
        expect(container.textContent).toContain('Aún no se ha realizado ningún cambio.');
    });

    it('carries the token as a data attribute, never inside visible text', () => {
        const container = renderAssistantComponents([proposal]);
        const card = container.querySelector('[data-token]');

        expect(card.dataset.token).toBe('abc123');
        expect(container.textContent).not.toContain('abc123');
    });

    it('renders exactly one Confirmar and one Cancelar button', () => {
        const container = renderAssistantComponents([proposal]);

        expect(container.querySelectorAll('[data-assistant-proposal-confirm]')).toHaveLength(1);
        expect(container.querySelectorAll('[data-assistant-proposal-cancel]')).toHaveLength(1);
        expect(container.querySelector('[data-assistant-proposal-confirm]').textContent).toBe('Confirmar');
        expect(container.querySelector('[data-assistant-proposal-cancel]').textContent).toBe('Cancelar');
    });

    it('still shows the "no change yet" notice even when no fields/lines/totals/warnings are present', () => {
        const container = renderAssistantComponents([{
            type: 'action_proposal',
            action: 'create_customer',
            title: 'Preparar cliente',
            summary: 'Cliente Juan (CC 12345)',
            token: 'xyz',
        }]);

        expect(container.textContent).toContain('Aún no se ha realizado ningún cambio.');
    });

    it('is visually distinct from a plain query-result card (primary-tinted border)', () => {
        const container = renderAssistantComponents([proposal]);
        const card = container.querySelector('[data-token]');

        expect(card.className).toContain('border-primary');
    });
});
