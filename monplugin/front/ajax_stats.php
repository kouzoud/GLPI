<?php
include('../../../inc/includes.php');
include_once('../inc/dashboard.class.php');

Session::checkLoginUser();

$filters = [];
if (isset($_GET['type']) && in_array($_GET['type'], ['1', '2'])) {
    $filters['type'] = (int)$_GET['type'];
}

if (isset($_GET['status']) && is_array($_GET['status'])) {
    $filters['status'] = array_map('intval', $_GET['status']);
} elseif (isset($_GET['status']) && $_GET['status'] !== 'all') {
    $filters['status'] = [(int)$_GET['status']];
}

header('Content-Type: application/json');
echo PluginMonpluginDashboard::getGeoDataAsJson($filters);
exit;
