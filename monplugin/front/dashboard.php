<?php
include('../../../inc/includes.php');
include_once('../inc/dashboard.class.php');

Session::checkLoginUser();

if (!PluginMonpluginDashboard::canView()) {
    Html::displayRightError();
}

$plugin_version = defined('PLUGIN_MONPLUGIN_VERSION') ? PLUGIN_MONPLUGIN_VERSION : '2.0.0';
$base_url = Plugin::getWebDir('monplugin', true, true);
?>

<!-- ══ Leaflet CSS ══════════════════════════════════════════════ -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- ══ Leaflet MarkerCluster CSS ═══════════════════════════════ -->
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />

<?php
Html::header('Carte CID', '', 'helpdesk', 'monplugin');

echo '<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col-auto">
            <h2 class="page-title">
                <i class="ti ti-map-pin me-2" style="color:#d4521c"></i>
                Carte des sites CID
            </h2>
            <div class="text-muted mt-1" style="font-size:12px;">
                Supervision géographique en temps réel &nbsp;·&nbsp;
                
            </div>
        </div>
    </div>
</div>';
?>

<!-- ══ Leaflet JS ═══════════════════════════════════════════════ -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- ══ Leaflet MarkerCluster JS ════════════════════════════════ -->
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

<!-- ══ Vue 3 (CDN production — pas de build requis) ════════════ -->
<script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>

<!-- ══ html2canvas + jsPDF (export PDF) ═══════════════════════ -->
<script src="https://unpkg.com/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<!-- ══ Dashboard Vue (Composition API) ════════════════════════= -->
<script
    src="<?php echo htmlspecialchars($base_url . '/js/geodash-vue.js'); ?>?v=<?php echo $plugin_version; ?>"></script>

<?php
/* PHP monte uniquement le <div id="geo-dashboard-app"> — Vue fait le reste */
PluginMonpluginDashboard::renderPage();

Html::footer();
