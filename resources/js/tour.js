/**
 * Product tour — 7 steps spanning real, separate pages (dashboard, then
 * four pages inside the real invoice-creation wizard, then the real
 * invoice show page). There is no SPA here: the only thing that can
 * survive a full page navigation is sessionStorage, so that's the only
 * state this module keeps (per the task's own preference — purely
 * visual, no backend write, nothing to desync across devices).
 *
 * Resilience is the whole design: on every page load this module scans
 * the CURRENT page for whichever `[data-tour-target]` exists, in step
 * order, and shows that step. A page with no matching target is simply
 * a no-op (tour stays "active" in storage, nothing renders) — never a
 * thrown error. The user actually advances the tour by clicking the
 * real, already-spotlighted control (the real "Nueva factura" button,
 * the real "Continuar a productos" link, etc.); "Siguiente" only moves
 * between steps that both exist on the SAME page (today: steps 1 and 2,
 * both on the dashboard).
 */

const ACTIVE_KEY = 'fiscora.tour.active';
const STEP_KEY = 'fiscora.tour.step';

const TOUR = [
    {
        target: 'dashboard-main',
        title: 'Este es tu panel principal',
        text: 'Aquí puedes consultar facturas, borradores, errores, notas crédito y actividad reciente.',
        center: true,
    },
    {
        target: 'new-invoice',
        title: 'Nueva factura',
        text: 'Desde aquí comienza el flujo de facturación.',
        place: 'bottom',
    },
    {
        target: 'invoice-customer',
        title: 'Cliente',
        text: 'Selecciona un cliente existente, crea uno nuevo o utiliza consumidor final.',
        place: 'right',
    },
    {
        target: 'invoice-items',
        title: 'Productos y servicios',
        text: 'Agrega los productos o servicios que formarán parte del documento.',
        place: 'right',
    },
    {
        target: 'invoice-summary',
        title: 'Resumen y totales',
        text: 'Revisa los valores antes de continuar.',
        place: 'right',
    },
    {
        target: 'invoice-validation',
        title: 'Validación',
        text: 'Fiscora revisa bloqueos y advertencias antes de la emisión simulada.',
        place: 'right',
    },
    {
        target: 'invoice-document',
        title: 'Documento y trazabilidad',
        text: 'Puedes consultar el documento, descargar su representación y revisar su historial.',
        place: 'right',
    },
];

function prefersReducedMotion() {
    try {
        return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (error) {
        return false;
    }
}

function isActive() {
    try {
        return window.sessionStorage.getItem(ACTIVE_KEY) === '1';
    } catch (error) {
        return false;
    }
}

function setActive(active) {
    try {
        if (active) {
            window.sessionStorage.setItem(ACTIVE_KEY, '1');
        } else {
            window.sessionStorage.removeItem(ACTIVE_KEY);
            window.sessionStorage.removeItem(STEP_KEY);
        }
    } catch (error) {
        // Storage unavailable — the tour simply won't survive a page
        // navigation; it still works for the current page.
    }
}

function getStoredStep() {
    try {
        const raw = window.sessionStorage.getItem(STEP_KEY);
        const parsed = raw === null ? 0 : Number(raw);

        return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
    } catch (error) {
        return 0;
    }
}

function setStoredStep(index) {
    try {
        window.sessionStorage.setItem(STEP_KEY, String(index));
    } catch (error) {
        // No-op — see setActive().
    }
}

function targetElement(step) {
    try {
        return document.querySelector(`[data-tour-target="${step.target}"]`);
    } catch (error) {
        return null;
    }
}

/**
 * First step at or after `from` whose target exists on this page; if
 * none, falls back to scanning the whole tour from the start (covers
 * the user navigating "backwards" to an earlier page while the tour is
 * active). Returns -1 if nothing on this page matches any step at all —
 * the caller treats that as "stay quiet, don't render anything".
 */
function findStepIndexForThisPage(from) {
    for (let i = from; i < TOUR.length; i += 1) {
        if (targetElement(TOUR[i])) {
            return i;
        }
    }

    for (let i = 0; i < from; i += 1) {
        if (targetElement(TOUR[i])) {
            return i;
        }
    }

    return -1;
}

let scrimEl = null;
let spotlightEl = null;
let popoverEl = null;
let currentIndex = -1;
let rafHandle = null;

function ensureOverlay() {
    if (scrimEl) {
        return;
    }

    scrimEl = document.createElement('div');
    scrimEl.className = 'tour-scrim';

    spotlightEl = document.createElement('div');
    spotlightEl.className = 'tour-spotlight';
    spotlightEl.style.opacity = '0';

    popoverEl = document.createElement('div');
    popoverEl.className = 'tour-popover';
    popoverEl.setAttribute('role', 'dialog');
    popoverEl.setAttribute('aria-live', 'polite');
    popoverEl.setAttribute('aria-label', 'Tutorial de Fiscora');

    scrimEl.appendChild(spotlightEl);
    document.body.appendChild(scrimEl);
    document.body.appendChild(popoverEl);
}

function removeOverlay() {
    scrimEl?.remove();
    popoverEl?.remove();
    scrimEl = null;
    spotlightEl = null;
    popoverEl = null;
    currentIndex = -1;

    if (rafHandle) {
        cancelAnimationFrame(rafHandle);
        rafHandle = null;
    }
}

function positionFor(index) {
    const step = TOUR[index];
    const el = targetElement(step);

    if (!el || step.center) {
        if (spotlightEl) {
            spotlightEl.style.opacity = '0';
        }

        if (popoverEl) {
            popoverEl.style.top = '50%';
            popoverEl.style.left = '50%';
            popoverEl.style.transform = 'translate(-50%, -50%)';
        }

        return;
    }

    const rect = el.getBoundingClientRect();
    const pad = 6;

    spotlightEl.style.opacity = '1';
    spotlightEl.style.top = `${rect.top - pad}px`;
    spotlightEl.style.left = `${rect.left - pad}px`;
    spotlightEl.style.width = `${rect.width + pad * 2}px`;
    spotlightEl.style.height = `${rect.height + pad * 2}px`;

    const popoverWidth = 300;
    let top = rect.bottom + 16;
    let left = Math.max(16, rect.left);

    if (step.place === 'right') {
        top = rect.top;
        left = rect.right + 18;

        if (left + popoverWidth > window.innerWidth - 16) {
            left = Math.max(16, rect.left - popoverWidth - 18);
        }
    }

    if (top + 220 > window.innerHeight) {
        top = Math.max(16, rect.top - 220);
    }

    popoverEl.style.transform = 'none';
    popoverEl.style.top = `${top}px`;
    popoverEl.style.left = `${left}px`;
}

function reposition() {
    if (currentIndex < 0 || !popoverEl) {
        return;
    }

    if (rafHandle) {
        return;
    }

    rafHandle = requestAnimationFrame(() => {
        rafHandle = null;
        positionFor(currentIndex);
    });
}

function renderPopoverContent(index) {
    const step = TOUR[index];
    const total = TOUR.length;
    const canGoPrevOnPage = index > 0 && !!targetElement(TOUR[index - 1]);
    const canGoNextOnPage = index < total - 1 && !!targetElement(TOUR[index + 1]);
    const isLast = index === total - 1;

    popoverEl.innerHTML = `
        <div class="tour-popover-top">
            <span class="tour-popover-progress">${index + 1}/${total}</span>
            <button type="button" class="tour-popover-skip" data-tour-skip>Saltar tutorial</button>
        </div>
        <p class="tour-popover-title">${step.title}</p>
        <p class="tour-popover-text">${step.text}</p>
        <div class="tour-popover-bar"><span style="width:${Math.round(((index + 1) / total) * 100)}%"></span></div>
        <div class="tour-popover-nav">
            <button type="button" class="tour-popover-btn ghost" data-tour-prev ${canGoPrevOnPage ? '' : 'disabled'}>
                <span class="material-symbols-outlined">chevron_left</span>
                Anterior
            </button>
            <button type="button" class="tour-popover-btn primary" data-tour-next>
                ${isLast ? 'Finalizar' : 'Siguiente'}
                <span class="material-symbols-outlined">chevron_right</span>
            </button>
        </div>
    `;

    popoverEl.querySelector('[data-tour-skip]')?.addEventListener('click', skip);
    popoverEl.querySelector('[data-tour-prev]')?.addEventListener('click', () => {
        if (canGoPrevOnPage) {
            showStep(index - 1);
        }
    });
    popoverEl.querySelector('[data-tour-next]')?.addEventListener('click', () => {
        if (isLast) {
            finish();
        } else if (canGoNextOnPage) {
            showStep(index + 1);
        } else {
            // The next step lives on a different real page — the user
            // advances by using the real, spotlighted control. Just
            // dismiss this step's overlay so it doesn't sit on top of
            // the page while they do that.
            removeOverlay();
        }
    });

    // Soft focus management, never a trap: keyboard users land on the
    // primary action immediately, but can still Tab out to the real
    // page underneath (no focus-trap listeners are attached).
    popoverEl.querySelector('[data-tour-next]')?.focus();
}

function showStep(index) {
    ensureOverlay();
    currentIndex = index;
    setStoredStep(index);
    positionFor(index);
    renderPopoverContent(index);
}

function finish() {
    removeOverlay();
    setActive(false);
    showDoneCard();
}

function skip() {
    removeOverlay();
    setActive(false);
}

let doneCardEl = null;

function showDoneCard() {
    doneCardEl?.remove();

    doneCardEl = document.createElement('div');
    doneCardEl.className = 'tour-done-card';
    doneCardEl.setAttribute('role', 'dialog');
    doneCardEl.setAttribute('aria-label', 'Tutorial completado');
    doneCardEl.innerHTML = `
        <div class="tour-done-icon"><span class="material-symbols-outlined">rocket_launch</span></div>
        <p class="tour-done-title">¡Listo! Ya conoces el flujo principal de Fiscora.</p>
        <p class="tour-done-sub">Puedes repetir el tutorial cuando quieras desde el Centro de ayuda.</p>
        <div class="tour-done-actions">
            <button type="button" class="tour-popover-btn primary" data-tour-done-dashboard>Ir al dashboard</button>
            <button type="button" class="tour-popover-btn ghost" data-tour-done-close>Cerrar</button>
        </div>
    `;

    document.body.appendChild(doneCardEl);

    doneCardEl.querySelector('[data-tour-done-dashboard]')?.addEventListener('click', () => {
        window.location.href = '/dashboard';
    });
    doneCardEl.querySelector('[data-tour-done-close]')?.addEventListener('click', () => {
        doneCardEl?.remove();
        doneCardEl = null;
    });
}

function renderForCurrentPage() {
    if (!isActive()) {
        removeOverlay();

        return;
    }

    const index = findStepIndexForThisPage(getStoredStep());

    if (index === -1) {
        // Nothing on this page matches any step — stay quiet, tour
        // remains active for whichever page the user visits next.
        removeOverlay();

        return;
    }

    showStep(index);
}

export function startTour() {
    setActive(true);
    setStoredStep(0);
    renderForCurrentPage();
}

export function initTour() {
    // Auto-start when redirected here with ?start_tour=1 (set by
    // OnboardingController::complete when the user picks "Comenzar
    // tutorial"). Cleaned from the URL immediately so a later manual
    // reload of the same URL doesn't re-trigger it.
    const url = new URL(window.location.href);

    if (url.searchParams.get('start_tour') === '1') {
        url.searchParams.delete('start_tour');
        window.history.replaceState({}, '', url.toString());
        startTour();
    } else {
        renderForCurrentPage();
    }

    window.addEventListener('fiscora:tour:start', startTour);

    if (!prefersReducedMotion()) {
        window.addEventListener('resize', reposition, { passive: true });
        window.addEventListener('scroll', reposition, { passive: true });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && currentIndex >= 0) {
            skip();
        }
    });
}
