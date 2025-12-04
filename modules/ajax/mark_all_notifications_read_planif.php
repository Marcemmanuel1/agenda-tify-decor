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

$user_id = $_SESSION['user_id'];
$result = markAllNotificationsAsRead($user_id);

echo json_encode(['success' => $result]);