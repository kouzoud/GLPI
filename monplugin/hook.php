<?php
/**
 * hook.php — Injection CSS par profil GLPI
 * Plugin : monplugin v1.1.0
 *
 * Profils gérés :
 *   ID 4 → Super-Admin   → superadmin.css
 *   ID 6 → Technicien    → technicien.css
 *   ID 1 → Employé       → employe.css
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
    $version    = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '1.1.0';

    // Mapping profil → fichier CSS
    $profile_css_map = [
        4 => 'css/superadmin.css',   // Super-Admin
        6 => 'css/technicien.css',   // Technicien
        1 => 'css/employe.css',      // Employé / Self-Service
    ];

    if (isset($profile_css_map[$profile_id])) {
        $css_file = $profile_css_map[$profile_id] . '?v=' . $version;
        $PLUGIN_HOOKS['add_css']['monplugin'] = [$css_file];
    }
    // Aucun profil reconnu → pas de CSS injecté (fallback propre)
}

/**
 * Hook display_login — Injection du CSS sur la page de login
 * Appelé automatiquement par GLPI avant l'affichage du formulaire de connexion
 */
function plugin_monplugin_display_login() {
    $version  = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '1.1.0';
    $base_url = Plugin::getWebDir('monplugin', true, true);

    echo '<link rel="stylesheet" type="text/css" href="'
        . htmlspecialchars($base_url . '/css/login.css')
        . '?v=' . $version . '">' . "\n";
}
