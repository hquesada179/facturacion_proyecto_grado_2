import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { initChecklist } from './checklist.js';

/**
 * Coverage for the "Primeros pasos" dashboard widget. Hiding it is a
 * pure localStorage preference scoped per company — these tests never
 * touch the done/pending items themselves, since those are always
 * server-rendered from real data (OnboardingService::checklistFor()).
 */

function buildChecklist(companyId = '7') {
    document.body.innerHTML = `
        <div class="onboarding-checklist" data-onboarding-checklist data-company-id="${companyId}">
            <button type="button" data-checklist-hide>Ocultar</button>
            <button type="button" data-checklist-tutorial>Ver tutorial</button>
        </div>
    `;

    return document.querySelector('[data-onboarding-checklist]');
}

describe('OnboardingChecklist', () => {
    beforeEach(() => {
        window.localStorage.clear();
        document.body.innerHTML = '';
        // jsdom doesn't implement scrollIntoView at all; stub it once so
        // every card (including ones from earlier tests whose
        // fiscora:checklist:show listener is still registered on
        // `window`) has a harmless no-op instead of throwing.
        Element.prototype.scrollIntoView = () => {};
    });

    afterEach(() => {
        document.body.innerHTML = '';
        window.localStorage.clear();
    });

    it('is visible by default', () => {
        const card = buildChecklist();
        initChecklist();

        expect(card.classList.contains('hidden-state')).toBe(false);
    });

    it('hides on click and persists the preference in localStorage, scoped per company', () => {
        const card = buildChecklist('7');
        initChecklist();

        card.querySelector('[data-checklist-hide]').click();

        expect(card.classList.contains('hidden-state')).toBe(true);
        expect(window.localStorage.getItem('fiscora.checklist.hidden.7')).toBe('true');
    });

    it('a hidden preference from a previous visit is respected on load', () => {
        window.localStorage.setItem('fiscora.checklist.hidden.7', 'true');
        const card = buildChecklist('7');
        initChecklist();

        expect(card.classList.contains('hidden-state')).toBe(true);
    });

    it('hiding for one company never hides it for another', () => {
        window.localStorage.setItem('fiscora.checklist.hidden.7', 'true');
        const card = buildChecklist('9');
        initChecklist();

        expect(card.classList.contains('hidden-state')).toBe(false);
    });

    it('the help center "Primeros pasos" event restores a hidden checklist and scrolls to it', () => {
        window.localStorage.setItem('fiscora.checklist.hidden.7', 'true');
        const card = buildChecklist('7');
        card.scrollIntoView = () => {};
        initChecklist();

        window.dispatchEvent(new CustomEvent('fiscora:checklist:show'));

        expect(card.classList.contains('hidden-state')).toBe(false);
        expect(window.localStorage.getItem('fiscora.checklist.hidden.7')).toBe('false');
    });

    it('"Ver tutorial" dispatches the real tour-start event', () => {
        const card = buildChecklist();
        initChecklist();

        let received = false;
        window.addEventListener('fiscora:tour:start', () => {
            received = true;
        });

        card.querySelector('[data-checklist-tutorial]').click();

        expect(received).toBe(true);
    });

    it('never throws when the checklist is not on the page (e.g. already 100% and removed, or a non-dashboard page)', () => {
        document.body.innerHTML = '<div>Sin checklist</div>';

        expect(() => initChecklist()).not.toThrow();
        expect(() => window.dispatchEvent(new CustomEvent('fiscora:checklist:show'))).not.toThrow();
    });
});
