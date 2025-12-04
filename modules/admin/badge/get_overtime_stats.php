<?php
require_once '../../../includes/auth.php';
require_once '../../../config/database.php';

// Vérifier l'authentification et les permissions
redirectIfNotLoggedIn();
if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit();
}

// Définir le fuseau horaire d'Abidjan
date_default_timezone_set('Africa/Abidjan');

// Header JSON
header('Content-Type: application/json');

// Récupérer l'ID utilisateur
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID utilisateur invalide']);
    exit();
}

$db = getDB();

// Fonction pour calculer les heures supplémentaires par période
function getOvertimeStats($db, $user_id, $period_type, $date = null) {
    $where_conditions = ["b.type_badgeage = 'departure'", "b.user_id = ?"];
    $params = [$user_id];
    
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    switch ($period_type) {
        case 'week':
            // Semaine courante (lundi à dimanche)
            $start_of_week = date('Y-m-d', strtotime('monday this week', strtotime($date)));
            $end_of_week = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
            $where_conditions[] = "b.date_badgeage BETWEEN ? AND ?";
            $params[] = $start_of_week;
            $params[] = $end_of_week;
            break;
            
        case 'month':
            // Mois courant
            $start_of_month = date('Y-m-01', strtotime($date));
            $end_of_month = date('Y-m-t', strtotime($date));
            $where_conditions[] = "b.date_badgeage BETWEEN ? AND ?";
            $params[] = $start_of_month;
            $params[] = $end_of_month;
            break;
            
        case 'year':
            // Année courante
            $year = date('Y', strtotime($date));
            $where_conditions[] = "YEAR(b.date_badgeage) = ?";
            $params[] = $year;
            break;
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    $query = "
        SELECT 
            COALESCE(SUM(b.overtime_hours), 0) as total_overtime,
            COUNT(DISTINCT b.date_badgeage) as days_worked
        FROM badgeages_collab b
        WHERE $where_sql
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Arrondir les heures supplémentaires à 2 décimales
    $result['total_overtime'] = round($result['total_overtime'], 2);
    
    return $result;
}

try {
    // Récupérer les statistiques pour chaque période
    $week_stats = getOvertimeStats($db, $user_id, 'week');
    $month_stats = getOvertimeStats($db, $user_id, 'month');
    $year_stats = getOvertimeStats($db, $user_id, 'year');
    
    // Retourner les résultats
    echo json_encode([
        'success' => true,
        'week' => $week_stats,
        'month' => $month_stats,
        'year' => $year_stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des données: ' . $e->getMessage()
    ]);
}
?>