/* ==========================================================================
   School ERP — Centralized animation system (ERPAnim)
   --------------------------------------------------------------------------
   Design principles (safe progressive enhancement):
   - Content is VISIBLE BY DEFAULT (CSS opacity:1). Animations only enhance.
   - If GSAP is unavailable OR the user prefers reduced motion, every method
     is a no-op and content simply appears — it is NEVER left hidden.
   - All entrance tweens use clearProps:'all' so no residual inline opacity/
     transform remains after completion.
   - A safety sweep runs shortly after load and forces any element that was
     accidentally left with opacity < 1 (e.g. an interrupted tween) back to
     fully visible. Content can therefore never disappear because of an
     animation failure.
   ========================================================================== */
(function (window, document) {
    'use strict';

    var hasGsap = typeof window.gsap !== 'undefined';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var enabled = hasGsap && !reduce;

    function nodes(target) {
        if (!target) { return []; }
        if (typeof target === 'string') { return Array.prototype.slice.call(document.querySelectorAll(target)); }
        if (target.length !== undefined) { return Array.prototype.slice.call(target); }
        return [target];
    }

    var ERPAnim = {
        enabled: enabled,

        /** Fade + rise a set of cards with a subtle stagger. Safe no-op otherwise. */
        staggerCards: function (target, opts) {
            var els = nodes(target);
            if (!enabled || !els.length) { return; }
            opts = opts || {};
            window.gsap.from(els, {
                y: opts.y != null ? opts.y : 16,
                opacity: 0,
                duration: opts.duration || 0.4,
                stagger: opts.stagger || 0.05,
                ease: 'power2.out',
                clearProps: 'all',
                overwrite: 'auto'
            });
        },

        /** Fade + rise a single element (or selector). */
        fadeUp: function (target, opts) {
            var els = nodes(target);
            if (!enabled || !els.length) { return; }
            opts = opts || {};
            window.gsap.from(els, {
                y: opts.y != null ? opts.y : 20,
                opacity: 0,
                duration: opts.duration || 0.5,
                delay: opts.delay || 0,
                ease: 'power2.out',
                clearProps: 'all',
                overwrite: 'auto'
            });
        },

        /** Slide/scale a panel in from the side (e.g. login brand/form). */
        pageEnter: function (target) {
            this.fadeUp(target, { y: 18, duration: 0.5 });
        },

        /** Modal open/close (element must be visible; we animate the inner box). */
        modalOpen: function (box) {
            var els = nodes(box);
            if (!enabled || !els.length) { return; }
            window.gsap.from(els, { y: 12, opacity: 0, scale: 0.98, duration: 0.2, ease: 'power2.out', clearProps: 'all' });
        },

        /** Toast enter/exit helpers (return promise-like via onComplete). */
        toastEnter: function (el) {
            if (!enabled || !el) { return; }
            window.gsap.from(el, { x: 40, opacity: 0, duration: 0.3, ease: 'power2.out', clearProps: 'all' });
        },
        toastExit: function (el, done) {
            if (!enabled || !el) { if (done) { done(); } return; }
            window.gsap.to(el, { x: 40, opacity: 0, duration: 0.3, ease: 'power2.in', onComplete: done });
        },

        /** Animate a number counting up (dashboard KPIs). Safe: sets final value if disabled. */
        counter: function (el, to, opts) {
            if (!el) { return; }
            to = Number(to) || 0;
            if (!enabled) { el.textContent = to.toLocaleString(); return; }
            opts = opts || {};
            var obj = { v: 0 };
            window.gsap.to(obj, {
                v: to, duration: opts.duration || 0.8, ease: 'power1.out',
                onUpdate: function () { el.textContent = Math.round(obj.v).toLocaleString(); },
                onComplete: function () { el.textContent = to.toLocaleString(); }
            });
        },

        /**
         * Safety net: guarantee nothing is left invisible by a failed/interrupted
         * animation. Any element with computed opacity ~0 that is not intentionally
         * hidden (display:none / [hidden] / .hidden) is restored to opacity:1.
         */
        safetySweep: function () {
            var candidates = document.querySelectorAll('[data-animate-card], [data-animate], [data-animate] > *');
            Array.prototype.forEach.call(candidates, function (el) {
                if (el.offsetParent === null) { return; } // genuinely hidden (display:none)
                var op = parseFloat(window.getComputedStyle(el).opacity);
                if (!isNaN(op) && op < 0.05) {
                    el.style.opacity = '1';
                    el.style.transform = 'none';
                }
            });
        }
    };

    window.ERPAnim = ERPAnim;

    // Run the safety sweep after animations should have finished, and again on
    // full window load (covers slow CDN / interrupted tweens / hidden tabs).
    function sweepSoon() { setTimeout(function () { ERPAnim.safetySweep(); }, 1500); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sweepSoon);
    } else {
        sweepSoon();
    }
    window.addEventListener('load', function () { setTimeout(function () { ERPAnim.safetySweep(); }, 300); });
})(window, document);
