<?php

// Obtenir la couleur du badge pour les activités de rendez-vous
function getRdvBadgeColor($type)
{
    $type = strtolower(trim($type ?? ''));
    switch ($type) {
        case 'création':
            return 'success';
        case 'modification':
            return 'info';
        case 'suppression':
            return 'danger';
        case 'confirmation':
            return 'primary';
        case 'annulation':
            return 'warning';
        case 'paiement':
            return 'secondary';
        default:
            return 'secondary';
    }
}

// Enregistrer une activité dans l'historique
function logActivity($userId, $type, $action, $details, $lien = null)
{
    global $db;

    try {
        $stmt = $db->prepare("INSERT INTO historiques (utilisateur_id, type, action, details, lien) 
                             VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $type, $action, $details, $lien]);
    } catch (PDOException $e) {
        error_log("Erreur logActivity: " . $e->getMessage());
        return false;
    }
}

// Récupérer l'historique des activités
function getActivityHistory($page = 1, $perPage = 10)
{
    global $db;

    // Calculer l'offset pour la pagination
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT h.date, CONCAT(u.prenom, ' ', u.nom) as utilisateur, h.type, h.action, h.details, h.lien 
            FROM historiques h 
            JOIN users u ON u.id = h.utilisateur_id 
            WHERE 1=1";

    $params = [];
    $countParams = [];

    // Filtrer par type
    if (isset($_GET['type']) && !empty($_GET['type'])) {
        $sql .= " AND h.type = ?";
        $params[] = $_GET['type'];
        $countParams[] = $_GET['type'];
    }

    // Filtrer par utilisateur
    if (isset($_GET['utilisateur']) && !empty($_GET['utilisateur'])) {
        $sql .= " AND h.utilisateur_id = ?";
        $params[] = $_GET['utilisateur'];
        $countParams[] = $_GET['utilisateur'];
    }

    // Filtrer par date
    if (isset($_GET['date_debut']) && !empty($_GET['date_debut'])) {
        $sql .= " AND DATE(h.date) >= ?";
        $params[] = $_GET['date_debut'];
        $countParams[] = $_GET['date_debut'];
    }

    if (isset($_GET['date_fin']) && !empty($_GET['date_fin'])) {
        $sql .= " AND DATE(h.date) <= ?";
        $params[] = $_GET['date_fin'];
        $countParams[] = $_GET['date_fin'];
    }

    // Requête pour le nombre total d'éléments
    $countSql = "SELECT COUNT(*) FROM historiques h WHERE 1=1" . substr($sql, strpos($sql, "WHERE 1=1") + 9);
    $stmt = $db->prepare($countSql);
    $stmt->execute($countParams);
    $totalItems = (int) $stmt->fetchColumn();
    $totalPages = ceil($totalItems / $perPage);

    // Ajout de la pagination à la requête principale
    $sql .= " ORDER BY h.date DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'totalItems' => $totalItems
        ];
    } catch (PDOException $e) {
        error_log("Erreur SQL dans getActivityHistory: " . $e->getMessage());
        return [
            'items' => [],
            'totalPages' => 0,
            'currentPage' => $page,
            'totalItems' => 0
        ];
    }
}

// Récupérer les statistiques de l'historique
function getHistoryStats()
{
    global $db;

    $stats = [
        'total_activities' => 0,
        'today_activities' => 0,
        'activities_by_type' => []
    ];

    try {
        // Total des activités
        $stmt = $db->query("SELECT COUNT(*) as total FROM historiques");
        $stats['total_activities'] = (int) $stmt->fetchColumn();

        // Activités aujourd'hui
        $stmt = $db->query("SELECT COUNT(*) as total FROM historiques WHERE DATE(date) = CURDATE()");
        $stats['today_activities'] = (int) $stmt->fetchColumn();

        // Activités par type
        $stmt = $db->query("SELECT type, COUNT(*) as count FROM historiques GROUP BY type ORDER BY count DESC");
        $stats['activities_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } catch (PDOException $e) {
        error_log("Erreur dans getHistoryStats: " . $e->getMessage());
    }

    return $stats;
}



// Exemples d'utilisation dans votre application :
// logActivity($_SESSION['user_id'], 'connexion', 'Connexion réussie au système');
// logActivity($_SESSION['user_id'], 'creation', 'Création d\'un nouveau rendez-vous', 'rendezvous', $rdv_id);
// logActivity($_SESSION['user_id'], 'modification', 'Modification du statut du rendez-vous', 'rendezvous', $rdv_id);