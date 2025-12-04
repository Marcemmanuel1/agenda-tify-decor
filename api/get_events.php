<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Construire la requête selon le rôle
if ($user_role === 'super_admin') {
    // Super admin voit tous les rendez-vous
    $sql = "SELECT r.id, r.date_rdv as date_debut, r.statut_rdv as statut, 
                   CONCAT(c.prenom, ' ', c.nom) as client, c.telephone, c.commune, r.motif,
                   DATE_FORMAT(r.date_rdv, '%d/%m/%Y à %H:%i') as date_complete
            FROM rendezvous r
            JOIN clients c ON r.client_id = c.id
            ORDER BY r.date_rdv";
    $stmt = $db->query($sql);
} elseif ($user_role === 'planificateur') {
    // Planificateur voit ses propres rendez-vous
    $sql = "SELECT r.id, r.date_rdv as date_debut, r.statut_rdv as statut, 
                   CONCAT(c.prenom, ' ', c.nom) as client, c.telephone, c.commune, r.motif,
                   DATE_FORMAT(r.date_rdv, '%d/%m/%Y à %H:%i') as date_complete
            FROM rendezvous r
            JOIN clients c ON r.client_id = c.id
            WHERE r.planificateur_id = ?
            ORDER BY r.date_rdv";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
} else {
    // Agent voit seulement les rendez-vous qui lui sont assignés
    $sql = "SELECT r.id, r.date_rdv as date_debut, r.statut_rdv as statut, 
                   CONCAT(c.prenom, ' ', c.nom) as client, c.telephone, c.commune, r.motif,
                   DATE_FORMAT(r.date_rdv, '%d/%m/%Y à %H:%i') as date_complete
            FROM rendezvous r
            JOIN clients c ON r.client_id = c.id
            WHERE r.agent_id = ?
            ORDER BY r.date_rdv";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id]);
}

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Formater les données pour le calendrier
$formattedEvents = [];
foreach ($events as $event) {
    $formattedEvents[] = [
        'id' => $event['id'],
        'date_debut' => $event['date_debut'],
        'statut' => $event['statut'],
        'client' => $event['client'],
        'telephone' => $event['telephone'],
        'commune' => $event['commune'],
        'motif' => $event['motif'],
        'date_complete' => $event['date_complete'],
        'heure' => date('H:i', strtotime($event['date_debut']))
    ];
}

echo json_encode($formattedEvents);