/**
 * "Primeros pasos" checklist — hiding it is a pure client-side
 * preference (localStorage, keyed per company so a shared browser never
 * hides it for the wrong account), never the server-side onboarding
 * completion state. Every item's done/pending state is rendered by the
 * server from real data (see OnboardingService::checklistFor()); this
 * module never recomputes or fakes progress, it only shows/hides the
 * card and reacts to the help center's "Primeros pasos" action.
 */

const STORAGE_PREFIX = 'fiscora.checklist.hidden.';

function storageKeyFor(companyId) {
    return `${STORAGE_PREFIX}${companyId || 'anon'}`;
}

function isHidden(companyId) {
    try {
        return window.localStorage.getItem(storageKeyFor(companyId)) === 'true';
    } catch (error) {
        return false;
    }
}

function setHidden(companyId, hidden) {
    try {
        window.localStorage.setItem(storageKeyFor(companyId), hidden ? 'true' : 'false');
    } catch (error) {
        // Private browsing / storage disabled — the card simply won't
        // stay hidden across reloads, same documented fallback used by
        // the theme/sidebar-collapse preferences.
    }
}

export function initChecklist() {
    const card = document.querySelector('[data-onboarding-checklist]');

    if (!card || card.dataset.checklistInitialized === 'true') {
        return;
    }

    card.dataset.checklistInitialized = 'true';

    const companyId = card.dataset.companyId || '';
    const hideButton = card.querySelector('[data-checklist-hide]');
    const tutorialButton = card.querySelector('[data-checklist-tutorial]');

    function applyVisibility() {
        card.classList.toggle('hidden-state', isHidden(companyId));
    }

    applyVisibility();

    hideButton?.addEventListener('click', () => {
        setHidden(companyId, true);
        applyVisibility();
    });

    tutorialButton?.addEventListener('click', () => {
        window.dispatchEvent(new CustomEvent('fiscora:tour:start'));
    });

    window.addEventListener('fiscora:checklist:show', () => {
        setHidden(companyId, false);
        applyVisibility();
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}
