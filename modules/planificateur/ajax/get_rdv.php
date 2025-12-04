<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le bon statut
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}
if (!isPlanificateur() && !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit();
}

$db = getDB();

// Pagination
$limit = 10; // Nombre de rendez-vous par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filtres et recherche
$filters = [
    'statut' => isset($_GET['statut']) ? $_GET['statut'] : '',
    'commune' => isset($_GET['commune']) ? $_GET['commune'] : '',
    'date_debut' => isset($_GET['date_debut']) ? $_GET['date_debut'] : '',
    'date_fin' => isset($_GET['date_fin']) ? $_GET['date_fin'] : '',
    'search' => isset($_GET['search']) ? $_GET['search'] : ''
];

// Construction de la requête SQL de base
$sql = "SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.commune, c.telephone,
               u.nom as agent_nom, u.prenom as agent_prenom
        FROM rendezvous r
        JOIN clients c ON r.client_id = c.id
        LEFT JOIN users u ON r.agent_id = u.id
        WHERE r.planificateur_id = ?";

// Construction de la requête pour le total
$sql_count = "SELECT COUNT(*) as total FROM rendezvous r JOIN clients c ON r.client_id = c.id WHERE r.planificateur_id = ?";

$params = [$_SESSION['user_id']];
$params_count = [$_SESSION['user_id']];

// Ajout des filtres
if (!empty($filters['statut'])) {
    $sql .= " AND r.statut_rdv = ?";
    $sql_count .= " AND r.statut_rdv = ?";
    $params[] = $filters['statut'];
    $params_count[] = $filters['statut'];
}

if (!empty($filters['commune'])) {
    $sql .= " AND c.commune LIKE ?";
    $sql_count .= " AND c.commune LIKE ?";
    $params[] = '%' . $filters['commune'] . '%';
    $params_count[] = '%' . $filters['commune'] . '%';
}

if (!empty($filters['date_debut'])) {
    $sql .= " AND r.date_rdv >= ?";
    $sql_count .= " AND r.date_rdv >= ?";
    $params[] = date('Y-m-d', strtotime($filters['date_debut'])) . ' 00:00:00';
    $params_count[] = date('Y-m-d', strtotime($filters['date_debut'])) . ' 00:00:00';
}

if (!empty($filters['date_fin'])) {
    $sql .= " AND r.date_rdv <= ?";
    $sql_count .= " AND r.date_rdv <= ?";
    $params[] = date('Y-m-d', strtotime($filters['date_fin'])) . ' 23:59:59';
    $params_count[] = date('Y-m-d', strtotime($filters['date_fin'])) . ' 23:59:59';
}

if (!empty($filters['search'])) {
    $sql .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ? OR r.motif LIKE ?)";
    $sql_count .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ? OR r.motif LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params_count[] = $search_term;
    $params_count[] = $search_term;
    $params_count[] = $search_term;
    $params_count[] = $search_term;
}

$sql .= " ORDER BY r.date_rdv DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    // Récupérer le nombre total de rendez-vous
    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params_count);
    $total_records = $stmt_count->fetchColumn();

    // Récupérer les rendez-vous pour la page actuelle
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rendezvous = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater la date pour l'affichage
    foreach ($rendezvous as &$rdv) {
        $rdv['date_rdv_formatted'] = date('d/m/Y H:i', strtotime($rdv['date_rdv']));
    }
    unset($rdv); // Détruire la référence pour éviter les effets de bord

    $response = [
        'rendezvous' => $rendezvous,
        'total' => $total_records,
        'current_page' => $page,
        'total_pages' => ceil($total_records / $limit)
    ];

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>