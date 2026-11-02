/**
 * Public landing page only — never imported by assistant.js,
 * notifications.js or theme.js, and nothing here touches the
 * authenticated app. Three small, independent, transform/opacity-only
 * enhancements:
 *
 *   - initLandingReveals: fade+rise sections in as they scroll into view.
 *   - initLandingNav: solidifies the navbar background once the page has
 *     scrolled past the hero.
 *   - initLandingTilt: a subtle 3D tilt on the hero's dashboard mockup
 *     that follows the pointer, desktop/hover-capable only.
 *
 * Every one of them degrades safely: if this module never runs at all
 * (blocked, failed to load), app.css never hides `[data-reveal]` content
 * in the first place (see the `.js` scope there), the nav keeps its
 * default transparent-over-hero look, and the mockup keeps the static
 * tilt already set in CSS. The FAQ accordion isn't handled here at all —
 * it's native <details>/<summary>, which opens and closes without any
 * JS and can't end up in a broken half-open state.
 */

function prefersReducedMotion() {
    try {
        return typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (error) {
        return false;
    }
}

export function initLandingReveals() {
    const elements = document.querySelectorAll('[data-reveal]');

    if (elements.length === 0) {
        return;
    }

    if (prefersReducedMotion() || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });

    elements.forEach((element) => observer.observe(element));
}

export function initLandingNav() {
    const nav = document.querySelector('[data-landing-nav]');

    if (!nav) {
        return;
    }

    let ticking = false;

    const applyScrollState = () => {
        nav.classList.toggle('is-scrolled', window.scrollY > 24);
        ticking = false;
    };

    applyScrollState();

    window.addEventListener('scroll', () => {
        if (ticking) {
            return;
        }

        ticking = true;
        window.requestAnimationFrame(applyScrollState);
    }, { passive: true });
}

export function initLandingTilt() {
    const stage = document.querySelector('[data-landing-tilt-stage]');
    const target = document.querySelector('[data-landing-tilt-target]');

    if (!stage || !target || prefersReducedMotion()) {
        return;
    }

    // Touch devices have no stable pointer position to tilt toward, and
    // forcing it there would just make the mockup jitter under a finger
    // — the task explicitly asks for desktop-only.
    const canHover = typeof window.matchMedia === 'function'
        && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    if (!canHover) {
        return;
    }

    let frame = null;

    stage.addEventListener('mousemove', (event) => {
        if (frame) {
            return;
        }

        frame = window.requestAnimationFrame(() => {
            const rect = stage.getBoundingClientRect();
            const px = (event.clientX - rect.left) / rect.width - 0.5;
            const py = (event.clientY - rect.top) / rect.height - 0.5;

            // Moderate range (±5deg/±4deg around the resting -7/2deg tilt
            // already set in CSS) — a tilt, not a spin.
            target.style.transform = `rotateY(${-7 + px * 10}deg) rotateX(${2 - py * 8}deg)`;
            frame = null;
        });
    });

    stage.addEventListener('mouseleave', () => {
        target.style.transform = '';
    });
}
