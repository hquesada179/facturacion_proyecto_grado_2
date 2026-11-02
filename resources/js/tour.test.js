import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initTour, startTour } from './tour.js';

/**
 * Coverage for the multi-page product tour. Real navigation between
 * pages can't be simulated in jsdom, so "survives navigation" is tested
 * the way it actually works: sessionStorage keeps { active, step }, and
 * a fresh initTour() call (equivalent to a new page load) re-scans
 * whatever [data-tour-target] exists in the current document.
 */

function stubRect(el) {
    el.getBoundingClientRect = () => ({ top: 100, left: 100, right: 200, bottom: 140, width: 100, height: 40 });
}

function buildDashboardPage() {
    document.body.innerHTML = `
        <div data-tour-target="dashboard-main">Dashboard</div>
        <button data-tour-target="new-invoice">Nueva factura</button>
    `;
    document.querySelectorAll('[data-tour-target]').forEach(stubRect);
}

function buildInvoiceCustomerPage() {
    document.body.innerHTML = `
        <section data-tour-target="invoice-customer">Cliente</section>
    `;
    document.querySelectorAll('[data-tour-target]').forEach(stubRect);
}

describe('ProductTour', () => {
    beforeEach(() => {
        window.sessionStorage.clear();
        document.body.innerHTML = '';
    });

    afterEach(() => {
        document.body.innerHTML = '';
        document.querySelectorAll('.tour-scrim, .tour-popover, .tour-done-card').forEach((el) => el.remove());
        window.sessionStorage.clear();
        vi.restoreAllMocks();
    });

    it('does nothing when the tour was never started', () => {
        buildDashboardPage();
        initTour();

        expect(document.querySelector('.tour-popover')).toBeNull();
    });

    it('renders the first matching step and marks the tour active', () => {
        buildDashboardPage();
        initTour();
        startTour();

        expect(window.sessionStorage.getItem('fiscora.tour.active')).toBe('1');
        expect(document.querySelector('.tour-popover')).not.toBeNull();
        expect(document.querySelector('.tour-popover-title').textContent).toBe('Este es tu panel principal');
        expect(document.querySelector('.tour-popover-progress').textContent).toBe('1/7');
    });

    it('moves to the next step when it exists on the same page', () => {
        buildDashboardPage();
        initTour();
        startTour();

        document.querySelector('[data-tour-next]').click();

        expect(document.querySelector('.tour-popover-title').textContent).toBe('Nueva factura');
        expect(document.querySelector('.tour-popover-progress').textContent).toBe('2/7');
    });

    it('moving back and forward between same-page steps keeps the right content', () => {
        buildDashboardPage();
        initTour();
        startTour();

        document.querySelector('[data-tour-next]').click();
        document.querySelector('[data-tour-prev]').click();

        expect(document.querySelector('.tour-popover-title').textContent).toBe('Este es tu panel principal');
    });

    it('clicking "Siguiente" when the next step lives on another page just dismisses the overlay without throwing', () => {
        buildDashboardPage();
        initTour();
        startTour();
        document.querySelector('[data-tour-next]').click(); // now on step 2 (new-invoice)

        expect(() => document.querySelector('[data-tour-next]').click()).not.toThrow();
        expect(document.querySelector('.tour-popover')).toBeNull();
        // Tour stays active in storage — the next real page will pick it up.
        expect(window.sessionStorage.getItem('fiscora.tour.active')).toBe('1');
    });

    it('resuming on a different real page (simulated reload) finds the matching step automatically', () => {
        buildDashboardPage();
        initTour();
        startTour();
        document.querySelector('[data-tour-next]').click(); // step 2
        document.querySelector('[data-tour-next]').click(); // dismissed, still active

        // Simulate the browser navigating to the real customer-selection page.
        buildInvoiceCustomerPage();
        initTour();

        expect(document.querySelector('.tour-popover-title').textContent).toBe('Cliente');
        expect(document.querySelector('.tour-popover-progress').textContent).toBe('3/7');
    });

    it('never throws when the current page has no matching tour target at all', () => {
        document.body.innerHTML = '<div>Página sin ningún target del tour</div>';
        initTour();

        expect(() => startTour()).not.toThrow();
        expect(document.querySelector('.tour-popover')).toBeNull();
        // Still marked active — a later page with a real target will show it.
        expect(window.sessionStorage.getItem('fiscora.tour.active')).toBe('1');
    });

    it('"Saltar tutorial" clears the active state and removes the overlay', () => {
        buildDashboardPage();
        initTour();
        startTour();

        document.querySelector('[data-tour-skip]').click();

        expect(document.querySelector('.tour-popover')).toBeNull();
        expect(window.sessionStorage.getItem('fiscora.tour.active')).toBeNull();
    });

    it('finishing the last step shows the done card and clears the active state', () => {
        document.body.innerHTML = `<div data-tour-target="invoice-document">Documento</div>`;
        stubRect(document.querySelector('[data-tour-target]'));
        window.sessionStorage.setItem('fiscora.tour.active', '1');
        window.sessionStorage.setItem('fiscora.tour.step', '6');
        initTour();

        document.querySelector('[data-tour-next]').click();

        expect(document.querySelector('.tour-done-card')).not.toBeNull();
        expect(window.sessionStorage.getItem('fiscora.tour.active')).toBeNull();
    });

    it('the help center "Ver tutorial" event starts the tour', () => {
        buildDashboardPage();
        initTour();

        window.dispatchEvent(new CustomEvent('fiscora:tour:start'));

        expect(document.querySelector('.tour-popover')).not.toBeNull();
    });

    it('Escape skips an active tour', () => {
        buildDashboardPage();
        initTour();
        startTour();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(document.querySelector('.tour-popover')).toBeNull();
    });

    it('auto-starts from ?start_tour=1 and cleans the URL', () => {
        window.history.pushState({}, '', '/dashboard?start_tour=1');
        buildDashboardPage();
        initTour();

        expect(document.querySelector('.tour-popover')).not.toBeNull();
        expect(window.location.search).not.toContain('start_tour');

        window.history.pushState({}, '', '/dashboard');
    });

    it('resize/scroll never throw while a step is being shown', () => {
        buildDashboardPage();
        initTour();
        startTour();

        expect(() => {
            window.dispatchEvent(new Event('resize'));
            window.dispatchEvent(new Event('scroll'));
        }).not.toThrow();
    });
});
