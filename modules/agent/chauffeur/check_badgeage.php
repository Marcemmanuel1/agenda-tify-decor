<?php
// check_badgeage.php
require_once 'config.php';

$chauffeur_id = getChauffeurId();
$today = date('Y-m-d');

try {
    // Vérifier si le chauffeur a déjà badgé aujourd'hui
    $stmt = $pdo->prepare("SELECT type_badge, heure_badge FROM badgeages WHERE chauffeur_id = ? AND date_badge = ?");
    $stmt->execute([$chauffeur_id, $today]);
    $badgeages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $arrival = null;
    $departure = null;
    
    foreach ($badgeages as $badge) {
        if ($badge['type_badge'] === 'arrivee') {
            $arrival = date('H:i', strtotime($badge['heure_badge']));
        } elseif ($badge['type_badge'] === 'depart') {
            $departure = date('H:i', strtotime($badge['heure_badge']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'arrival' => $arrival,
        'departure' => $departure
    ]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
}
?>