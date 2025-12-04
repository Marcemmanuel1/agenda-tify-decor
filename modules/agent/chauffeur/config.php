<?php
// config.php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$dbname = 'agenda_rdv';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

// Fonction pour obtenir l'ID du chauffeur (à adapter selon votre système d'authentification)
function getChauffeurId() {
    // Pour l'exemple, on utilise l'ID 1
    // En production, vous devriez utiliser l'ID de l'utilisateur connecté
    return 1;
}
?>