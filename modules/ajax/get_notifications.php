<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

redirectIfNotLoggedIn();

$db = getDB();

try {
    // Récupérer les notifications de l'utilisateur
    $stmt = $db->prepare("SELECT id, message, lien, lue, created_at FROM notifications WHERE user_id = ? AND lue = FALSE ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formater la sortie JSON
    echo json_encode($notifications);

} catch (PDOException $e) {
    // En cas d'erreur de la base de données
    echo json_encode(['error' => 'Erreur de base de données.']);
}
?>