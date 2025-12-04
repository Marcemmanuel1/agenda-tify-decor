<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

// Vérifier que la requête est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !isset($input['statut'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$rdv_id = intval($input['id']);
$statut = $input['statut'];

// Préparer la requête de mise à jour
$sql = "UPDATE rendezvous SET statut_rdv = ?";
$params = [$statut];

// Ajouter la mise à jour du paiement si le statut est "Effectué"
if ($statut === 'Effectué') {
    $sql .= ", statut_paiement = 'Payé'";
}

$sql .= " WHERE id = ?";
$params[] = $rdv_id;

// Vérifier les permissions selon le rôle
if ($user_role === 'agent') {
    // L'agent ne peut modifier que ses propres rendez-vous
    $stmt = $db->prepare("SELECT id FROM rendezvous WHERE id = ? AND agent_id = ?");
    $stmt->execute([$rdv_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission refusée']);
        exit();
    }
    
    // Finaliser la requête pour l'agent
    $sql .= " AND agent_id = ?";
    $params[] = $user_id;
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);

} elseif ($user_role === 'planificateur' || $user_role === 'super_admin') {
    // Le planificateur et l'admin peuvent modifier tous les rendez-vous
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Rôle non autorisé']);
    exit();
}

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
}
