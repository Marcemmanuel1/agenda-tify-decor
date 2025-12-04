<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/notifications.php';
require_once '../../includes/auth.php';

// Vérifie les autorisations avant de procéder
if (!isLoggedIn() || !isPlanner()) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit();
}

// Récupère l'ID de la notification depuis le corps de la requête JSON
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = isset($data['id']) ? intval($data['id']) : 0;

if ($notification_id > 0) {
    $result = markNotificationAsRead($notification_id);
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false, 'error' => 'ID de notification invalide.']);
}