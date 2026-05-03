<?php
// Cache busting — incrémenter à chaque déploiement
define('PLUGIN_MONPLUGIN_VERSION', '1.0.3');

function plugin_init_monplugin()
{
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['monplugin'] = true;

    include_once(__DIR__ . '/hook.php');
    plugin_monplugin_inject_css();

    $PLUGIN_HOOKS['display_login']['monplugin'] = 'plugin_monplugin_display_login';

    // Hook pour afficher le lien "Carte des sites" en haut des pages
    $PLUGIN_HOOKS['head']['monplugin'] = 'plugin_monplugin_add_menu_content';
}

function plugin_monplugin_getMenuContent()
{
    return [
        'helpdesk' => [
            'geodashboard' => [
                'title' => 'Carte des sites',
                'page' => Plugin::getWebDir('monplugin', false) . '/front/dashboard.php',
                'icon' => 'ti ti-map-2'
            ]
        ]
    ];
}

function plugin_version_monplugin()
{
    return [
        'name' => 'Mon Premier Plugin',
        'version' => PLUGIN_MONPLUGIN_VERSION,
        'author' => 'DevOps',
        'license' => 'GPLv2+',
        'homepage' => '',
        'requirements' => ['glpi' => ['min' => '10.0']]
    ];
}

function plugin_monplugin_check_prerequisites()
{
    return true;
}
function plugin_monplugin_check_config()
{
    return true;
}
function plugin_monplugin_install()
{
    return true;
}
function plugin_monplugin_uninstall()
{
    return true;
}

function plugin_monplugin_update($version)
{
    return true;
}

