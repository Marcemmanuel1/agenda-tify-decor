<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Vérifier si l'utilisateur est connecté et a le bon statut
redirectIfNotLoggedIn();

// Vérification supplémentaire du statut utilisateur
checkUserStatus();

if (!isPlanificateur() && !isSuperAdmin()) {
    header('Location: ../agent/');
    exit();
}

$db = getDB();

// Récupérer les statistiques pour le planificateur
$stats = [];

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

// Rendez-vous créés par ce planificateur
$stmt = $db->prepare("SELECT COUNT(*) as count FROM rendezvous 
                     WHERE planificateur_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['mes_rdv'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Rendez-vous par statut
$stmt = $db->prepare("SELECT statut_rdv, COUNT(*) as count FROM rendezvous 
                     WHERE planificateur_id = ? GROUP BY statut_rdv");
$stmt->execute([$_SESSION['user_id']]);
$stats['statuts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Planificateur - Agenda Rendez-vous</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --doré-foncé: #8B5A2B;
            --doré-clair: #C89B66;
            --ivoire: #F5F1EB;
            --blanc: #FFFFFF;
            --gris-anthracite: #333333;
            --vert-sage: #8A9A5B;
            --ombre: rgba(0, 0, 0, 0.1);
            --orange: #fd7e14;
            --rouge: #dc3545;
            --bleu: #17a2b8;
            --vert-clair: #d4edda;
            --vert-fonce: #28a745;
            --rouge-clair: #f8d7da;
            --rouge-fonce: #dc3545;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--ivoire);
        }

        .page-title {
            color: var(--doré-foncé);
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background-color: var(--blanc);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--ombre);
            padding: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--ivoire);
        }

        .card-title {
            color: var(--doré-foncé);
            margin: 0;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--vert-sage);
            color: var(--blanc);
        }

        .btn-primary:hover {
            background-color: #7a8a4b;
        }

        .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-success {
            background-color: var(--vert-clair);
            color: var(--vert-fonce);
        }

        .badge-danger {
            background-color: var(--rouge-clair);
            color: var(--rouge-fonce);
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th,
        .table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: var(--ivoire);
            color: var(--doré-foncé);
            font-weight: 600;
        }

        .table tr:hover {
            background-color: rgba(245, 241, 235, 0.5);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Tableau de bord Planificateur</h1>
            <span>Bienvenue, <?= $_SESSION['user_prenom'] ?> <?= $_SESSION['user_nom'] ?></span>
        </div>
        
        <div class="stats-grid">
            <div class="card">
                <h3>Mes rendez-vous</h3>
                <div style="font-size: 2rem; font-weight: bold; color: var(--doré-foncé);">
                    <?= $stats['mes_rdv'] ?>
                </div>
                <div style="margin-top: 0.5rem;">
                    <span>Créés par moi</span>
                </div>
            </div>
            
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
                <h3>Statut de mes rendez-vous</h3>
                <div style="margin-top: 0.5rem;">
                    <?php foreach ($stats['statuts'] as $statut): 
                        $statut_class = [
                            'En attente' => 'badge-warning',
                            'Effectué' => 'badge-success',
                            'Annulé' => 'badge-danger',
                            'Modifié' => 'badge-info'
                        ][$statut['statut_rdv']];
                    ?>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span><?= $statut['statut_rdv'] ?></span>
                            <span class="badge <?= $statut_class ?>"><?= $statut['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Mes prochains rendez-vous</h2>
                <a href="add_rdv.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau rendez-vous</a>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Agent</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, 
                                       u.nom as agent_nom, u.prenom as agent_prenom
                                       FROM rendezvous r
                                       JOIN clients c ON r.client_id = c.id
                                       LEFT JOIN users u ON r.agent_id = u.id
                                       WHERE r.planificateur_id = ? AND r.date_rdv >= NOW()
                                       ORDER BY r.date_rdv ASC LIMIT 5");
                    $stmt->execute([$_SESSION['user_id']]);
                    $rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($rdvs) > 0):
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
                        <td>
                            <a href="add_rdv.php?edit=<?= $rdv['id'] ?>" class="btn btn-primary"><i class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">Aucun rendez-vous à venir</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script src="../../js/script.js"></script>
</body>
</html>