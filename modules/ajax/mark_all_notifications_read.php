<?php
// Fichier : modules/ajax/mark_all_notifications_read.php

session_start();
header('Content-Type: application/json');

// Assurez-vous que les chemins d'accès sont corrects
require_once '../../config/database.php';
require_once '../../includes/auth.php'; 

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé.']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getDB(); // Utilisation du nom de fonction correct
    
    // Requête de mise à jour
    $sql = "UPDATE notifications SET lue = 1 WHERE user_id = :userId AND lue = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':userId', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Toutes les notifications ont été marquées comme lues.']);

} catch (Exception $e) {
    // Capturer l'exception de connexion ou d'exécution
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
