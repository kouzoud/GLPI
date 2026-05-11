<?php
// Cache busting — incrémenter à chaque déploiement
define('PLUGIN_MONPLUGIN_VERSION', '3.5.6');

function plugin_init_monplugin()
{
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['monplugin'] = true;

    include_once(__DIR__ . '/hook.php');
    plugin_monplugin_inject_css();

    $PLUGIN_HOOKS['display_login']['monplugin'] = 'plugin_monplugin_display_login';

    // Hook standard GLPI pour ajouter au menu "Assistance"
    $PLUGIN_HOOKS['menu_toadd']['monplugin'] = ['helpdesk' => 'PluginMonpluginDashboard'];
}


function plugin_version_monplugin()
{
    return [
        'name' => 'ITSM-ESM ',
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

