/**
 * superadmin-central.js — GeoDashboard CID
 * Super Admin (profil ID 4) uniquement.
 *
 * Comportement :
 *   - Masque TOUS les onglets du tableau de bord central GLPI
 *   - Conserve et active automatiquement l'onglet "Tableau de bord Metabase"
 *   - Masque également les blocs de contenu (planning, notes, etc.)
 */
(function () {
    'use strict';

    var KEYWORD = 'metabase';

    /* ── Masque tous les onglets sauf Metabase ── */
    function applyMetabaseMode() {
        /* 1. Onglets de navigation */
        var navLinks = document.querySelectorAll(
            '.nav-tabs .nav-link, .tab-navigate .nav-link'
        );
        if (!navLinks.length) return; // Pas encore rendu

        var metabaseLink = null;

        navLinks.forEach(function (link) {
            var text = link.textContent.trim().toLowerCase();
            var li   = link.closest('li') || link.closest('.nav-item') || link.parentElement;

            if (text.indexOf(KEYWORD) !== -1) {
                metabaseLink = link;
                li.style.setProperty('display', '', 'important');
            } else {
                li.style.setProperty('display', 'none', 'important');
            }
        });

        /* 2. Activer l'onglet Metabase si pas encore actif */
        if (metabaseLink && !metabaseLink.classList.contains('active')) {
            metabaseLink.click();
        }

        /* 3. Masquer les blocs de contenu non-Metabase visibles
              (planning, notes personnelles, notes publiques…)
              Ces blocs sont dans .tab-content > .tab-pane */
        var panes = document.querySelectorAll('.tab-content > .tab-pane');
        panes.forEach(function (pane) {
            /* Si ce panneau est actif ET ne contient pas de Metabase, le cacher */
            var hasMetabase = pane.querySelector('iframe[src*="' + KEYWORD + '"]') !== null
                           || pane.innerHTML.toLowerCase().indexOf(KEYWORD) !== -1;
            if (!hasMetabase && pane.classList.contains('active')) {
                pane.classList.remove('active', 'show');
            }
        });
    }

    /* ── Initialisation ── */
    function init() {
        applyMetabaseMode();           // Tentative immédiate
        setTimeout(applyMetabaseMode, 300);   // Après rendu Bootstrap
        setTimeout(applyMetabaseMode, 900);   // Après chargement Ajax GLPI
        setTimeout(applyMetabaseMode, 2000);  // Filet de sécurité
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
