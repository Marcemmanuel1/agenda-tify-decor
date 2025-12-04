<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

redirectIfNotLoggedIn();
if (!isAdminGeneral()) {
    header('Location: ../admin_general/');
    exit();
}

// Définir le timezone pour la Côte d'Ivoire
date_default_timezone_set('Africa/Abidjan'); 

// Récupérer les paramètres de pagination et de filtrage
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // Nombre d'éléments par page
$offset = ($page - 1) * $limit;

// Paramètres de filtrage
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$user_filter = isset($_GET['user']) ? (int)$_GET['user'] : '';
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';
$recherche = isset($_GET['recherche']) ? $_GET['recherche'] : '';

// Fonction pour convertir UTC vers heure locale Côte d'Ivoire
function convertToLocalTime($utc_datetime) {
    if (empty($utc_datetime)) return '';
    
    // Créer un objet DateTime en UTC
    $date = new DateTime($utc_datetime, new DateTimeZone('UTC'));
    
    // Convertir vers le timezone de Côte d'Ivoire
    $date->setTimezone(new DateTimeZone('Africa/Abidjan'));
    
    return $date;
}

// Construire la requête pour les logs d'activités (en utilisant la table historique)
$query = "SELECT h.*, u.nom as user_nom, u.prenom as user_prenom, u.role as user_role
          FROM historique h
          JOIN users u ON h.user_id = u.id
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM historique h WHERE 1=1";

// Appliquer les filtres
$params = [];
$count_params = [];

if (!empty($type_filter)) {
    $query .= " AND h.action = :action_type";
    $count_query .= " AND h.action = :action_type";
    $params[':action_type'] = $type_filter;
    $count_params[':action_type'] = $type_filter;
}

if (!empty($user_filter)) {
    $query .= " AND h.user_id = :user_id";
    $count_query .= " AND h.user_id = :user_id";
    $params[':user_id'] = $user_filter;
    $count_params[':user_id'] = $user_filter;
}

if (!empty($date_debut)) {
    $query .= " AND DATE(CONVERT_TZ(h.created_at, '+00:00', '+00:00')) >= :date_debut";
    $count_query .= " AND DATE(CONVERT_TZ(h.created_at, '+00:00', '+00:00')) >= :date_debut";
    $params[':date_debut'] = $date_debut;
    $count_params[':date_debut'] = $date_debut;
}

if (!empty($date_fin)) {
    $query .= " AND DATE(CONVERT_TZ(h.created_at, '+00:00', '+00:00')) <= :date_fin";
    $count_query .= " AND DATE(CONVERT_TZ(h.created_at, '+00:00', '+00:00')) <= :date_fin";
    $params[':date_fin'] = $date_fin;
    $count_params[':date_fin'] = $date_fin;
}

if (!empty($recherche)) {
    $query .= " AND (h.details LIKE :recherche OR u.nom LIKE :recherche OR u.prenom LIKE :recherche)";
    $count_query .= " AND (h.details LIKE :recherche OR u.nom LIKE :recherche OR u.prenom LIKE :recherche)";
    $params[':recherche'] = "%$recherche%";
    $count_params[':recherche'] = "%$recherche%";
}

// Ajouter le tri et la pagination
$query .= " ORDER BY h.created_at DESC LIMIT :limit OFFSET :offset";
$params[':limit'] = $limit;
$params[':offset'] = $offset;

// Compter le nombre total de logs avec les filtres
$db = getDB();
$stmt = $db->prepare($count_query);
foreach ($count_params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_logs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_logs / $limit);

// Récupérer les logs avec pagination
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    if ($key === ':limit' || $key === ':offset') {
        $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des utilisateurs pour le filtre
$users = $db->query("SELECT id, nom, prenom FROM users ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);

// Types d'actions disponibles (basés sur les actions existantes dans la table historique)
$action_types = [
    'connexion' => 'Connexion',
    'deconnexion' => 'Déconnexion',
    'creation' => 'Création',
    'modification' => 'Modification',
    'suppression' => 'Suppression',
    'statut' => 'Changement de statut',
    'paiement' => 'Paiement',
    'export' => 'Export',
    'import' => 'Import',
    'systeme' => 'Système',
    'deconnexion_forcee' => 'Déconnexion forcée'
];

// Récupérer les statistiques d'historique
$stats = [];

// Nombre total d'actions
$stmt = $db->query("SELECT COUNT(*) as total FROM historique");
$stats['total_actions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Nombre d'actions aujourd'hui (utiliser la date locale de Côte d'Ivoire)
$today_ci = date('Y-m-d'); // Date actuelle en Côte d'Ivoire
$stmt = $db->prepare("SELECT COUNT(*) as today FROM historique 
                     WHERE DATE(CONVERT_TZ(created_at, '+00:00', '+00:00')) = :today");
$stmt->bindValue(':today', $today_ci);
$stmt->execute();
$stats['actions_aujourdhui'] = $stmt->fetch(PDO::FETCH_ASSOC)['today'];

// Actions par type
$stmt = $db->query("SELECT action, COUNT(*) as count 
                   FROM historique 
                   GROUP BY action 
                   ORDER BY count DESC");
$stats['actions_par_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Utilisateur avec le plus d'actions
$stmt = $db->query("SELECT u.id, u.nom, u.prenom, COUNT(h.id) as count 
                   FROM historique h 
                   JOIN users u ON h.user_id = u.id 
                   GROUP BY h.user_id 
                   ORDER BY count DESC 
                   LIMIT 1");
$stats['top_user'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Dernière activité
$stmt = $db->query("SELECT h.*, u.nom, u.prenom 
                   FROM historique h 
                   JOIN users u ON h.user_id = u.id 
                   ORDER BY h.created_at DESC 
                   LIMIT 1");
$stats['derniere_activite'] = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique Complet du Système - Agenda Rendez-vous</title>
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
            --violet: #6f42c1;
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

        .btn-info {
            background-color: var(--bleu);
            color: white;
        }

        .btn-info:hover {
            background-color: #138496;
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

        .badge-connexion {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-deconnexion {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-creation {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .badge-modification {
            background-color: #e2e3e5;
            color: #383d41;
        }

        .badge-suppression {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-statut {
            background-color: #d6d8db;
            color: #1b1e21;
        }

        .badge-paiement {
            background-color: #cce7ff;
            color: #004085;
        }

        .badge-export {
            background-color: #c3e6cb;
            color: #155724;
        }

        .badge-import {
            background-color: #b8daff;
            color: #004085;
        }

        .badge-systeme {
            background-color: #f5c6cb;
            color: #721c24;
        }

        .badge-deconnexion_forcee {
            background-color: #ffcccc;
            color: #cc0000;
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

        .log-details {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            border-left: 4px solid var(--doré-clair);
            font-size: 0.9rem;
        }

        .action-icon {
            width: 30px;
            text-align: center;
        }

        .user-role {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .export-buttons {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }

        @media (max-width: 1024px) {
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .export-buttons {
                margin-left: 0;
                margin-top: 1rem;
                justify-content: flex-start;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table {
                font-size: 0.9rem;
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
    
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Historique Complet du Système</h1>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="card">
                <h3>Actions totales</h3>
                <div class="stat-number"><?= $stats['total_actions'] ?></div>
                <div class="stat-details">
                    <div>
                        <span>Aujourd'hui</span>
                        <span><?= $stats['actions_aujourdhui'] ?></span>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h3>Utilisateur le plus actif</h3>
                <div class="stat-number"><?= $stats['top_user'] ? $stats['top_user']['count'] : '0' ?></div>
                <div class="stat-details">
                    <div>
                        <span>Actions</span>
                        <span>
                            <?php if ($stats['top_user']): ?>
                                <?= $stats['top_user']['prenom'] ?> <?= $stats['top_user']['nom'] ?>
                            <?php else: ?>
                                Aucun
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h3>Dernière activité</h3>
                <div class="stat-details">
                    <?php if ($stats['derniere_activite']): 
                        $lastActivityTime = convertToLocalTime($stats['derniere_activite']['created_at']);
                    ?>
                    <div>
                        <span>Type</span>
                        <span class="badge badge-<?= $stats['derniere_activite']['action'] ?>">
                            <?= $action_types[$stats['derniere_activite']['action']] ?? $stats['derniere_activite']['action'] ?>
                        </span>
                    </div>
                    <div>
                        <span>Utilisateur</span>
                        <span><?= $stats['derniere_activite']['prenom'] ?> <?= $stats['derniere_activite']['nom'] ?></span>
                    </div>
                    <div>
                        <span>Heure</span>
                        <span><?= $lastActivityTime ? $lastActivityTime->format('H:i') : 'N/A' ?></span>
                    </div>
                    <?php else: ?>
                        <p>Aucune activité récente</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="filters">
            <div class="filter-group">
                <label for="type">Type d'action</label>
                <select id="type" name="type" onchange="updateFilters()">
                    <option value="">Tous les types</option>
                    <?php foreach ($action_types as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $type_filter == $key ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="user">Utilisateur</label>
                <select id="user" name="user" onchange="updateFilters()">
                    <option value="">Tous les utilisateurs</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="date_debut">Date début</label>
                <input type="date" id="date_debut" name="date_debut" value="<?= $date_debut ?>" onchange="updateFilters()">
            </div>
            
            <div class="filter-group">
                <label for="date_fin">Date fin</label>
                <input type="date" id="date_fin" name="date_fin" value="<?= $date_fin ?>" onchange="updateFilters()">
            </div>
            
            <div class="filter-group">
                <label for="recherche">Recherche</label>
                <input type="text" id="recherche" name="recherche" placeholder="Mot-clé..." value="<?= htmlspecialchars($recherche) ?>" onchange="updateFilters()">
            </div>
            
            <div class="filter-group">
                <button class="btn btn-primary" onclick="resetFilters()">
                    <i class="fas fa-sync-alt"></i> Réinitialiser
                </button>
            </div>
        </div>
        
        <!-- Tableau des logs d'activités -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Journal des activités système</h2>
                <span class="badge badge-info"><?= $total_logs ?> éléments</span>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Utilisateur</th>
                            <th>Type</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): 
                                $action_icons = [
                                    'connexion' => 'fas fa-sign-in-alt',
                                    'deconnexion' => 'fas fa-sign-out-alt',
                                    'creation' => 'fas fa-plus-circle',
                                    'modification' => 'fas fa-edit',
                                    'suppression' => 'fas fa-trash-alt',
                                    'statut' => 'fas fa-exchange-alt',
                                    'paiement' => 'fas fa-money-bill-wave',
                                    'export' => 'fas fa-download',
                                    'import' => 'fas fa-upload',
                                    'systeme' => 'fas fa-cogs',
                                    'deconnexion_forcee' => 'fas fa-user-slash'
                                ];
                                $action_icon = $action_icons[$log['action']] ?? 'fas fa-circle';
                                
                                // Convertir l'heure vers le timezone local
                                $localTime = convertToLocalTime($log['created_at']);
                            ?>
                            <tr>
                                <td>
                                    <?php if ($localTime): ?>
                                        <?= $localTime->format('d/m/Y H:i:s') ?>
                                    <?php else: ?>
                                        <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($log['user_prenom'] . ' ' . $log['user_nom']) ?></strong>
                                        <div class="user-role"><?= ucfirst(str_replace('_', ' ', $log['user_role'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $log['action'] ?>">
                                        <i class="<?= $action_icon ?>"></i>
                                        <?= $action_types[$log['action']] ?? $log['action'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="log-details">
                                        <?= nl2br(htmlspecialchars($log['details'])) ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <h3>Aucune activité trouvée</h3>
                                        <p>Aucune action ne correspond à vos critères de recherche.</p>
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
                    <a href="?page=<?= $page - 1 ?>&type=<?= $type_filter ?>&user=<?= $user_filter ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>&recherche=<?= urlencode($recherche) ?>">
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
                        <a href="?page=<?= $i ?>&type=<?= $type_filter ?>&user=<?= $user_filter ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>&recherche=<?= urlencode($recherche) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&type=<?= $type_filter ?>&user=<?= $user_filter ?>&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>&recherche=<?= urlencode($recherche) ?>">
                        Suivant &raquo;
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function updateFilters() {
            const type = document.getElementById('type').value;
            const user = document.getElementById('user').value;
            const date_debut = document.getElementById('date_debut').value;
            const date_fin = document.getElementById('date_fin').value;
            const recherche = document.getElementById('recherche').value;
            
            let url = '?page=1';
            if (type) url += '&type=' + encodeURIComponent(type);
            if (user) url += '&user=' + encodeURIComponent(user);
            if (date_debut) url += '&date_debut=' + encodeURIComponent(date_debut);
            if (date_fin) url += '&date_fin=' + encodeURIComponent(date_fin);
            if (recherche) url += '&recherche=' + encodeURIComponent(recherche);
            
            window.location.href = url;
        }
        
        function resetFilters() {
            window.location.href = '?page=1';
        }
        
        function exporterPDF() {
            alert('Fonctionnalité d\'export PDF à implémenter');
            // Implémenter l'export PDF ici
        }
        
        function exporterCSV() {
            alert('Fonctionnalité d\'export CSV à implémenter');
            // Implémenter l'export CSV ici
        }
    </script>
    
    <script src="../../js/script.js"></script>
</body>
</html>