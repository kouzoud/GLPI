/**
 * modal-fix.js — Plugin monplugin / CID
 *
 * PROBLÈME :
 *   GLPI fait $(modal).appendTo($("body")) pour les modaux de dropdowns
 *   et d'images, MAIS PAS pour le modal #modal_password_*.
 *   Ce modal reste donc enfoncé dans le DOM du formulaire :
 *     .tab-content > form > .card-body > ... > .modal
 *
 *   Conséquence : même avec position:fixed, le navigateur calcule
 *   la position du modal par rapport au premier ancêtre transformé
 *   (CSS transform / will-change / filter) au lieu du viewport.
 *   Le modal s'affiche alors mal positionné ou invisible.
 *
 * SOLUTION :
 *   Déplacer TOUS les modaux de mot de passe vers <body> dès que
 *   la page est chargée, et surveiller les contenus chargés en AJAX
 *   (tabs GLPI) avec un MutationObserver.
 */

(function () {
    'use strict';

    /**
     * Déplace un modal vers <body> s'il n'y est pas déjà.
     * @param {Element} modal
     */
    function moveModalToBody(modal) {
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    }

    /**
     * Recherche et déplace tous les modaux de mot de passe
     * présents dans un nœud racine donné.
     * @param {Element|Document} root
     */
    function fixPasswordModals(root) {
        // Cible : #modal_password_* (id commence par "modal_password_")
        root.querySelectorAll('[id^="modal_password_"]').forEach(moveModalToBody);
    }

    /* ── Lancement initial après chargement du DOM ── */
    document.addEventListener('DOMContentLoaded', function () {
        fixPasswordModals(document);

        /*
         * MutationObserver — surveille les ajouts AJAX de GLPI.
         * Quand l'utilisateur clique sur un onglet (Utilisateur, Sécurité…),
         * GLPI injecte du HTML via XHR. On scanne chaque nouveau nœud.
         */
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return; // nœuds texte ignorés
                    // Le nœud ajouté lui-même peut être un modal
                    if (/^modal_password_/.test(node.id)) {
                        moveModalToBody(node);
                    }
                    // Ou il peut contenir des modaux imbriqués
                    fixPasswordModals(node);
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });

})();
