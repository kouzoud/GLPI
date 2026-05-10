<?php
include('../../../inc/includes.php');
include_once('../inc/dashboard.class.php');

header('Content-Type: application/json; charset=utf-8');
Session::checkLoginUser();

if (!PluginMonpluginDashboard::canView()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

global $DB;

$bi = [
    'status_dist' => [],
    'heatmap'     => [],
    'trend_7d'    => ['labels'=>[],'incidents'=>[],'demandes'=>[]],
    'kpis_ops'    => ['avg_resolution_hours'=>null,'resolution_rate_pct'=>0,
                      'busiest_site_name'=>'N/A','busiest_site_count'=>0,
                      'oldest_ticket_name'=>'N/A','oldest_ticket_age'=>'N/A'],
];

$sl = [1=>'Nouveau',2=>'Attribué',3=>'Planifié',4=>'En attente',5=>'Résolu',6=>'Clos'];
$sc = [1=>'#ef4444',2=>'#d4521c',3=>'#f59e0b',4=>'#6366f1',5=>'#10b981',6=>'#5a7499'];
$df = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];

try {

    /* ── Widget 1 : Répartition statuts ─────────────────────────── */
    $r = $DB->request([
        'SELECT'  => ['status', new \QueryExpression('COUNT(*) AS cnt')],
        'FROM'    => 'glpi_tickets',
        'WHERE'   => ['is_deleted' => 0],
        'GROUPBY' => 'status',
        'ORDERBY' => 'status'
    ]);
    foreach ($r as $row) { $s=(int)$row['status']; $bi['status_dist'][]=['status'=>$s,'label'=>$sl[$s]??'Autre','count'=>(int)$row['cnt'],'color'=>$sc[$s]??'#5a7499']; }

    /* ── Widget 2 : Heatmap sites × priorités ────────────────────── */
    $r = $DB->request([
        'SELECT'    => ['t.locations_id', 't.priority', new \QueryExpression('COUNT(*) AS cnt'), 'l.name AS loc_name'],
        'FROM'      => 'glpi_tickets AS t',
        'LEFT JOIN' => [
            'glpi_locations AS l' => [
                'FKEY' => ['l' => 'id', 't' => 'locations_id']
            ]
        ],
        'WHERE'     => ['t.is_deleted' => 0, 't.locations_id' => ['>', 0]],
        'GROUPBY'   => ['t.locations_id', 't.priority']
    ]);
    $tmp = [];
    foreach ($r as $row) { $lid=(int)$row['locations_id']; if(!isset($tmp[$lid])) $tmp[$lid]=['name'=>$row['loc_name']?:'Site #'.$lid,'priorities'=>[]]; $tmp[$lid]['priorities'][(int)$row['priority']]=(int)$row['cnt']; }
    $bi['heatmap'] = array_values($tmp);

    /* ── Widget 3 : Tendance 7 jours ─────────────────────────────── */
    $six_days_ago = date('Y-m-d', strtotime('-6 days'));
    $r = $DB->request([
        'SELECT'  => [new \QueryExpression('DATE(`date_creation`) AS day'), 'type', new \QueryExpression('COUNT(*) AS cnt')],
        'FROM'    => 'glpi_tickets',
        'WHERE'   => [
            'is_deleted' => 0,
            'date_creation' => ['>=', $six_days_ago . ' 00:00:00']
        ],
        'GROUPBY' => [new \QueryExpression('DATE(`date_creation`)'), 'type'],
        'ORDERBY' => 'day ASC'
    ]);
    $bd = [];
    foreach ($r as $row) { $bd[$row['day']][(int)$row['type']]=(int)$row['cnt']; }
    for ($i=6;$i>=0;$i--) { $d=date('Y-m-d',strtotime("-{$i} days")); $bi['trend_7d']['labels'][]=$df[(int)date('w',strtotime($d))]; $bi['trend_7d']['incidents'][]=$bd[$d][1]??0; $bi['trend_7d']['demandes'][]=$bd[$d][2]??0; }

    /* ── Widget 4 : KPIs ─────────────────────────────────────────── */
    $r = $DB->request([
        'SELECT' => [new \QueryExpression('ROUND(AVG(TIMESTAMPDIFF(HOUR,`date_creation`,`solvedate`)),1) AS avg_h')],
        'FROM'   => 'glpi_tickets',
        'WHERE'  => ['is_deleted' => 0, ['NOT' => ['solvedate' => null]], 'status' => ['>=', 5]]
    ]);
    foreach ($r as $row) { $bi['kpis_ops']['avg_resolution_hours']=$row['avg_h']!==null?(float)$row['avg_h']:null; break; }

    $r = $DB->request([
        'SELECT' => [new \QueryExpression('COUNT(*) AS total'), new \QueryExpression('SUM(CASE WHEN `status`>=5 THEN 1 ELSE 0 END) AS resolved')],
        'FROM'   => 'glpi_tickets',
        'WHERE'  => ['is_deleted' => 0]
    ]);
    foreach ($r as $row) { if ((int)$row['total']>0) $bi['kpis_ops']['resolution_rate_pct']=(int)round((int)$row['resolved']/(int)$row['total']*100); break; }

    $r = $DB->request([
        'SELECT'    => ['t.locations_id', new \QueryExpression('COUNT(*) AS cnt'), 'l.name AS loc_name'],
        'FROM'      => 'glpi_tickets AS t',
        'LEFT JOIN' => [
            'glpi_locations AS l' => [
                'FKEY' => ['l' => 'id', 't' => 'locations_id']
            ]
        ],
        'WHERE'     => ['t.is_deleted' => 0, 't.status' => [1,2,3,4], 't.locations_id' => ['>', 0]],
        'GROUPBY'   => ['t.locations_id', 'l.name'],
        'ORDERBY'   => 'cnt DESC',
        'LIMIT'     => 1
    ]);
    foreach ($r as $row) { $bi['kpis_ops']['busiest_site_name']=$row['loc_name']?:'Site #'.$row['locations_id']; $bi['kpis_ops']['busiest_site_count']=(int)$row['cnt']; break; }

    $r = $DB->request([
        'SELECT'  => ['id', 'name', 'date_creation'],
        'FROM'    => 'glpi_tickets',
        'WHERE'   => ['is_deleted' => 0, 'status' => [1,2,3,4]],
        'ORDERBY' => 'date_creation ASC',
        'LIMIT'   => 1
    ]);
    foreach ($r as $row) { $diff=time()-strtotime($row['date_creation']); $bi['kpis_ops']['oldest_ticket_name']=mb_substr($row['name'],0,35).(mb_strlen($row['name'])>35?'…':''); $bi['kpis_ops']['oldest_ticket_age']=($diff>=86400?floor($diff/86400).'j ':'').floor(($diff%86400)/3600).'h'; break; }

} catch (\Throwable $e) {
    error_log('[monplugin][bi] '.$e->getMessage());
    // On ne retourne pas 500 : on retourne les données partielles déjà collectées
}

echo json_encode(['bi_data'=>$bi,'generated_at'=>date('c')]);
file_put_contents('debug_bi.json', json_encode(['bi_data'=>$bi,'bi_type'=>gettype($bi['status_dist'])]));
exit;
