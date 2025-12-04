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

// Vérifier le rôle (seuls planificateur et admin peuvent ajouter des événements)
$user_role = $_SESSION['user_role'];
if ($user_role !== 'planificateur' && $user_role !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission refusée']);
    exit();
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);

// Validation des données requises
$required_fields = ['nom', 'prenom', 'genre', 'commune', 'telephone', 'date_contact', 'date_rdv', 'statut_paiement', 'motif'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champ manquant: ' . $field]);
        exit();
    }
}

$db = getDB();

try {
    $db->beginTransaction();
    
    // Vérifier si le client existe déjà
    $stmt = $db->prepare("SELECT id FROM clients WHERE nom = ? AND prenom = ? AND telephone = ?");
    $stmt->execute([$input['nom'], $input['prenom'], $input['telephone']]);
    $client_id = $stmt->fetchColumn();
    
    // Créer le client s'il n'existe pas
    if (!$client_id) {
        $stmt = $db->prepare("INSERT INTO clients (nom, prenom, genre, commune, telephone) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$input['nom'], $input['prenom'], $input['genre'], $input['commune'], $input['telephone']]);
        $client_id = $db->lastInsertId();
    }
    
    // Convertir les dates au format MySQL
    $date_contact_mysql = date('Y-m-d H:i:s', strtotime($input['date_contact']));
    $date_rdv_mysql = date('Y-m-d H:i:s', strtotime($input['date_rdv']));
    
    // Créer le rendez-vous
    $agent_id = isset($input['agent_id']) ? intval($input['agent_id']) : null;
    
    $stmt = $db->prepare("INSERT INTO rendezvous (client_id, planificateur_id, agent_id, date_contact, date_rdv, statut_paiement, motif) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$client_id, $_SESSION['user_id'], $agent_id, $date_contact_mysql, $date_rdv_mysql, $input['statut_paiement'], $input['motif']]);
    
    $rdv_id = $db->lastInsertId();
    
    // Créer une notification pour l'agent si assigné
    if ($agent_id) {
        $message_notif = "Nouveau rendez-vous assigné: {$input['prenom']} {$input['nom']} le " . date('d/m/Y à H:i', strtotime($input['date_rdv']));
        $stmt = $db->prepare("INSERT INTO notifications (user_id, message, lien) VALUES (?, ?, ?)");
        $stmt->execute([$agent_id, $message_notif, "../agent/rendezvous.php?id=$rdv_id"]);
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Rendez-vous créé avec succès', 'id' => $rdv_id]);
    
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}