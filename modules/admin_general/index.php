<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

redirectIfNotLoggedIn();
if (!isAdminGeneral()) {
    header('Location: ../admin_general/');
    exit();
}

// Récupérer les statistiques
$db = getDB();
$stats = [];

// Nombre total d'utilisateurs
$stmt = $db->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Nombre d'utilisateurs par rôle
$stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stats['users_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nombre total de clients
$stmt = $db->query("SELECT COUNT(*) as count FROM clients");
$stats['total_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Nombre total de rendez-vous
$stmt = $db->query("SELECT COUNT(*) as count FROM rendezvous");
$stats['total_rdv'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

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

// Pagination pour les derniers rendez-vous
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; // Nombre d'éléments par page
$offset = ($page - 1) * $limit;

// Compter le nombre total de rendez-vous pour la pagination
$stmt = $db->query("SELECT COUNT(*) as total FROM rendezvous");
$total_rdv = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rdv / $limit);

// Récupérer les rendez-vous avec pagination
$stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, 
                     u.nom as agent_nom, u.prenom as agent_prenom
                     FROM rendezvous r
                     JOIN clients c ON r.client_id = c.id
                     LEFT JOIN users u ON r.agent_id = u.id
                     ORDER BY r.date_rdv DESC 
                     LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin - Agenda Rendez-vous</title>
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
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background-color: var(--blanc);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--ombre);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid var(--ivoire);
        }
        
        .card-title {
            font-size: 1.2rem;
            color: var(--doré-foncé);
            font-weight: 600;
            margin: 0;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
            color: var(--vert-sage);
        }
        
        .stat-details {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--gris-anthracite);
        }
        
        .stat-details div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.3rem;
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
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .btn-primary {
            background-color: var(--vert-sage);
            color: var(--blanc);
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
        
        .btn-primary:hover {
            background-color: #7a8a4b;
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
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
            gap: 0.5rem;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: var(--gris-anthracite);
        }
        
        .pagination a:hover {
            background-color: var(--ivoire);
        }
        
        .pagination .current {
            background-color: var(--vert-sage);
            color: white;
            border-color: var(--vert-sage);
        }
        
        /* Styles responsives */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .table {
                display: block;
                overflow-x: auto;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1rem;
            }
            
            .user-info {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .btn-primary {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body> 
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Tableau de bord Administrateur</h1>
            <span>Bienvenue, <?= $_SESSION['user_prenom'] ?> <?= $_SESSION['user_nom'] ?></span>
        </div>
        
        <div class="stats-grid">
            <!-- Carte Utilisateurs -->
            <div class="card">
                <h3>Utilisateurs</h3>
                <div class="stat-number"><?= $stats['total_users'] ?></div>
                <div class="stat-details">
                    <?php foreach ($stats['users_by_role'] as $user): ?>
                        <div>
                            <span><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></span>
                            <span><?= $user['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Carte Clients -->
            <div class="card">
                <h3>Clients</h3>
                <div class="stat-number"><?= $stats['total_clients'] ?></div>
                <div class="stat-details">
                    <div>
                        <span>Total enregistrés</span>
                        <span><?= $stats['total_clients'] ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Carte Rendez-vous -->
            <div class="card">
                <h3> Rendez-vous</h3>
                <div class="stat-number"><?= $stats['total_rdv'] ?></div>
                <div class="stat-details">
                    <div>
                        <span>Aujourd'hui</span>
                        <span><?= $stats['aujourdhui'] ?></span>
                    </div>
                    <div>
                        <span>7 prochains jours</span>
                        <span><?= $stats['prochains'] ?></span>
                    </div>
                </div>
            </div>
            
        </div>
        
        <div class="stats-grid">
            <!-- Statut des rendez-vous -->
            <div class="card">
                <h3>Statut des rendez-vous</h3>
                <div class="stat-details">
                    <?php foreach ($stats['rdv'] as $rdv): 
                        $color_class = [
                            'En attente' => 'badge-warning',
                            'Effectué' => 'badge-success',
                            'Annulé' => 'badge-danger',
                            'Modifié' => 'badge-info'
                        ][$rdv['statut_rdv']];
                    ?>
                        <div>
                            <span><?= $rdv['statut_rdv'] ?></span>
                            <span class="badge <?= $color_class ?>"><?= $rdv['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Derniers rendez-vous</h2>
                <a href="liste-rdv.php" class="btn-primary">Voir tous les rendez-vous</a>
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
                    <?php if (count($rdvs) > 0): ?>
                        <?php foreach ($rdvs as $rdv):
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
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">Aucun rendez-vous trouvé</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>">&laquo; Précédent</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>">Suivant &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../../js/script.js"></script>
</body>
</html>