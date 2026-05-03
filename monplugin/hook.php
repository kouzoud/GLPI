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

function plugin_monplugin_inject_css()
{
    global $PLUGIN_HOOKS;

    // Sécurité : session active avec profil défini
    if (!isset($_SESSION['glpiactiveprofile']['id'])) {
        return;
    }

    $profile_id = (int) $_SESSION['glpiactiveprofile']['id'];
    $version = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '1.7.1';

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

        // Fix modaux mot de passe : les déplace vers <body> pour que
        // position:fixed fonctionne correctement (hors du DOM du formulaire)
        $PLUGIN_HOOKS['add_javascript']['monplugin'][] = 'js/modal-fix.js';
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
function plugin_monplugin_display_login()
{
    $version = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '1.7.1';
    $base_url = Plugin::getWebDir('monplugin', true, true);

    echo '<link rel="stylesheet" type="text/css" href="'
        . htmlspecialchars($base_url . '/css/login.css')
        . '?v=' . $version . '">' . "\n";
}

