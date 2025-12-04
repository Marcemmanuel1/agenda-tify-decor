<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

redirectIfNotLoggedIn();

$db = getDB();

// Récupérer l'ID de la notification depuis la requête POST
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = isset($data['id']) ? intval($data['id']) : 0;

if ($notification_id > 0) {
    try {
        // Mettre à jour la notification pour la marquer comme lue
        $stmt = $db->prepare("UPDATE notifications SET lue = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $_SESSION['user_id']]);

        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur de base de données.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'ID de notification invalide.']);
}
?>