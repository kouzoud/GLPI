/**
 * superadmin-central.js — CID Plugin
 * Global — tous les profils.
 *
 * Comportement :
 *   - Masque les onglets "Flux RSS" et "Tous"
 *   - Tous les autres onglets restent visibles et accessibles
 */
(function () {
    'use strict';

    /* Onglets à masquer (texte en minuscules) */
    var HIDE_TABS = ['flux rss', 'tous'];

    /* ── Cache les onglets Flux RSS et Tous ── */
    function hideTabs() {
        var navLinks = document.querySelectorAll(
            '.nav-tabs .nav-link, .tab-navigate .nav-link'
        );
        if (!navLinks.length) return; // DOM pas encore prêt

        navLinks.forEach(function (link) {
            var text = link.textContent.trim().toLowerCase();
            var li   = link.closest('li') || link.closest('.nav-item') || link.parentElement;

            var shouldHide = HIDE_TABS.some(function (kw) {
                return text === kw || text.indexOf(kw) !== -1;
            });

            if (shouldHide) {
                li.style.setProperty('display', 'none', 'important');
            }
        });
    }

    /* ── Initialisation ── */
    function init() {
        hideTabs();
        setTimeout(hideTabs, 300);   // Après rendu Bootstrap
        setTimeout(hideTabs, 900);   // Après chargement Ajax GLPI
        setTimeout(hideTabs, 2000);  // Filet de sécurité
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
