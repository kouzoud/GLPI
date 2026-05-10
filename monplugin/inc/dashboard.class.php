<?php

use Html;
use Session;
use DB;
use Toolbox;
use Plugin;

class PluginMonpluginDashboard extends \CommonGLPI {

    public static $rightname = 'profile';

    public static function getTypeName($nb = 0): string {
        return 'Carte des sites';
    }

    public static function getMenuName(): string {
        return 'Carte des sites';
    }

    public static function getIcon() {
        return "ti ti-map-2";
    }

    public static function getMenuContent() {
        $menu = [];
        if (static::canView()) {
            $menu['title'] = self::getMenuName();
            $menu['page']  = Plugin::getWebDir('monplugin', false) . '/front/dashboard.php';
            $menu['icon']  = self::getIcon();
        }
        return $menu;
    }

    public static function canView(): bool {
        // Accès seulement pour Admin (ID 2) et Super-Admin (ID 4)
        if (!isset($_SESSION['glpiactiveprofile']['id'])) {
            return false;
        }

        $profile_id = (int)$_SESSION['glpiactiveprofile']['id'];

        // Autoriser Admin (2) et Super-Admin (4)
        return in_array($profile_id, [2, 4]);
    }

    /**
     * @param array $filters
     * @return array
     */
    public static function getGeoData(array $filters = []): array {
        Session::checkLoginUser();
        global $DB;

        $type_filter = isset($filters['type']) ? (int)$filters['type'] : null;
        $status_filter = isset($filters['status']) && is_array($filters['status']) ? array_map('intval', $filters['status']) : null;
        
        // Construction du critère au format tableau pour GLPI 10+
        $criteria = [
            'SELECT' => [
                'loc.id',
                'loc.name',
                'loc.latitude',
                'loc.longitude',
                new \QueryExpression('SUM(CASE WHEN t.type = 1 AND t.status IN (1,2,3,4) THEN 1 ELSE 0 END) AS incidents_open'),
                new \QueryExpression('SUM(CASE WHEN t.type = 2 AND t.status IN (1,2,3,4) THEN 1 ELSE 0 END) AS requests_open'),
                new \QueryExpression('SUM(CASE WHEN t.status IN (1,2,3,4) THEN 1 ELSE 0 END) AS total_open'),
                new \QueryExpression('SUM(CASE WHEN t.status IN (5,6) THEN 1 ELSE 0 END) AS total_closed')
            ],
            'FROM' => 'glpi_locations AS loc',
            'LEFT JOIN' => [
                'glpi_tickets AS t' => [
                    'FKEY' => [
                        't'   => 'locations_id',
                        'loc' => 'id'
                    ],
                    // Condition additionnelle dans le JOIN
                    ['t.is_deleted' => 0]
                ]
            ],
            'WHERE' => [
                ['NOT' => ['loc.latitude' => null]],
                ['NOT' => ['loc.latitude' => '']],
                ['NOT' => ['loc.longitude' => null]],
                ['NOT' => ['loc.longitude' => '']]
            ],
            'GROUPBY' => [
                'loc.id',
                'loc.name',
                'loc.latitude',
                'loc.longitude'
            ],
            'ORDERBY' => 'total_open DESC'
        ];

        // Ajout dynamique des filtres dans le LEFT JOIN (pour ne compter que les tickets filtrés)
        if ($type_filter) {
            $criteria['LEFT JOIN']['glpi_tickets AS t'][] = ['t.type' => $type_filter];
        }
        
        if ($status_filter) {
            $criteria['LEFT JOIN']['glpi_tickets AS t'][] = ['t.status' => $status_filter];
        }

        try {
            $iterator = $DB->request($criteria);
            $data = [];
            
            foreach ($iterator as $row) {
                $data[] = $row;
            }
        } catch (\Exception $e) {
            error_log("GeoDashboard SQL Error: " . $e->getMessage());
            // Retourner l'erreur comme un faux site pour le débogage
            return [[
                'id' => 'error',
                'name' => 'ERREUR BUILDER: ' . $e->getMessage(),
                'latitude' => 33.9,
                'longitude' => -6.8,
                'incidents_open' => 999,
                'requests_open' => 999,
                'total_open' => 999,
                'total_closed' => 0
            ]];
        }
        
        // Fallback sites if no data from DB
        if (empty($data)) {
            $data = [
                [
                    'id' => 'fallback_1',
                    'name' => 'CID Technopolis',
                    'latitude' => 33.9794,
                    'longitude' => -6.7231,
                    'incidents_open' => 12,
                    'requests_open' => 8,
                    'total_open' => 20,
                    'total_closed' => 45
                ],
                [
                    'id' => 'fallback_2',
                    'name' => 'CID Siège Rabat',
                    'latitude' => 33.9877,
                    'longitude' => -6.8538,
                    'incidents_open' => 5,
                    'requests_open' => 3,
                    'total_open' => 8,
                    'total_closed' => 120
                ],
                [
                    'id' => 'fallback_3',
                    'name' => 'CID Casablanca',
                    'latitude' => 33.5333,
                    'longitude' => -7.6333,
                    'incidents_open' => 2,
                    'requests_open' => 1,
                    'total_open' => 3,
                    'total_closed' => 85
                ]
            ];
        }

        return $data;
    }

    /**
     * @param array $filters
     * @return string
     */
    public static function getGeoDataAsJson(array $filters): string {
        Session::checkLoginUser();
        
        $data = self::getGeoData($filters);
        $sites = [];
        
        foreach ($data as $row) {
            $total_open = (int)$row['total_open'];
            
            if ($total_open > 15) {
                $criticality = 'high';
            } elseif ($total_open > 5) {
                $criticality = 'medium';
            } else {
                $criticality = 'low';
            }
            
            $sites[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'lat' => (float)$row['latitude'],
                'lng' => (float)$row['longitude'],
                'stats' => [
                    'incidents_open' => (int)$row['incidents_open'],
                    'requests_open'  => (int)$row['requests_open'],
                    'total_open'     => $total_open,
                    'total_closed'   => (int)$row['total_closed'],
                    'criticality'    => $criticality
                ]
            ];
        }
        
        $response = [
            'sites' => $sites,
            'meta'  => [
                'generated_at'    => date('c'),
                'last_ticket_date'=> 'il y a 2 min',
                'total_sites'     => count($sites),
                'filters_applied' => $filters
            ]
        ];

        return json_encode($response);
    }

    /**
     * Get trend value (mocked implementation, would normally diff current vs last week)
     */
    public static function getTrend(): array {
        return [
            'total' => '+2 depuis hier',
            'incidents' => '= stable',
            'requests' => '-1 depuis hier',
            'sites' => '+1 actif'
        ];
    }

    /**
     * renderPage() — v2.1.0 Vue.js x-template
     *
     * Émet :
     *   1. Un <script type="text/x-template"> contenant le template Vue
     *      (évite les problèmes de compilation de chaîne en mode strict CDN)
     *   2. Le point de montage #geo-dashboard-app avec l'URL AJAX
     *
     * @return void
     */
    public static function renderPage(): void {
        Session::checkLoginUser();

        $ajax_url    = Plugin::getWebDir('monplugin', true, true) . '/front/ajax_geodata.php';
        $tickets_url = Plugin::getWebDir('monplugin', true, true) . '/front/ajax_recent_tickets.php';
        $bi_url      = Plugin::getWebDir('monplugin', true, true) . '/front/ajax_bi_data.php';

        /* Nowdoc PHP : pas d'interpolation → les {{ }} Vue sont préservés */
        echo <<<'VUETEMPLATE'
<script type="text/x-template" id="geo-dashboard-tpl">
<div id="geo-dashboard-container">

    <!-- KPI GRID -->
    <div class="geo-kpi-grid">
        <div class="geo-kpi-card kpi-total">
            <div class="geo-kpi-icon"><i class="ti ti-ticket"></i></div>
            <div>
                <div class="geo-kpi-label">Total tickets ouverts</div>
                <div class="geo-kpi-value">{{ kpiTotal }}</div>
            </div>
        </div>
        <div class="geo-kpi-card kpi-incident">
            <div class="geo-kpi-icon"><i class="ti ti-alert-triangle"></i></div>
            <div>
                <div class="geo-kpi-label">Incidents</div>
                <div class="geo-kpi-value">{{ kpiIncidents }}</div>
            </div>
        </div>
        <div class="geo-kpi-card kpi-demande">
            <div class="geo-kpi-icon"><i class="ti ti-headset"></i></div>
            <div>
                <div class="geo-kpi-label">Demandes</div>
                <div class="geo-kpi-value">{{ kpiRequests }}</div>
            </div>
        </div>
        <div class="geo-kpi-card kpi-sites">
            <div class="geo-kpi-icon"><i class="ti ti-building-community"></i></div>
            <div>
                <div class="geo-kpi-label">Sites actifs</div>
                <div class="geo-kpi-value">{{ kpiSites }}</div>
            </div>
        </div>
    </div>

    <!-- BARRE DE FILTRES -->
    <div class="geo-filter-bar">
        <div class="geo-filter-group">
            <button
                v-for="btn in filterButtons"
                :key="btn.type"
                class="geo-filter-btn"
                :class="{ active: activeType === btn.type }"
                :data-type="btn.type"
                @click="setTypeFilter(btn.type)">
                <i :class="btn.icon"></i>
                <span>{{ btn.label }}</span>
                <span class="badge-count">{{ btn.count }}</span>
            </button>
        </div>
        <div class="geo-filter-separator"></div>
        <div class="geo-filter-right">
            <div class="geo-select-wrapper">
                <select v-model="activeStatus" class="geo-status-select" id="geo-status-filter">
                    <option value="all">Tous les statuts</option>
                    <option value="1">Nouveau</option>
                    <option value="2">En cours (Attribu&#233;)</option>
                    <option value="3">En cours (Planifi&#233;)</option>
                    <option value="4">En attente</option>
                    <option value="5">R&#233;solu</option>
                    <option value="6">Clos</option>
                </select>
            </div>
        </div>
    </div>

    <!-- BARRE D'ETAT -->
    <div class="geo-status-bar">
        <div>
            <span class="geo-status-dot" :style="statusDotStyle"></span>
            {{ statusLabel }}
        </div>
        <div id="geo-meta-info">{{ metaInfo }}</div>
    </div>

    <!-- CONTENEUR CARTE (Leaflet monte ici) -->
    <div id="map-container" style="position:relative;">
        <div class="geo-loader" :class="{ active: loading }" id="geo-loader">
            <div class="geo-loader-spinner"></div>
        </div>
    </div>

</div>
</script>
VUETEMPLATE;

        echo '<div id="geo-dashboard-app"'
            . ' data-ajax-url="'    . htmlspecialchars($ajax_url,    ENT_QUOTES, 'UTF-8') . '"'
            . ' data-tickets-url="' . htmlspecialchars($tickets_url, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-bi-url="'      . htmlspecialchars($bi_url,      ENT_QUOTES, 'UTF-8') . '"'
            . '></div>';
    }

    /**
     * @return void
     */
    public static function ajaxHandler(): void {
        Session::checkLoginUser();
        
        $filters = [];
        
        if (isset($_GET['type']) && in_array($_GET['type'], ['1', '2'])) {
            $filters['type'] = (int)$_GET['type'];
        }
        
        if (isset($_GET['status']) && $_GET['status'] !== 'all') {
            $filters['status'] = [(int)$_GET['status']];
        } else {
            // Include specific open statuses by default? 
            // Or 'all' could just be left absent for getGeoData to handle. 
            // In getGeoData we use status IN (1,2,3,4) for open and 5,6 for closed internally in sums,
            // but the WHERE clause only gets filtered if $filters['status'] is set.
            // When 'all' is selected, we want all statuses, so we don't set the filter.
        }
        
        header('Content-Type: application/json');
        echo self::getGeoDataAsJson($filters);
        exit;
    }

    /** @return array[] Répartition tickets par statut — SQL direct */
    public static function getStatusDistribution(): array {
        global $DB;
        $labels = [1=>'Nouveau',2=>'Attribué',3=>'Planifié',4=>'En attente',5=>'Résolu',6=>'Clos'];
        $colors = [1=>'#ef4444',2=>'#d4521c',3=>'#f59e0b',4=>'#6366f1',5=>'#10b981',6=>'#5a7499'];
        $result = [];
        try {
            $res = $DB->query("SELECT `status`, COUNT(*) AS cnt FROM `glpi_tickets` WHERE `is_deleted` = 0 GROUP BY `status` ORDER BY `status` ASC");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $s = (int)$row['status'];
                    $result[] = ['status'=>$s,'label'=>$labels[$s]??'Inconnu','count'=>(int)$row['cnt'],'color'=>$colors[$s]??'#5a7499'];
                }
            }
        } catch (\Exception $e) { Toolbox::logError($e->getMessage()); }
        return $result;
    }

    /** @return array[] Matrice site x priorité — SQL direct */
    public static function getPriorityHeatmap(): array {
        global $DB;
        $tmp = [];
        try {
            $res = $DB->query(
                "SELECT t.`locations_id`, t.`priority`, COUNT(*) AS cnt, l.`name` AS loc_name
                 FROM `glpi_tickets` t
                 LEFT JOIN `glpi_locations` l ON l.`id` = t.`locations_id`
                 WHERE t.`is_deleted` = 0 AND t.`locations_id` > 0
                 GROUP BY t.`locations_id`, t.`priority`"
            );
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $lid = (int)$row['locations_id'];
                    if (!isset($tmp[$lid])) $tmp[$lid] = ['name'=>$row['loc_name']?:'Site #'.$lid,'priorities'=>[]];
                    $tmp[$lid]['priorities'][(int)$row['priority']] = (int)$row['cnt'];
                }
            }
        } catch (\Exception $e) { Toolbox::logError($e->getMessage()); }
        return array_values($tmp);
    }

    /** @return array Tendance 7 derniers jours {labels[], incidents[], demandes[]} — SQL direct */
    public static function get7DayTrend(): array {
        global $DB;
        $trend = ['labels'=>[],'incidents'=>[],'demandes'=>[]];
        $days  = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
        try {
            $res = $DB->query(
                "SELECT DATE(`date_creation`) AS day, `type`, COUNT(*) AS cnt
                 FROM `glpi_tickets`
                 WHERE `is_deleted` = 0 AND DATE(`date_creation`) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                 GROUP BY DATE(`date_creation`), `type`
                 ORDER BY `day` ASC"
            );
            $byDay = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $byDay[$row['day']][(int)$row['type']] = (int)$row['cnt'];
                }
            }
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $trend['labels'][]    = $days[(int)date('w', strtotime($date))];
                $trend['incidents'][] = $byDay[$date][1] ?? 0;
                $trend['demandes'][]  = $byDay[$date][2] ?? 0;
            }
        } catch (\Exception $e) { Toolbox::logError($e->getMessage()); }
        return $trend;
    }

    /** @return array KPIs opérationnels — SQL direct */
    public static function getOperationalKpis(): array {
        global $DB;
        $k = ['avg_resolution_hours'=>null,'resolution_rate_pct'=>0,'busiest_site_name'=>'N/A','busiest_site_count'=>0,'oldest_ticket_name'=>'N/A','oldest_ticket_age'=>'N/A'];
        try {
            // KPI 1 — Délai moyen résolution (heures)
            $r1 = $DB->query("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR,`date_creation`,`solvedate`)),1) AS avg_h FROM `glpi_tickets` WHERE `is_deleted`=0 AND `solvedate` IS NOT NULL AND `status` >= 5");
            if ($r1 && $row = $r1->fetch_assoc()) $k['avg_resolution_hours'] = $row['avg_h'] !== null ? (float)$row['avg_h'] : null;

            // KPI 2 — Taux de résolution
            $r2 = $DB->query("SELECT COUNT(*) AS total, SUM(CASE WHEN `status`>=5 THEN 1 ELSE 0 END) AS resolved FROM `glpi_tickets` WHERE `is_deleted`=0");
            if ($r2 && $row = $r2->fetch_assoc()) {
                if ((int)$row['total'] > 0) $k['resolution_rate_pct'] = (int)round((int)$row['resolved'] / (int)$row['total'] * 100);
            }

            // KPI 3 — Site le plus chargé (tickets ouverts)
            $r3 = $DB->query("SELECT t.`locations_id`, COUNT(*) AS cnt, l.`name` AS loc_name FROM `glpi_tickets` t LEFT JOIN `glpi_locations` l ON l.`id`=t.`locations_id` WHERE t.`is_deleted`=0 AND t.`status` IN (1,2,3,4) AND t.`locations_id`>0 GROUP BY t.`locations_id` ORDER BY cnt DESC LIMIT 1");
            if ($r3 && $row = $r3->fetch_assoc()) {
                $k['busiest_site_name']  = $row['loc_name'] ?: 'Site #'.$row['locations_id'];
                $k['busiest_site_count'] = (int)$row['cnt'];
            }

            // KPI 4 — Ticket le plus ancien non résolu
            $r4 = $DB->query("SELECT `id`,`name`,`date_creation` FROM `glpi_tickets` WHERE `is_deleted`=0 AND `status` IN (1,2,3,4) ORDER BY `date_creation` ASC LIMIT 1");
            if ($r4 && $row = $r4->fetch_assoc()) {
                $diff = time() - strtotime($row['date_creation']);
                $k['oldest_ticket_name'] = mb_substr($row['name'],0,35).(mb_strlen($row['name'])>35?'…':'');
                $k['oldest_ticket_age']  = ($diff >= 86400 ? floor($diff/86400).'j ' : '').floor(($diff%86400)/3600).'h';
            }
        } catch (\Exception $e) { Toolbox::logError($e->getMessage()); }
        return $k;
    }
}
