<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../includes/notifications.php';
require_once '../includes/auth.php';

// Vérifie si l'utilisateur est connecté et a le rôle de planificateur
if (!isLoggedIn() || !isPlanner()) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

$user_id = $_SESSION['user_id'];
// Appelle la fonction de notifications qui interroge la table notification_planif
$notifications = getUnreadNotifications($user_id);

echo json_encode($notifications);