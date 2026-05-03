<?php
include('../../../inc/includes.php');
include_once('../inc/dashboard.class.php');

// Désactivé en production pour ne pas corrompre le JSON
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
header('Content-Type: application/json');
Session::checkLoginUser();

if (!PluginMonpluginDashboard::canView()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

PluginMonpluginDashboard::ajaxHandler();
