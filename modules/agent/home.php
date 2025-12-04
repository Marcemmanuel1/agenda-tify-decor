<?php


require_once '../../config/db_connect.php';
require_once '../../includes/auth.php'; 
require_once '../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le bon statut
redirectIfNotLoggedIn();

// Vérification supplémentaire du statut utilisateur
checkUserStatus();

// Vérifier l'authentification et le rôle
check_auth(['agent']);

// Récupérer les rendez-vous à venir de l'agent
$stmt = $pdo->prepare("
    SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.commune, c.telephone 
    FROM rendezvous r 
    JOIN clients c ON r.client_id = c.id 
    WHERE r.agent_id = ? AND r.date_rdv >= CURDATE() AND r.statut_rdv = 'En attente'
    ORDER BY r.date_rdv ASC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$upcoming_appointments = $stmt->fetchAll();

// Récupérer les statistiques
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM rendezvous WHERE agent_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_rdv = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM rendezvous WHERE agent_id = ? AND statut_rdv = 'Effectué'");
$stmt->execute([$_SESSION['user_id']]);
$completed_rdv = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM rendezvous WHERE agent_id = ? AND DATE(date_rdv) = CURDATE()");
$stmt->execute([$_SESSION['user_id']]);
$today_rdv = $stmt->fetch()['total'];

// Récupérer les rendez-vous des 7 prochains jours
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM rendezvous WHERE agent_id = ? AND date_rdv BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute([$_SESSION['user_id']]);
$next7days_rdv = $stmt->fetch()['total'];

// Récupérer les statistiques par statut
$stmt = $pdo->prepare("SELECT statut_rdv, COUNT(*) as count FROM rendezvous WHERE agent_id = ? GROUP BY statut_rdv");
$stmt->execute([$_SESSION['user_id']]);
$status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Agent - <?= defined('APP_NAME') ? APP_NAME : 'Agenda Rendez-vous' ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --vert-sage: #8a9a5b;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Tableau de bord Agent</h1>
        </div>
        
        <!-- Cartes de statistiques avec le même style que le planificateur -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="card">
                <h3>Total rendez-vous</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--vert-sage);">
                    <?= htmlspecialchars($total_rdv) ?>
                </div>
            </div>
            
            <div class="card">
                <h3>Rendez-vous effectués</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--vert-sage);">
                    <?= htmlspecialchars($completed_rdv) ?>
                </div>
            </div>
            
            <div class="card">
                <h3>Rendez-vous aujourd'hui</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--vert-sage);">
                    <?= htmlspecialchars($today_rdv) ?>
                </div>
                <div style="margin-top: 0.5rem;">
                    <span>7 prochains jours: <?= htmlspecialchars($next7days_rdv) ?></span>
                </div>
            </div>
            
            <div class="card">
                <h3>Statut des rendez-vous</h3>
                <div style="margin-top: 0.5rem;">
                    <?php foreach ($status_stats as $stat): ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span><?= htmlspecialchars($stat['statut_rdv']) ?></span>
                            <span><?= htmlspecialchars($stat['count']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Prochains rendez-vous -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Mes prochains rendez-vous</h2>
                <div>
                    <a href="rendezvous.php" class="btn btn-primary" style="text-decoration:none;">Voir tous mes rendez-vous</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming_appointments)): ?>
                    <div class="alert info">Vous n'avez aucun rendez-vous à venir.</div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Date et heure</th>
                            <th>Commune</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_appointments as $appointment): 
                            $statut_class = [
                                'En attente' => 'badge-warning',
                                'Effectué' => 'badge-success',
                                'Annulé' => 'badge-danger',
                                'Modifié' => 'badge-info'
                            ][$appointment['statut_rdv']] ?? 'badge-secondary';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($appointment['client_prenom'] . ' ' . $appointment['client_nom']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($appointment['date_rdv'])) ?></td>
                            <td><?= htmlspecialchars($appointment['commune']) ?></td>
                            <td><?= htmlspecialchars($appointment['telephone']) ?></td>
                            <td><span class="badge <?= $statut_class ?>"><?= $appointment['statut_rdv'] ?></span></td>
                            <td>
                                <a href="rendezvous.php?id=<?= htmlspecialchars($appointment['id']) ?>" class="btn btn-primary" style="text-decoration:none;">
                                    <i class="fas fa-eye"></i> Détails
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../../js/script.js"></script>
</body>
</html>