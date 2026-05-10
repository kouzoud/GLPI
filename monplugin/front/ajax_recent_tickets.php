<?php
/**
 * ajax_recent_tickets.php
 * Retourne les derniers tickets ouverts (urgence >= moyenne) pour le ticker d'alertes.
 * Triés par urgence décroissante puis par date de création décroissante.
 */
include('../../../inc/includes.php');
include_once('../inc/dashboard.class.php');

header('Content-Type: application/json');
Session::checkLoginUser();

if (!PluginMonpluginDashboard::canView()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

global $DB;

/* ── Niveaux d'urgence GLPI ─────────────────────────────────────────
   1 = Très bas | 2 = Bas | 3 = Moyen | 4 = Élevé | 5 = Très élevé
─────────────────────────────────────────────────────────────────── */
$urgency_labels = [
    5 => ['label' => '🔴 CRITIQUE', 'color' => '#c0392b'],
    4 => ['label' => '🔴 URGENT',   'color' => '#d4521c'],
    3 => ['label' => '🟡 ÉLEVÉ',    'color' => '#e67e22'],
    2 => ['label' => '🟡 MOYEN',    'color' => '#f39c12'],
    1 => ['label' => '🟢 FAIBLE',   'color' => '#1a8c6e'],
];

/**
 * Calcule un "temps relatif" lisible depuis une date MySQL.
 */
function ticketTimeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'à l\'instant';
    if ($diff < 3600)   return 'il y a ' . floor($diff / 60) . 'min';
    if ($diff < 86400)  return 'il y a ' . floor($diff / 3600) . 'h';
    if ($diff < 604800) return 'il y a ' . floor($diff / 86400) . 'j';
    return 'il y a ' . floor($diff / 604800) . ' sem.';
}

try {
    $criteria = [
        'SELECT' => [
            'glpi_tickets.id',
            'glpi_tickets.name',
            'glpi_tickets.date_creation',
            'glpi_tickets.urgency',
            'glpi_tickets.type',
            'glpi_tickets.status',
            'glpi_locations.name AS location_name',
        ],
        'FROM'      => 'glpi_tickets',
        'LEFT JOIN' => [
            'glpi_locations' => [
                'FKEY' => [
                    'glpi_tickets'   => 'locations_id',
                    'glpi_locations' => 'id',
                ]
            ]
        ],
        'WHERE' => [
            'glpi_tickets.status'    => [1, 2, 3, 4], // tous les statuts ouverts
            'glpi_tickets.is_deleted'=> 0,
            // Pas de filtre urgence — Vue filtre côté client
        ],
        'ORDERBY' => [
            'glpi_tickets.urgency DESC',
            'glpi_tickets.date_creation DESC',
        ],
        'LIMIT' => 20,
    ];

    $iterator = $DB->request($criteria);
    $tickets  = [];

    foreach ($iterator as $row) {
        $urgency  = (int) $row['urgency'];
        $urg_info = $urgency_labels[$urgency] ?? $urgency_labels[3];

        $tickets[] = [
            'id'            => (int) $row['id'],
            'title'         => $row['name'] ?: 'Ticket #' . $row['id'],
            'site'          => $row['location_name'] ?: 'Site inconnu',
            'urgency'       => $urg_info['label'],  // libellé affiché
            'urgency_level' => $urgency,             // valeur numérique pour filtre Vue
            'color'         => $urg_info['color'],
            'time'          => ticketTimeAgo($row['date_creation']),
            'type'          => (int) $row['type'],
        ];
    }

    echo json_encode([
        'tickets'      => $tickets,
        'count'        => count($tickets),
        'generated_at' => date('c'),
    ]);

} catch (\Exception $e) {
    error_log('[monplugin] ajax_recent_tickets error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
