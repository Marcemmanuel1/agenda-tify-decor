<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Vérifier l'accès
if (!isAdminGeneral()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit();
}

// Récupérer le timestamp du dernier check
$last_timestamp = isset($_GET['last_timestamp']) ? (int)$_GET['last_timestamp'] : null;

$db = getDB();

if ($last_timestamp) {
    // Vérifier s'il y a de nouvelles activités depuis le dernier timestamp
    $query = "SELECT COUNT(*) as count, MAX(UNIX_TIMESTAMP(created_at)) as latest_timestamp 
              FROM historique 
              WHERE UNIX_TIMESTAMP(created_at) > :last_timestamp";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':last_timestamp', $last_timestamp, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'has_new_activities' => $result['count'] > 0,
        'latest_timestamp' => $result['latest_timestamp'] ? (int)$result['latest_timestamp'] : null
    ]);
} else {
    // Premier check - juste récupérer le timestamp le plus récent
    $query = "SELECT MAX(UNIX_TIMESTAMP(created_at)) as latest_timestamp FROM historique";
    $stmt = $db->query($query);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'has_new_activities' => false,
        'latest_timestamp' => $result['latest_timestamp'] ? (int)$result['latest_timestamp'] : null
    ]);
}
?>