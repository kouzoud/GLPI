<?php
include('../../../inc/includes.php');
include_once('../inc/dashboard.class.php');

Session::checkLoginUser();

if (!PluginMonpluginDashboard::canView()) {
    Html::displayRightError();
}

// ===== INJECTION CDN LEAFLET =====
// (Injectées directement ici pour éviter le préfixage GLPI)
$plugin_version = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '1.7.1';
?>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css?v=<?php echo htmlspecialchars($plugin_version); ?>" />

<!-- Leaflet MarkerCluster CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css?v=<?php echo htmlspecialchars($plugin_version); ?>" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css?v=<?php echo htmlspecialchars($plugin_version); ?>" />

<?php
// Page header GLPI standard
Html::header('Carte des sites', '', 'helpdesk', 'geodashboard');
?>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Leaflet MarkerCluster JS -->
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<?php
// Render dashboard container, KPI, filters, map, etc.
PluginMonpluginDashboard::renderPage();

Html::footer();
