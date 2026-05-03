/**
 * effects.js — Micro-interactions JS pour monplugin
 * Plugin : monplugin — Charte CID
 *
 * Fonctionnalités :
 *   1. Animation compteur (data-counter)
 *   2. Observation d'intersection pour animations au scroll
 */

(function () {
    'use strict';

    // Respecter prefers-reduced-motion
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* === 1. COUNTER ANIMATION === */
    function animateCounter(el) {
        if (prefersReduced) {
            el.classList.add('done');
            return;
        }

        const target = parseInt(el.textContent.replace(/\s/g, ''), 10);
        if (isNaN(target) || target === 0) {
            el.classList.add('done');
            return;
        }

        el.setAttribute('data-counter', target);
        el.classList.add('counting');
        el.textContent = '0';

        const duration = 800; // ms
        const start = performance.now();

        function step(now) {
            const progress = Math.min((now - start) / duration, 1);
            // easeOutQuart
            const ease = 1 - Math.pow(1 - progress, 4);
            el.textContent = Math.round(target * ease).toLocaleString('fr-FR');

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString('fr-FR');
                el.classList.remove('counting');
                el.classList.add('done');
            }
        }

        requestAnimationFrame(step);
    }

    /* === 2. INTERSECTION OBSERVER — Animation au scroll === */
    function initScrollAnimations() {
        if (prefersReduced) return;

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.card, .table').forEach(function (el) {
            observer.observe(el);
        });
    }

    /* === 3. INITIALISATION === */
    function init() {
        // Compteurs — cibler les éléments avec data-counter ou les chiffres de stats
        document.querySelectorAll('[data-counter]').forEach(animateCounter);

        // Aussi animer les compteurs dans les nav-tabs stats (GLPI)
        document.querySelectorAll(
            '.search_config_top .nav-link strong, ' +
            '.tab-navigate .nav-link strong, ' +
            '[id*="tabspanel"] .nav-tabs .nav-link strong'
        ).forEach(function (el) {
            if (!el.hasAttribute('data-counter') && /^\d+$/.test(el.textContent.trim())) {
                animateCounter(el);
            }
        });

        initScrollAnimations();
    }

    // Lancer à la fin du chargement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
