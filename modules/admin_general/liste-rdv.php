<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

redirectIfNotLoggedIn();
if (!isAdminGeneral()) {
    header('Location: ../admin_general/');
    exit();
}

// Récupérer les paramètres de pagination et de filtrage
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Paramètres de filtrage
$statut_filter = isset($_GET['statut']) ? $_GET['statut'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Construire la requête de base avec les filtres
$query = "SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, 
          u.nom as agent_nom, u.prenom as agent_prenom,
          uc.nom as planificateur_nom, uc.prenom as planificateur_prenom
          FROM rendezvous r
          JOIN clients c ON r.client_id = c.id
          LEFT JOIN users u ON r.agent_id = u.id
          JOIN users uc ON r.planificateur_id = uc.id
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM rendezvous r WHERE 1=1";

// Appliquer les filtres
$params = [];
$count_params = [];

if (!empty($statut_filter)) {
    $query .= " AND r.statut_rdv = :statut";
    $count_query .= " AND r.statut_rdv = :statut";
    $params[':statut'] = $statut_filter;
    $count_params[':statut'] = $statut_filter;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(r.date_rdv) = :date_rdv";
    $count_query .= " AND DATE(r.date_rdv) = :date_rdv";
    $params[':date_rdv'] = $date_filter;
    $count_params[':date_rdv'] = $date_filter;
}

// Ajouter le tri et la pagination
$query .= " ORDER BY r.date_rdv DESC LIMIT :limit OFFSET :offset";
$params[':limit'] = $limit;
$params[':offset'] = $offset;

// Compter le nombre total de rendez-vous avec les filtres
$db = getDB();

// Préparer et exécuter la requête de comptage
$stmt = $db->prepare($count_query);
foreach ($count_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_rdv = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rdv / $limit);

// Récupérer les rendez-vous avec pagination
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    if ($key === ':limit' || $key === ':offset') {
        $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les différents statuts pour le filtre
$statuts = $db->query("SELECT DISTINCT statut_rdv FROM rendezvous ORDER BY statut_rdv")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Rendez-vous - Agenda Rendez-vous</title>
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

        .filters {
            background-color: var(--blanc);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--ombre);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        .filter-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gris-anthracite);
        }

        .filter-group select, .filter-group input {
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
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

        .btn-secondary {
            background-color: var(--doré-clair);
            color: var(--blanc);
        }

        .btn-secondary:hover {
            background-color: var(--doré-foncé);
        }

        .table-container {
            overflow-x: auto;
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
            position: sticky;
            top: 0;
        }

        .table tr:hover {
            background-color: rgba(245, 241, 235, 0.5);
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

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
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

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }

        .btn-info {
            background-color: var(--bleu);
            color: white;
        }

        .btn-danger {
            background-color: var(--rouge);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gris-anthracite);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .table {
                font-size: 0.9rem;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Liste des Rendez-vous</h1>
        </div>
        
        <!-- Filtres -->
        <div class="filters">
            <div class="filter-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut" onchange="updateFilters()">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($statuts as $statut): ?>
                    <option value="<?= $statut['statut_rdv'] ?>" <?= $statut_filter == $statut['statut_rdv'] ? 'selected' : '' ?>>
                        <?= $statut['statut_rdv'] ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date">Date spécifique</label>
                <input type="date" id="date" name="date" value="<?= $date_filter ?>" onchange="updateFilters()">
            </div>
            
            <div class="filter-group">
                <button class="btn btn-primary" onclick="resetFilters()">
                    <i class="fas fa-sync-alt"></i> Réinitialiser
                </button>
            </div>
        </div>
        
        <!-- Tableau des rendez-vous -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= $total_rdv ?> rendez-vous trouvés</h2>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Date et Heure</th>
                            <th>Planificateur</th>
                            <th>Agent</th>
                            <th>Statut</th>
                            <th>Paiement</th>
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
                                
                                $paiement_class = $rdv['statut_paiement'] == 'Payé' ? 'badge-success' : 'badge-danger';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($rdv['client_prenom'] . ' ' . $rdv['client_nom']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($rdv['date_rdv'])) ?></td>
                                <td><?= htmlspecialchars($rdv['planificateur_prenom'] . ' ' . $rdv['planificateur_nom']) ?></td>
                                <td><?= $rdv['agent_id'] ? htmlspecialchars($rdv['agent_prenom'] . ' ' . $rdv['agent_nom']) : 'Non assigné' ?></td>
                                <td><span class="badge <?= $statut_class ?>"><?= $rdv['statut_rdv'] ?></span></td>
                                <td><span class="badge <?= $paiement_class ?>"><?= $rdv['statut_paiement'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times"></i>
                                        <h3>Aucun rendez-vous trouvé</h3>
                                        <p>Aucun rendez-vous ne correspond à vos critères de recherche.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&statut=<?= $statut_filter ?>&date=<?= $date_filter ?>">
                        &laquo; Précédent
                    </a>
                <?php endif; ?>
                
                <?php 
                // Afficher un nombre limité de pages autour de la page courante
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $start_page + 4);
                
                // Ajuster si on est près de la fin
                if ($end_page - $start_page < 4 && $start_page > 1) {
                    $start_page = max(1, $end_page - 4);
                }
                
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&statut=<?= $statut_filter ?>&date=<?= $date_filter ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&statut=<?= $statut_filter ?>&date=<?= $date_filter ?>">
                        Suivant &raquo;
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function updateFilters() {
            const statut = document.getElementById('statut').value;
            const date = document.getElementById('date').value;
            
            let url = '?page=1';
            if (statut) url += '&statut=' + encodeURIComponent(statut);
            if (date) url += '&date=' + encodeURIComponent(date);
            
            window.location.href = url;
        }
        
        function resetFilters() {
            window.location.href = '?page=1';
        }
    </script>
    
    <script src="../../js/script.js"></script>
</body>
</html>