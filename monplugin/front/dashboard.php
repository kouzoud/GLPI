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
Html::header('Carte CID', '', 'helpdesk', 'monplugin');

// TITRE DE PAGE PROPRE
echo '<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col-auto">
            <h2 class="page-title">
                <i class="ti ti-map-pin me-2" style="color:#d4521c"></i>
                Carte des sites CID
            </h2>
            <div class="text-muted mt-1" style="font-size:12px">
                Supervision géographique en temps réel
            </div>
        </div>
    </div>
</div>';
?>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Leaflet MarkerCluster JS -->
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<?php
// Render dashboard container, KPI, filters, map, etc.
PluginMonpluginDashboard::renderPage();

Html::footer();
