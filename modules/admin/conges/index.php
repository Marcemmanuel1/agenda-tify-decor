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

$db = getDB();

// Traitement de la validation/refus des congés
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['demande_id'])) {
        $demande_id = (int)$_POST['demande_id'];
        $action = $_POST['action'];
        $commentaire = $_POST['commentaire_validation'] ?? '';
        
        $statut = '';
        $message = '';
        
        switch($action) {
            case 'valider':
                $statut = 'approuve';
                $message = 'Demande de congés approuvée avec succès.';
                break;
            case 'refuser':
                $statut = 'refuse';
                $message = 'Demande de congés refusée.';
                break;
            case 'annuler':
                $statut = 'en_attente';
                $message = 'Statut de la demande réinitialisé.';
                break;
        }
        
        if ($statut) {
            try {
                $stmt = $db->prepare("UPDATE demandes_conges 
                                    SET statut = ?, 
                                        commentaire_validation = ?, 
                                        date_validation = NOW(),
                                        validateur_id = ?
                                    WHERE id = ?");
                $stmt->execute([$statut, $commentaire, $_SESSION['user_id'], $demande_id]);
                
                $_SESSION['success_msg'] = $message;
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "Erreur lors de la mise à jour : " . $e->getMessage();
            }
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Récupérer les messages de session
$success_msg = $_SESSION['success_msg'] ?? '';
$error_msg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Récupérer les paramètres de filtrage
$filter_statut = isset($_GET['filter_statut']) ? $_GET['filter_statut'] : '';
$filter_user = isset($_GET['filter_user']) ? (int)$_GET['filter_user'] : 0;
$filter_date_debut = isset($_GET['filter_date_debut']) ? $_GET['filter_date_debut'] : '';
$filter_date_fin = isset($_GET['filter_date_fin']) ? $_GET['filter_date_fin'] : '';

// Récupérer les paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Récupérer la liste des utilisateurs pour le filtre
$stmt_users = $db->query("SELECT id, nom, prenom FROM users ORDER BY nom, prenom");
$users_list = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

// Construction des conditions de filtre pour les demandes de congés
$params = [];
$where_clauses = ["1=1"];

if ($filter_user > 0) {
    $where_clauses[] = "dc.user_id = ?";
    $params[] = $filter_user;
}

if (!empty($filter_statut)) {
    $where_clauses[] = "dc.statut = ?";
    $params[] = $filter_statut;
}

if (!empty($filter_date_debut)) {
    $where_clauses[] = "dc.date_debut >= ?";
    $params[] = $filter_date_debut;
}

if (!empty($filter_date_fin)) {
    $where_clauses[] = "dc.date_fin <= ?";
    $params[] = $filter_date_fin;
}

$where_sql = implode(" AND ", $where_clauses);

// Compter le total de demandes
$count_query = "SELECT COUNT(*) as total FROM demandes_conges dc WHERE $where_sql";
$stmt_count = $db->prepare($count_query);
$stmt_count->execute($params);
$totalDemandes = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = $totalDemandes > 0 ? ceil($totalDemandes / $limit) : 1;

// REQUÊTE PRINCIPALE pour les demandes de congés
$query = "
    SELECT 
        dc.*,
        u.nom as user_nom,
        u.prenom as user_prenom,
        u.email as user_email,
        v.nom as validateur_nom,
        v.prenom as validateur_prenom
    FROM demandes_conges dc
    JOIN users u ON dc.user_id = u.id
    LEFT JOIN users v ON dc.validateur_id = v.id
    WHERE $where_sql
    ORDER BY dc.date_demande DESC
    LIMIT ? OFFSET ?
";

$stmt = $db->prepare($query);

// Lier les paramètres de filtre
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
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques pour le dashboard
$stats_query = "
    SELECT 
        statut,
        COUNT(*) as count
    FROM demandes_conges 
    WHERE date_demande >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY statut
";
$stats_stmt = $db->query($stats_query);
$stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_data = [
    'en_attente' => 0,
    'approuve' => 0,
    'refuse' => 0
];

foreach ($stats as $stat) {
    $stats_data[$stat['statut']] = $stat['count'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Congés - Admin</title>
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
            --rouge: #dc3545;
            --vert: #28a745;
            --orange: #fd7e14;
            --bleu: #0d6efd;
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
            position: relative;
        }

        .header .btn-secondary {
            position: absolute;
            left: 2rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
            margin-left: 9rem;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content {
            padding: 2rem;
        }

        /* Section Statistiques */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--blanc);
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px var(--ombre);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gris-anthracite);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.en_attente .stat-number { color: var(--orange); }
        .stat-card.approuve .stat-number { color: var(--vert); }
        .stat-card.refuse .stat-number { color: var(--rouge); }

        /* Filtres */
        .filters-section {
            background: var(--ivoire);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .btn-success {
            background: linear-gradient(135deg, var(--vert), #198754);
            color: var(--blanc);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--rouge), #b02a37);
            color: var(--blanc);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--orange), #fd7e14);
            color: var(--blanc);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(253, 126, 20, 0.3);
        }

        /* Tableau */
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
            min-width: 1200px;
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

        /* Badges de statut */
        .statut-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .statut-en_attente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .statut-approuve {
            background: #d1f2eb;
            color: #0c5460;
            border: 1px solid #a3e4d7;
        }

        .statut-refuse {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f1b0b7;
        }

        /* Actions */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Alertes */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d1f2eb;
            color: #0c5460;
            border-left-color: var(--vert);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: var(--rouge);
        }

        /* Modal */
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
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
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
            max-height: calc(90vh - 200px);
            overflow-y: auto;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gris-anthracite);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--doré-clair);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--blanc);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--doré-foncé);
            box-shadow: 0 0 0 3px rgba(139, 90, 43, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--ivoire);
        }

        /* États vides */
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

        /* Pagination */
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

            .header .btn-secondary {
                position: static;
                transform: none;
                margin-bottom: 1rem;
            }

            .content {
                padding: 1rem;
            }

            .stats-section {
                grid-template-columns: 1fr;
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

            .action-buttons {
                flex-direction: column;
            }

            .modal-container {
                margin: 10px;
                max-height: 95vh;
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

            .modal-actions {
                flex-direction: column;
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
            <h1><i class="fas fa-umbrella-beach"></i> Gestion des Congés</h1>
            <p>Validation des demandes de congés des collaborateurs</p>
        </div>

        <div class="content">
            <!-- Messages d'alerte -->
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- Section Statistiques -->
            <div class="stats-section">
                <div class="stat-card en_attente">
                    <div class="stat-number"><?= $stats_data['en_attente'] ?></div>
                    <div class="stat-label">En Attente</div>
                    <i class="fas fa-clock" style="color: var(--orange); font-size: 2rem; margin-top: 0.5rem;"></i>
                </div>
                <div class="stat-card approuve">
                    <div class="stat-number"><?= $stats_data['approuve'] ?></div>
                    <div class="stat-label">Approuvés</div>
                    <i class="fas fa-check-circle" style="color: var(--vert); font-size: 2rem; margin-top: 0.5rem;"></i>
                </div>
                <div class="stat-card refuse">
                    <div class="stat-number"><?= $stats_data['refuse'] ?></div>
                    <div class="stat-label">Refusés</div>
                    <i class="fas fa-times-circle" style="color: var(--rouge); font-size: 2rem; margin-top: 0.5rem;"></i>
                </div>
            </div>

            <!-- Section Filtres -->
            <div class="filters-section">
                <h2 style="color: var(--gris-anthracite); margin-bottom: 1rem;">
                    <i class="fas fa-filter"></i> Filtres de Recherche
                </h2>
                
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="filter_user">
                                <i class="fas fa-user"></i> Collaborateur
                            </label>
                            <select name="filter_user" id="filter_user">
                                <option value="0">Tous les collaborateurs</option>
                                <?php foreach ($users_list as $user): ?>
                                    <option value="<?= $user['id'] ?>" 
                                        <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_statut">
                                <i class="fas fa-tag"></i> Statut
                            </label>
                            <select name="filter_statut" id="filter_statut">
                                <option value="">Tous les statuts</option>
                                <option value="en_attente" <?= $filter_statut == 'en_attente' ? 'selected' : '' ?>>En Attente</option>
                                <option value="approuve" <?= $filter_statut == 'approuve' ? 'selected' : '' ?>>Approuvé</option>
                                <option value="refuse" <?= $filter_statut == 'refuse' ? 'selected' : '' ?>>Refusé</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_date_debut">
                                <i class="fas fa-calendar-plus"></i> Date de début
                            </label>
                            <input type="date" name="filter_date_debut" id="filter_date_debut" 
                                   value="<?= htmlspecialchars($filter_date_debut) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="filter_date_fin">
                                <i class="fas fa-calendar-minus"></i> Date de fin
                            </label>
                            <input type="date" name="filter_date_fin" id="filter_date_fin" 
                                   value="<?= htmlspecialchars($filter_date_fin) ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <div class="filter-actions">
                                
                                <a href="?" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Réinitialiser
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Appliquer
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="stats-bar">
                <div class="stats-info">
                    <i class="fas fa-chart-bar"></i>
                    Page <?= $page ?> sur <?= $totalPages ?> - 
                    <?= $totalDemandes ?> demande(s) trouvée(s)
                </div>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Collaborateur</th>
                            <th>Période</th>
                            <th>Type</th>
                            <th>Motif</th>
                            <th>Statut</th>
                            <th>Date Demande</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($demandes)): ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <h3>Aucune demande trouvée</h3>
                                    <p>
                                        <?php if ($totalDemandes === 0): ?>
                                            Aucune demande de congés n'a été soumise pour le moment.
                                        <?php else: ?>
                                            Aucune demande ne correspond à vos critères de filtrage.
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($demandes as $demande): ?>
                                <?php
                                    $type_conge_labels = [
                                        'conges_payes' => 'Congés Payés',
                                        'conges_sans_solde' => 'Congés Sans Solde',
                                        'maladie' => 'Arrêt Maladie',
                                        'familial' => 'Congé Familial',
                                        'maternite' => 'Congé Maternité',
                                        'paternite' => 'Congé Paternité',
                                        'formation' => 'Congé Formation',
                                        'autre' => 'Autre'
                                    ];
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($demande['user_prenom'] . ' ' . $demande['user_nom']) ?></strong>
                                        <br>
                                        <small style="color: #666;"><?= htmlspecialchars($demande['user_email']) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= date('d/m/Y', strtotime($demande['date_debut'])) ?></strong>
                                        <br>
                                        au
                                        <br>
                                        <strong><?= date('d/m/Y', strtotime($demande['date_fin'])) ?></strong>
                                        <br>
                                        <small style="color: #666;">
                                            <?php
                                            $debut = new DateTime($demande['date_debut']);
                                            $fin = new DateTime($demande['date_fin']);
                                            $interval = $debut->diff($fin);
                                            echo ($interval->days + 1) . ' jour(s)';
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($type_conge_labels[$demande['type_conge']] ?? $demande['type_conge']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($demande['motif']) ?>
                                        <?php if (!empty($demande['commentaires'])): ?>
                                            <br>
                                            <small style="color: #666;">
                                                <i class="fas fa-sticky-note"></i>
                                                <?= htmlspecialchars(substr($demande['commentaires'], 0, 50)) ?>...
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="statut-badge statut-<?= $demande['statut'] ?>">
                                            <?php
                                            $statut_labels = [
                                                'en_attente' => 'En Attente',
                                                'approuve' => 'Approuvé',
                                                'refuse' => 'Refusé'
                                            ];
                                            echo $statut_labels[$demande['statut']];
                                            ?>
                                        </span>
                                        <?php if ($demande['validateur_nom']): ?>
                                            <br>
                                            <small style="color: #666;">
                                                Par <?= htmlspecialchars($demande['validateur_prenom'] . ' ' . $demande['validateur_nom']) ?>
                                                <?php if ($demande['date_validation']): ?>
                                                    <br>
                                                    le <?= date('d/m/Y', strtotime($demande['date_validation'])) ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y à H:i', strtotime($demande['date_demande'])) ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick='showDemandeDetails(<?= json_encode($demande) ?>)'>
                                                <i class="fas fa-eye"></i>
                                                Détails
                                            </button>
                                            
                                            <?php if ($demande['statut'] == 'en_attente'): ?>
                                                <button class="btn btn-success btn-sm" 
                                                        onclick='showValidationModal(<?= $demande['id'] ?>, "valider")'>
                                                    <i class="fas fa-check"></i>
                                                    Valider
                                                </button>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick='showValidationModal(<?= $demande['id'] ?>, "refuser")'>
                                                    <i class="fas fa-times"></i>
                                                    Refuser
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-warning btn-sm" 
                                                        onclick='showValidationModal(<?= $demande['id'] ?>, "annuler")'>
                                                    <i class="fas fa-undo"></i>
                                                    Réinitialiser
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_params = array_filter([
                        'filter_user' => $filter_user,
                        'filter_statut' => $filter_statut,
                        'filter_date_debut' => $filter_date_debut,
                        'filter_date_fin' => $filter_date_fin
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

    <!-- Modal pour afficher les détails d'une demande -->
    <div class="modal-overlay" id="details-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-file-alt"></i>
                    Détails de la Demande
                </h3>
                <button class="modal-close" onclick="closeModal('details-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="modal-info">
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-user"></i> Collaborateur
                        </span>
                        <span class="modal-info-value" id="modal-user"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-envelope"></i> Email
                        </span>
                        <span class="modal-info-value" id="modal-email"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-calendar-day"></i> Date de début
                        </span>
                        <span class="modal-info-value" id="modal-date-debut"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-calendar-minus"></i> Date de fin
                        </span>
                        <span class="modal-info-value" id="modal-date-fin"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-clock"></i> Durée
                        </span>
                        <span class="modal-info-value" id="modal-duree"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-tag"></i> Type de congé
                        </span>
                        <span class="modal-info-value" id="modal-type"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-tag"></i> Statut
                        </span>
                        <span class="modal-info-value" id="modal-statut"></span>
                    </div>
                    <div class="modal-info-item">
                        <span class="modal-info-label">
                            <i class="fas fa-calendar"></i> Date de demande
                        </span>
                        <span class="modal-info-value" id="modal-date-demande"></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-comment"></i> Motif du congé
                    </label>
                    <div style="padding: 1rem; background: var(--ivoire); border-radius: 8px; border: 1px solid var(--doré-clair);">
                        <span id="modal-motif"></span>
                    </div>
                </div>
                
                <div class="form-group" id="modal-commentaires-container">
                    <label class="form-label">
                        <i class="fas fa-sticky-note"></i> Commentaires du collaborateur
                    </label>
                    <div style="padding: 1rem; background: var(--ivoire); border-radius: 8px; border: 1px solid var(--doré-clair);">
                        <span id="modal-commentaires"></span>
                    </div>
                </div>
                
                <div class="form-group" id="modal-commentaire-validation-container">
                    <label class="form-label">
                        <i class="fas fa-comment-dots"></i> Commentaire de validation
                    </label>
                    <div style="padding: 1rem; background: var(--ivoire); border-radius: 8px; border: 1px solid var(--doré-clair);">
                        <span id="modal-commentaire-validation"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour valider/refuser une demande -->
    <div class="modal-overlay" id="validation-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-check-circle"></i>
                    <span id="validation-title">Valider la demande</span>
                </h3>
                <button class="modal-close" onclick="closeModal('validation-modal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form method="POST" action="" id="validation-form">
                    <input type="hidden" name="demande_id" id="validation-demande-id">
                    <input type="hidden" name="action" id="validation-action">
                    
                    <div class="form-group">
                        <label for="commentaire_validation" class="form-label">
                            <i class="fas fa-comment"></i> Commentaire (optionnel)
                        </label>
                        <textarea name="commentaire_validation" id="commentaire_validation" class="form-control" 
                                  placeholder="Ajoutez un commentaire pour le collaborateur..."></textarea>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('validation-modal')">
                            <i class="fas fa-times"></i> Annuler
                        </button>
                        <button type="submit" class="btn" id="validation-submit-btn">
                            <i class="fas fa-check"></i>
                            <span id="validation-submit-text">Valider</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour afficher les détails d'une demande
        function showDemandeDetails(demande) {
            const typeLabels = {
                'conges_payes': 'Congés Payés',
                'conges_sans_solde': 'Congés Sans Solde',
                'maladie': 'Arrêt Maladie',
                'familial': 'Congé Familial',
                'maternite': 'Congé Maternité',
                'paternite': 'Congé Paternité',
                'formation': 'Congé Formation',
                'autre': 'Autre'
            };
            
            const statutLabels = {
                'en_attente': 'En Attente',
                'approuve': 'Approuvé',
                'refuse': 'Refusé'
            };
            
            // Calcul de la durée
            const dateDebut = new Date(demande.date_debut);
            const dateFin = new Date(demande.date_fin);
            const diffTime = dateFin.getTime() - dateDebut.getTime();
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            // Remplir les champs du modal
            document.getElementById('modal-user').textContent = demande.user_prenom + ' ' + demande.user_nom;
            document.getElementById('modal-email').textContent = demande.user_email;
            document.getElementById('modal-date-debut').textContent = new Date(demande.date_debut).toLocaleDateString('fr-FR');
            document.getElementById('modal-date-fin').textContent = new Date(demande.date_fin).toLocaleDateString('fr-FR');
            document.getElementById('modal-duree').textContent = diffDays + ' jour(s)';
            document.getElementById('modal-type').textContent = typeLabels[demande.type_conge] || demande.type_conge;
            document.getElementById('modal-statut').textContent = statutLabels[demande.statut];
            document.getElementById('modal-date-demande').textContent = new Date(demande.date_demande).toLocaleDateString('fr-FR') + ' à ' + new Date(demande.date_demande).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
            document.getElementById('modal-motif').textContent = demande.motif;
            
            // Gérer les commentaires
            const commentairesContainer = document.getElementById('modal-commentaires-container');
            const commentairesContent = document.getElementById('modal-commentaires');
            if (demande.commentaires && demande.commentaires.trim() !== '') {
                commentairesContainer.style.display = 'block';
                commentairesContent.textContent = demande.commentaires;
            } else {
                commentairesContainer.style.display = 'none';
            }
            
            // Gérer le commentaire de validation
            const validationContainer = document.getElementById('modal-commentaire-validation-container');
            const validationContent = document.getElementById('modal-commentaire-validation');
            if (demande.commentaire_validation && demande.commentaire_validation.trim() !== '') {
                validationContainer.style.display = 'block';
                validationContent.textContent = demande.commentaire_validation;
            } else {
                validationContainer.style.display = 'none';
            }
            
            // Afficher le modal
            document.getElementById('details-modal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Fonction pour afficher le modal de validation
        function showValidationModal(demandeId, action) {
            const modal = document.getElementById('validation-modal');
            const title = document.getElementById('validation-title');
            const submitBtn = document.getElementById('validation-submit-btn');
            const submitText = document.getElementById('validation-submit-text');
            
            document.getElementById('validation-demande-id').value = demandeId;
            document.getElementById('validation-action').value = action;
            document.getElementById('commentaire_validation').value = '';
            
            // Adapter l'interface selon l'action
            switch(action) {
                case 'valider':
                    title.textContent = 'Valider la demande';
                    submitText.textContent = 'Valider';
                    submitBtn.className = 'btn btn-success';
                    break;
                case 'refuser':
                    title.textContent = 'Refuser la demande';
                    submitText.textContent = 'Refuser';
                    submitBtn.className = 'btn btn-danger';
                    break;
                case 'annuler':
                    title.textContent = 'Réinitialiser le statut';
                    submitText.textContent = 'Réinitialiser';
                    submitBtn.className = 'btn btn-warning';
                    break;
            }
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Fonction générique pour fermer les modals
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Fermer les modals en cliquant à l'extérieur
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        });

        // Fermer les modals avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
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
                }, index * 50);
            });
        });
    </script>
</body>
</html>