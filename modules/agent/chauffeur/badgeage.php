<?php
// badgeage.php
require_once 'config.php';

// Définir le fuseau horaire d'Abidjan, Côte d'Ivoire
date_default_timezone_set('Africa/Abidjan');

// Récupération des données POST
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$observations = $input['observations'] ?? '';
$chauffeur_id = getChauffeurId();
$today = date('Y-m-d');
$current_time = date('H:i:s');

if ($action === 'arrival') {
    // Vérifier si le chauffeur a déjà badgé aujourd'hui
    try {
        $stmt = $pdo->prepare("SELECT id FROM badgeages WHERE chauffeur_id = ? AND date_badge = ? AND type_badge = 'arrivee'");
        $stmt->execute([$chauffeur_id, $today]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Vous avez déjà badgé votre arrivée aujourd\'hui']);
            exit;
        }
        
        // Enregistrement de l'arrivée
        $stmt = $pdo->prepare("INSERT INTO badgeages (chauffeur_id, type_badge, date_badge, heure_badge) VALUES (?, 'arrivee', ?, ?)");
        $stmt->execute([$chauffeur_id, $today, $current_time]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Arrivée enregistrée avec succès',
            'time' => date('H:i')
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
    }
} elseif ($action === 'departure') {
    // Vérifier si le chauffeur a déjà badgé son départ aujourd'hui
    try {
        $stmt = $pdo->prepare("SELECT id FROM badgeages WHERE chauffeur_id = ? AND date_badge = ? AND type_badge = 'depart'");
        $stmt->execute([$chauffeur_id, $today]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Vous avez déjà badgé votre départ aujourd\'hui']);
            exit;
        }
        
        // Vérifier si le chauffeur a badgé son arrivée aujourd'hui
        $stmt = $pdo->prepare("SELECT id FROM badgeages WHERE chauffeur_id = ? AND date_badge = ? AND type_badge = 'arrivee'");
        $stmt->execute([$chauffeur_id, $today]);
        
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Vous devez d\'abord badger votre arrivée']);
            exit;
        }
        
        // Enregistrement du départ et du rapport
        $pdo->beginTransaction();
        
        // Enregistrer le badgeage de départ
        $stmt = $pdo->prepare("INSERT INTO badgeages (chauffeur_id, type_badge, date_badge, heure_badge) VALUES (?, 'depart', ?, ?)");
        $stmt->execute([$chauffeur_id, $today, $current_time]);
        
        // Enregistrer le rapport
        $stmt = $pdo->prepare("INSERT INTO rapports (chauffeur_id, date_rapport, observations) VALUES (?, ?, ?)");
        $stmt->execute([$chauffeur_id, $today, $observations]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Rapport soumis avec succès',
            'time' => date('H:i')
        ]);
    } catch(PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
}
?>