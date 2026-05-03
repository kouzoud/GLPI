<?php
/**
 * hook.php — Injection CSS + JS par profil GLPI
 * Plugin : monplugin — Charte CID
 *
 * Profils gérés :
 *   ID 4 → Super-Admin   → superadmin.css + effects.js
 *   ID 6 → Technicien    → technicien.css + effects.js
 *   ID 1 → Employé       → employe.css + effects.js
 *
 * Login : hook display_login → login.css (injection directe via echo)
 */

function plugin_monplugin_inject_css() {
    global $PLUGIN_HOOKS;

    // Sécurité : session active avec profil défini
    if (!isset($_SESSION['glpiactiveprofile']['id'])) {
        return;
    }

    $profile_id = (int) $_SESSION['glpiactiveprofile']['id'];
    $version    = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '1.7.1';

    // Mapping profil → fichier CSS
    $profile_css_map = [
        4 => 'css/superadmin.css',   // Super-Admin
        6 => 'css/technicien.css',   // Technicien
        1 => 'css/employe.css',      // Employé / Self-Service
    ];

    if (isset($profile_css_map[$profile_id])) {
        $css_file = $profile_css_map[$profile_id];
        $PLUGIN_HOOKS['add_css']['monplugin'] = [$css_file];

        // Injection du JS d'effets pour tous les profils reconnus
        $js_file = 'js/effects.js';
        $PLUGIN_HOOKS['add_javascript']['monplugin'] = [$js_file];
    }

    // === DASHBOARD GÉOGRAPHIQUE — Assets locaux ===
    // Styles personnalisés de la carte (s'appliquent à tous les profils)
    $PLUGIN_HOOKS['add_css']['monplugin'][] = 'css/map-style.css';

    // Logique Leaflet (s'applique à tous les profils)
    $PLUGIN_HOOKS['add_javascript']['monplugin'][] = 'js/leaflet-logic.js';
    // Aucun profil reconnu → pas de CSS/JS injecté (fallback propre)
}

/**
 * Hook display_login — Injection du CSS sur la page de login
 * Appelé automatiquement par GLPI avant l'affichage du formulaire de connexion
 */
function plugin_monplugin_display_login() {
    $version  = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '1.7.1';
    $base_url = Plugin::getWebDir('monplugin', true, true);

    echo '<link rel="stylesheet" type="text/css" href="'
        . htmlspecialchars($base_url . '/css/login.css')
        . '?v=' . $version . '">' . "\n";
}

/**
 * Hook pour ajouter lien "Carte des sites" au menu Assistance via CSS/JS
 */
function plugin_monplugin_add_menu_content() {
    global $PLUGIN_HOOKS;

    // Vérifier que c'est Admin ou Super-Admin (ID 2 ou 4)
    if (!isset($_SESSION['glpiactiveprofile']['id'])) {
        return;
    }

    $profile_id = (int)$_SESSION['glpiactiveprofile']['id'];
    if (!in_array($profile_id, [2, 4])) {
        return;
    }

    // Injecter un script qui ajoute le lien au menu
    $url = Plugin::getWebDir('monplugin', false) . '/front/dashboard.php';

    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Chercher le menu Assistance
        var assistanceMenu = document.querySelector("[href*=\"helpdesk\"]");
        if (assistanceMenu) {
            var parent = assistanceMenu.closest(".menu-item") || assistanceMenu.parentElement;
            if (parent) {
                // Créer le lien Carte des sites
                var link = document.createElement("a");
                link.href = "' . htmlspecialchars($url) . '";
                link.className = "menu-item";
                link.innerHTML = \'<i class="ti ti-map-2"></i> Carte des sites\';
                link.style.cssText = "display: block; padding: 10px 15px; color: inherit; text-decoration: none; cursor: pointer;";

                // Ajouter après le lien Assistance
                if (parent.nextElementSibling) {
                    parent.parentElement.insertBefore(link, parent.nextElementSibling);
                } else {
                    parent.parentElement.appendChild(link);
                }
            }
        }
    });
    </script>';
}

