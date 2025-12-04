<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

redirectIfNotLoggedIn();
if (!isSuperAdmin()) {
    header('Location: ../planificateur/');
    exit();
}

// Récupérer les statistiques
$db = getDB();
$stats = [];


// Nombre de rendez-vous par statut
$stmt = $db->query("SELECT statut_rdv, COUNT(*) as count FROM rendezvous GROUP BY statut_rdv");
$stats['rdv'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Rendez-vous à venir (7 prochains jours)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM rendezvous 
                     WHERE date_rdv BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$stats['prochains'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Rendez-vous du jour
$stmt = $db->prepare("SELECT COUNT(*) as count FROM rendezvous 
                     WHERE DATE(date_rdv) = CURDATE()");
$stmt->execute();
$stats['aujourdhui'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin - Agenda Rendez-vous</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Tableau de bord Administrateur</h1>
            <span>Bienvenue, <?= $_SESSION['user_prenom'] ?> <?= $_SESSION['user_nom'] ?></span>
        </div>
        
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            
            
            <div class="card">
                <h3>Rendez-vous aujourd'hui</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--vert-sage);">
                    <?= $stats['aujourdhui'] ?>
                </div>
                <div style="margin-top: 0.5rem;">
                    <span>7 prochains jours: <?= $stats['prochains'] ?></span>
                </div>
            </div>
            
            <div class="card">
                <h3>Statut des rendez-vous</h3>
                <div style="margin-top: 0.5rem;">
                    <?php foreach ($stats['rdv'] as $rdv): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span><?= $rdv['statut_rdv'] ?></span>
                            <span><?= $rdv['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Derniers rendez-vous</h2>
                <a href="calendrier.php" class="btn btn-primary" style="text-decoration:none;">Voir tout les Rendez-vous</a>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Agent</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $db->query("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, 
                                       u.nom as agent_nom, u.prenom as agent_prenom
                                       FROM rendezvous r
                                       JOIN clients c ON r.client_id = c.id
                                       LEFT JOIN users u ON r.agent_id = u.id
                                       ORDER BY r.date_rdv DESC LIMIT 5");
                    $rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($rdvs as $rdv):
                        $statut_class = [
                            'En attente' => 'badge-warning',
                            'Effectué' => 'badge-success',
                            'Annulé' => 'badge-danger',
                            'Modifié' => 'badge-info'
                        ][$rdv['statut_rdv']];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($rdv['client_prenom'] . ' ' . $rdv['client_nom']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($rdv['date_rdv'])) ?></td>
                        <td><?= $rdv['agent_id'] ? htmlspecialchars($rdv['agent_prenom'] . ' ' . $rdv['agent_nom']) : 'Non assigné' ?></td>
                        <td><span class="badge <?= $statut_class ?>"><?= $rdv['statut_rdv'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="../../js/script.js"></script>
</body>
</html>