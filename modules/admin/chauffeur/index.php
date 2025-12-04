<?php
require_once '../../../includes/auth.php';
require_once '../../../config/database.php';

redirectIfNotLoggedIn();
if (!isSuperAdmin()) {
    header('Location: ../planificateur/');
    exit();
}

// Définir le fuseau horaire d'Abidjan
date_default_timezone_set('Africa/Abidjan');

// Récupérer les paramètres de filtrage
$filter_chauffeur = isset($_GET['filter_chauffeur']) ? (int)$_GET['filter_chauffeur'] : 0;
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

// Récupérer les paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$db = getDB();

// Récupérer la liste des chauffeurs pour le filtre
$stmt_chauffeurs = $db->query("SELECT id, nom, prenom FROM chauffeurs ORDER BY nom, prenom");
$chauffeurs_list = $stmt_chauffeurs->fetchAll(PDO::FETCH_ASSOC);

// Construction des conditions de filtre
$params = [];
$where_clauses = ["b.type_badge = 'arrivee'"];

if ($filter_chauffeur > 0) {
    $where_clauses[] = "b.chauffeur_id = ?";
    $params[] = $filter_chauffeur;
}

if (!empty($filter_date)) {
    $where_clauses[] = "b.date_badge = ?";
    $params[] = $filter_date;
}

$where_sql = implode(" AND ", $where_clauses);

// Compter le total d'activités
$count_query = "SELECT COUNT(*) as total FROM badgeages b WHERE $where_sql";
$stmt_count = $db->prepare($count_query);
$stmt_count->execute($params);
$totalActivities = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = $totalActivities > 0 ? ceil($totalActivities / $limit) : 1;

// REQUÊTE PRINCIPALE CORRIGÉE - avec bindValue pour les limites
$query = "
    SELECT 
        b.id,
        b.chauffeur_id,
        c.nom as chauffeur_nom,
        c.prenom as chauffeur_prenom,
        b.date_badge as date_activite,
        b.heure_badge as heure_arrivee,
        b.observations as observations_badge,
        b.date_creation,
        (SELECT heure_badge FROM badgeages b2 
         WHERE b2.chauffeur_id = b.chauffeur_id 
         AND b2.date_badge = b.date_badge 
         AND b2.type_badge = 'depart' 
         LIMIT 1) as heure_depart,
        (SELECT observations FROM rapports r 
         WHERE r.chauffeur_id = b.chauffeur_id 
         AND r.date_rapport = b.date_badge 
         LIMIT 1) as observations_rapport
    FROM badgeages b
    JOIN chauffeurs c ON b.chauffeur_id = c.id
    WHERE $where_sql
    ORDER BY b.date_badge DESC, b.heure_badge DESC
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($query);

// Lier les paramètres correctement avec leurs types
$param_index = 1;
foreach ($params as $param) {
    $stmt->bindValue($param_index, $param, PDO::PARAM_STR);
    $param_index++;
}

// Lier LIMIT et OFFSET comme entiers
$stmt->bindValue($param_index, $limit, PDO::PARAM_INT);
$param_index++;
$stmt->bindValue($param_index, $offset, PDO::PARAM_INT);

$stmt->execute();
$activites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activités des Chauffeurs - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <style>
        :root {
            --doré-foncé: #8B5A2B;
            --doré-clair: #C89B66;
            --ivoire: #F5F1EB;
            --blanc: #FFFFFF;
            --gris-anthracite: #333333;
            --vert-sage: #8A9A5B;
            --ombre: rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--ivoire) 0%, var(--blanc) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--blanc);
            border-radius: 20px;
            box-shadow: 0 10px 30px var(--ombre);
            overflow: hidden;
            margin-top: 80px;
        }

        .header {
            background: linear-gradient(135deg, var(--doré-clair), var(--doré-foncé));
            color: var(--blanc);
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content {
            padding: 2rem;
        }

        .filters-section {
            background: var(--ivoire);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--gris-anthracite);
            font-size: 0.9rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 1px solid var(--doré-clair);
            border-radius: 8px;
            background: var(--blanc);
            color: var(--gris-anthracite);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--doré-foncé);
            box-shadow: 0 0 0 3px rgba(139, 90, 43, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--doré-clair), var(--doré-foncé));
            color: var(--blanc);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 90, 43, 0.3);
        }

        .btn-secondary {
            background: var(--blanc);
            color: var(--doré-foncé);
            border: 1px solid var(--doré-clair);
        }

        .btn-secondary:hover {
            background: var(--ivoire);
        }

        .stats-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .stats-info {
            color: var(--gris-anthracite);
            font-weight: 500;
            font-size: 1.1rem;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid var(--ivoire);
            margin-bottom: 2rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .table th {
            background: var(--ivoire);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gris-anthracite);
            border-bottom: 2px solid var(--doré-clair);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--ivoire);
            color: var(--gris-anthracite);
        }

        .table tr:hover {
            background: var(--ivoire);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-complet {
            background: #d1f2eb;
            color: #0c5460;
            border: 1px solid #a3e4d7;
        }

        .badge-en-cours {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .time-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .time-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .time-item i {
            color: var(--doré-clair);
        }

        .duration-badge {
            background: var(--ivoire);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--doré-foncé);
        }

        .observations-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--gris-anthracite);
        }

        .no-data i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 1rem;
            display: block;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination-btn {
            background: var(--blanc);
            border: 1px solid var(--doré-clair);
            color: var(--doré-foncé);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .pagination-btn:hover {
            background: var(--doré-clair);
            color: var(--blanc);
        }

        .pagination-btn.active {
            background: var(--doré-foncé);
            color: var(--blanc);
            border-color: var(--doré-foncé);
        }

        /* Styles pour le Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .modal-container {
            background: var(--blanc);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            max-height: 85vh;
            overflow: hidden;
            animation: slideUp 0.4s ease;
            border: 2px solid var(--doré-clair);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--doré-clair), var(--doré-foncé));
            color: var(--blanc);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal-header h3 {
            font-size: 1.4rem;
            font-weight: 500;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: var(--blanc);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: var(--ivoire);
            border-radius: 12px;
            border-left: 4px solid var(--doré-clair);
        }

        .modal-info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .modal-info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--doré-foncé);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-info-value {
            font-size: 1rem;
            color: var(--gris-anthracite);
            font-weight: 500;
        }

        .modal-observations-container {
            background: var(--ivoire);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--doré-clair);
        }

        .modal-observations-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--doré-foncé);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-observations-content {
            color: var(--gris-anthracite);
            line-height: 1.7;
            font-size: 1rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
            background: var(--blanc);
            border-radius: 8px;
            border: 1px solid rgba(139, 90, 43, 0.1);
        }

        .modal-observations-content::-webkit-scrollbar {
            width: 6px;
        }

        .modal-observations-content::-webkit-scrollbar-track {
            background: var(--ivoire);
            border-radius: 3px;
        }

        .modal-observations-content::-webkit-scrollbar-thumb {
            background: var(--doré-clair);
            border-radius: 3px;
        }

        .modal-observations-content::-webkit-scrollbar-thumb:hover {
            background: var(--doré-foncé);
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            background: var(--ivoire);
            border-top: 1px solid var(--doré-clair);
            display: flex;
            justify-content: flex-end;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--doré-foncé);
            color: var(--doré-foncé);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: var(--doré-foncé);
            color: var(--blanc);
            transform: translateY(-2px);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                margin-top: 70px;
            }

            .content {
                padding: 1rem;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .stats-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .table {
                font-size: 0.8rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
            }

            .modal-container {
                margin: 10px;
                max-height: 90vh;
            }

            .modal-header {
                padding: 1rem 1.5rem;
            }

            .modal-body {
                padding: 1.5rem;
            }

            .modal-info {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
  <?php include '../../../includes/header.php'; ?>

    <div class="container">
        <div class="header">
            <a href="../" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            <h1 style=""><i class="fas fa-car"></i> Activités du Chauffeur</h1>
        </div>

        <div class="content">
            <!-- Section Filtres -->
            <div class="filters-section">
                <h2 style="color: var(--gris-anthracite); margin-bottom: 1rem;">
                    <i class="fas fa-filter"></i> Filtres de Recherche
                </h2>
                
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="filter_chauffeur">
                                <i class="fas fa-user"></i> Sélectionner un chauffeur
                            </label>
                            <select name="filter_chauffeur" id="filter_chauffeur">
                                <option value="0">Tous les chauffeurs</option>
                                <?php foreach ($chauffeurs_list as $chauffeur): ?>
                                    <option value="<?= $chauffeur['id'] ?>" 
                                        <?= $filter_chauffeur == $chauffeur['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($chauffeur['prenom'] . ' ' . $chauffeur['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_date">
                                <i class="fas fa-calendar"></i> Filtrer par date
                            </label>
                            <input type="date" name="filter_date" id="filter_date" 
                                   value="<?= htmlspecialchars($filter_date) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Appliquer les filtres
                                </button>
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Réinitialiser
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Section Résultats -->
            <div class="stats-bar">
                <div class="stats-info">
                    <i class="fas fa-chart-bar"></i>
                    Page <?= $page ?> sur <?= $totalPages ?> - 
                    <?= $totalActivities ?> activité(s) trouvée(s)
                </div>
            </div>

            <!-- Tableau des activités -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Chauffeur</th>
                            <th>Date</th>
                            <th>Horaires</th>
                            <th>Statut</th>
                            <th>Observations</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activites)): ?>
                            <tr>
                                <td colspan="6" class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <h3>Aucune activité trouvée</h3>
                                    <p>
                                        <?php if ($totalActivities === 0): ?>
                                            Aucune donnée d'activité n'a été enregistrée pour le moment.
                                            <br><small>Les chauffeurs doivent d'abord badger leur arrivée.</small>
                                        <?php else: ?>
                                            Aucune activité ne correspond à vos critères de filtrage.
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activites as $activite): ?>
                                <?php
                                    // Déterminer le statut
                                    $statut = $activite['heure_depart'] ? 'complet' : 'en_cours';
                                    
                                    // Calculer la durée si départ existe
                                    $duree = null;
                                    if ($activite['heure_depart']) {
                                        $arrivee_time = new DateTime($activite['date_activite'] . ' ' . $activite['heure_arrivee']);
                                        $depart_time = new DateTime($activite['date_activite'] . ' ' . $activite['heure_depart']);
                                        $interval = $arrivee_time->diff($depart_time);
                                        $duree = $interval->format('%Hh %Im');
                                    }
                                    
                                    // Combiner les observations
                                    $observations = $activite['observations_rapport'] ?: $activite['observations_badge'];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($activite['chauffeur_prenom'] . ' ' . $activite['chauffeur_nom']) ?></strong>
                                    </td>
                                    <td>
                                        <i class="fas fa-calendar-day" style="color: var(--doré-clair);"></i>
                                        <?= date('d/m/Y', strtotime($activite['date_activite'])) ?>
                                    </td>
                                    <td>
                                        <div class="time-display">
                                            <div class="time-item">
                                                <i class="fas fa-sign-in-alt"></i>
                                                <span><?= date('H:i', strtotime($activite['heure_arrivee'])) ?></span>
                                            </div>
                                            <?php if ($activite['heure_depart']): ?>
                                                <div class="time-item">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                    <span><?= date('H:i', strtotime($activite['heure_depart'])) ?></span>
                                                </div>
                                                <?php if ($duree): ?>
                                                    <span class="duration-badge">
                                                        <i class="fas fa-clock"></i> <?= $duree ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #856404;">
                                                    <i class="fas fa-hourglass-half"></i> En cours
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($statut === 'complet'): ?>
                                            <span class="badge badge-complet">
                                                <i class="fas fa-check-circle"></i>
                                                Complet
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-en-cours">
                                                <i class="fas fa-spinner"></i>
                                                En cours
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="observations-cell">
                                        <?php if (!empty($observations)): ?>
                                            <?= htmlspecialchars(substr($observations, 0, 50)) ?>...
                                        <?php else: ?>
                                            <span style="color: var(--gris-anthracite); opacity: 0.6;">Aucune observation</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($observations)): ?>
                                            <button class="btn btn-primary" 
                                                    onclick='showObservations(`<?= addslashes($observations) ?>`, `<?= addslashes($activite['chauffeur_prenom'] . ' ' . $activite['chauffeur_nom']) ?>`, `<?= date('d/m/Y', strtotime($activite['date_activite'])) ?>`)'>
                                                <i class="fas fa-eye"></i>
                                                Voir
                                            </button>
                                        <?php else: ?>
                                            <span style="color: var(--gris-anthracite); opacity: 0.6;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_params = array_filter([
                        'filter_chauffeur' => $filter_chauffeur,
                        'filter_date' => $filter_date
                    ]);
                    $query_string = http_build_query($query_params);
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="?page=1&<?= $query_string ?>" class="pagination-btn">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?= $page - 1 ?>&<?= $query_string ?>" class="pagination-btn">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="?page=<?= $i ?>&<?= $query_string ?>" class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&<?= $query_string ?>" class="pagination-btn">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?= $totalPages ?>&<?= $query_string ?>" class="pagination-btn">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pour afficher les observations -->
    <div class="modal-overlay" id="observations-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-clipboard-list"></i>
                    Rapport d'Activité
                </h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="modal-info">
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-user"></i> Chauffeur
                        </span>
                        <span class="modal-info-value" id="modal-chauffeur"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-calendar-day"></i> Date
                        </span>
                        <span class="modal-info-value" id="modal-date"></span>
                    </div>
                </div>
                
                <div class="modal-observations-container">
                    <div class="modal-observations-label">
                        <i class="fas fa-sticky-note"></i>
                        Observations
                    </div>
                    <div class="modal-observations-content" id="modal-observations"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showObservations(observations, chauffeur, date) {
            document.getElementById('modal-chauffeur').textContent = chauffeur;
            document.getElementById('modal-date').textContent = date;
            document.getElementById('modal-observations').textContent = observations;
            
            const modal = document.getElementById('observations-modal');
            modal.style.display = 'flex';
            
            // Empêcher le défilement du body quand le modal est ouvert
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('observations-modal');
            modal.style.display = 'none';
            
            // Rétablir le défilement du body
            document.body.style.overflow = 'auto';
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('observations-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Animation des lignes du tableau
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.table tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.4s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>