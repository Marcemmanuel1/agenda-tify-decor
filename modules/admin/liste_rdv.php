<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

redirectIfNotLoggedIn();
if (!isSuperAdmin()) {
    header('Location: ../admin/');
    exit();
}

$db = getDB();
$message = '';

// Filtres et recherche
$filters = [
    'statut' => isset($_GET['statut']) ? $_GET['statut'] : '',
    'commune' => isset($_GET['commune']) ? $_GET['commune'] : '',
    'date_debut' => isset($_GET['date_debut']) ? $_GET['date_debut'] : '',
    'date_fin' => isset($_GET['date_fin']) ? $_GET['date_fin'] : '',
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'planificateur' => isset($_GET['planificateur']) ? $_GET['planificateur'] : '',
    'agent' => isset($_GET['agent']) ? $_GET['agent'] : '',
    'statut_chantier' => isset($_GET['statut_chantier']) ? $_GET['statut_chantier'] : '',
    'livraison' => isset($_GET['livraison']) ? $_GET['livraison'] : ''
];

// Paramètres de pagination
$items_par_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_par_page;

// Construction de la requête avec filtres
$sql = "SELECT r.*, 
               c.nom as client_nom, c.prenom as client_prenom, c.commune, c.telephone, c.canal, c.genre,
               up.nom as planificateur_nom, up.prenom as planificateur_prenom,
               ua.nom as agent_nom, ua.prenom as agent_prenom,
               ch.id as chantier_id, ch.date_entretien, ch.date_devis_envoye, ch.type_devis, 
               ch.statut_devis, ch.date_debut_travaux, ch.duree_estimee, ch.date_fin_estimee,
               ch.date_fin_reelle, ch.statut_travaux, ch.livraison, ch.notes as notes_chantier
        FROM rendezvous r
        JOIN clients c ON r.client_id = c.id
        JOIN users up ON r.planificateur_id = up.id
        LEFT JOIN users ua ON r.agent_id = ua.id
        LEFT JOIN chantiers ch ON r.id = ch.rdv_id
        WHERE 1=1";

$sql_count = "SELECT COUNT(*) as total
              FROM rendezvous r
              JOIN clients c ON r.client_id = c.id
              JOIN users up ON r.planificateur_id = up.id
              LEFT JOIN users ua ON r.agent_id = ua.id
              LEFT JOIN chantiers ch ON r.id = ch.rdv_id
              WHERE 1=1";

$params = [];
$params_count = [];

// Ajout des filtres aux deux requêtes
if (!empty($filters['statut'])) {
    $sql .= " AND r.statut_rdv = ?";
    $sql_count .= " AND r.statut_rdv = ?";
    $params[] = $filters['statut'];
    $params_count[] = $filters['statut'];
}

if (!empty($filters['commune'])) {
    $sql .= " AND c.commune LIKE ?";
    $sql_count .= " AND c.commune LIKE ?";
    $params[] = '%' . $filters['commune'] . '%';
    $params_count[] = '%' . $filters['commune'] . '%';
}

if (!empty($filters['date_debut'])) {
    $sql .= " AND r.date_rdv >= ?";
    $sql_count .= " AND r.date_rdv >= ?";
    $params[] = date('Y-m-d', strtotime($filters['date_debut'])) . ' 00:00:00';
    $params_count[] = date('Y-m-d', strtotime($filters['date_debut'])) . ' 00:00:00';
}

if (!empty($filters['date_fin'])) {
    $sql .= " AND r.date_rdv <= ?";
    $sql_count .= " AND r.date_rdv <= ?";
    $params[] = date('Y-m-d', strtotime($filters['date_fin'])) . ' 23:59:59';
    $params_count[] = date('Y-m-d', strtotime($filters['date_fin'])) . ' 23:59:59';
}

if (!empty($filters['search'])) {
    $sql .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ? OR r.motif LIKE ?)";
    $sql_count .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ? OR r.motif LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params_count[] = $search_term;
    $params_count[] = $search_term;
    $params_count[] = $search_term;
    $params_count[] = $search_term;
}

if (!empty($filters['planificateur'])) {
    $sql .= " AND r.planificateur_id = ?";
    $sql_count .= " AND r.planificateur_id = ?";
    $params[] = $filters['planificateur'];
    $params_count[] = $filters['planificateur'];
}

if (!empty($filters['agent'])) {
    $sql .= " AND r.agent_id = ?";
    $sql_count .= " AND r.agent_id = ?";
    $params[] = $filters['agent'];
    $params_count[] = $filters['agent'];
}

if (!empty($filters['statut_chantier'])) {
    if ($filters['statut_chantier'] === 'sans_chantier') {
        $sql .= " AND ch.id IS NULL";
        $sql_count .= " AND ch.id IS NULL";
    } else {
        $sql .= " AND ch.statut_travaux = ?";
        $sql_count .= " AND ch.statut_travaux = ?";
        $params[] = $filters['statut_chantier'];
        $params_count[] = $filters['statut_chantier'];
    }
}

if (!empty($filters['livraison'])) {
    $sql .= " AND ch.livraison = ?";
    $sql_count .= " AND ch.livraison = ?";
    $params[] = $filters['livraison'];
    $params_count[] = $filters['livraison'];
}

// CORRECTION : Ajout du tri et de la pagination SANS paramètres préparés
$sql .= " ORDER BY r.date_rdv DESC LIMIT " . intval($items_par_page) . " OFFSET " . intval($offset);

// Exécution de la requête pour le comptage
$stmt_count = $db->prepare($sql_count);
$stmt_count->execute($params_count);
$total_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
$total_items = $total_result['total'];
$total_pages = ceil($total_items / $items_par_page);

// Exécution de la requête pour les données
$stmt = $db->prepare($sql);
$stmt->execute($params); // CORRECTION : On exécute seulement avec les params, pas avec LIMIT/OFFSET
$rendezvous = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Suppression d'un rendez-vous
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_rdv'])) {
        $rdv_id = filter_input(INPUT_POST, 'rdv_id', FILTER_VALIDATE_INT);
        $motif_suppression = filter_input(INPUT_POST, 'motif_suppression', FILTER_SANITIZE_STRING);
        
        if ($rdv_id && $motif_suppression) {
            try {
                $db->beginTransaction();
                
                // Suppression du chantier si existe
                $stmt = $db->prepare("DELETE FROM chantiers WHERE rdv_id = ?");
                $stmt->execute([$rdv_id]);
                
                // Suppression du rendez-vous
                $stmt = $db->prepare("DELETE FROM rendezvous WHERE id = ?");
                $stmt->execute([$rdv_id]);
                
                $db->commit();
                $message = '<div class="alert success">Rendez-vous et chantier associé supprimés avec succès.</div>';
                
                // Recharger la liste
                header('Location: liste_rdv.php?success=1');
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                $message = '<div class="alert error">Erreur lors de la suppression: ' . $e->getMessage() . '</div>';
            }
        } else {
            $message = '<div class="alert error">Veuillez saisir un motif de suppression.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Rendez-vous - Administration</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--doré-foncé);
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            color: var(--gris-anthracite);
            font-size: 0.85rem;
        }
        
        .chantier-badge {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-top: 0.2rem;
        }
        
        .chantier-en-attente { background-color: #d1ecf1; color: #0c5460; }
        .chantier-en-cours { background-color: #fff3cd; color: #856404; }
        .chantier-termine { background-color: #d4edda; color: #155724; }
        
        .livraison-badge {
            display: inline-block;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        
        .livraison-a-temps { background-color: #d4edda; color: #155724; }
        .livraison-en-avance { background-color: #c3e6cb; color: #0c5460; }
        .livraison-en-retard { background-color: #f8d7da; color: #721c24; }
        
        .progress-indicator {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.8rem;
        }
        
        .progress-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #ddd;
        }
        
        .progress-dot.active {
            background-color: var(--doré-foncé);
        }
        
        .progress-dot.completed {
            background-color: var(--vert-sage);
        }
        
        .btn-view {
            padding: 0.3rem 0.8rem;
            background-color: var(--doré-foncé);
            color: white; 
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-view:hover {
            background-color: var(--doré-clair);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding: 1rem 0;
        }
        
        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            background-color: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background-color: var(--doré-foncé);
            color: white;
            border-color: var(--doré-foncé);
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-current {
            padding: 0.5rem 1rem;
            background-color: var(--doré-foncé);
            color: white;
            border-radius: 4px;
        }
        
        .pagination-info {
            margin-right: 1rem;
            color: var(--gris-anthracite);
        }
        
        .page-input {
            width: 60px;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            width: 90%;
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            max-height: 85vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 1.5rem;
            color: var(--doré-foncé);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-section h3 {
            color: var(--doré-foncé);
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--ivoire);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--gris-anthracite);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .info-value {
            padding: 8px;
            background-color: #f9f9f9;
            border-radius: 4px;
            min-height: 38px;
            display: flex;
            align-items: center;
        }
        
        .timeline-modal {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            position: relative;
        }
        
        .timeline-modal::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #ddd;
            z-index: 1;
        }
        
        .etape-modal {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .cercle-modal {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #ddd;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .cercle-modal.active {
            background-color: var(--doré-foncé);
        }
        
        .cercle-modal.completed {
            background-color: var(--vert-sage);
        }
        
        .etape-label-modal {
            font-size: 0.9rem;
            color: var(--gris-anthracite);
        }
        .status-cell {
            display: flex;
            gap: 5px;
        }
        
        .etape-date-modal {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Liste des Rendez-vous - Administration</h1>
        </div>
        
        <?php echo $message; ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Filtres de recherche</h2>
            </div>
            
            <form method="GET" class="filter-form">
                <input type="hidden" name="page" value="1">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="statut">Statut RDV</label>
                        <select id="statut" name="statut" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="En attente" <?= $filters['statut'] == 'En attente' ? 'selected' : '' ?>>En attente</option>
                            <option value="Effectué" <?= $filters['statut'] == 'Effectué' ? 'selected' : '' ?>>Effectué</option>
                            <option value="Annulé" <?= $filters['statut'] == 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                            <option value="Modifié" <?= $filters['statut'] == 'Modifié' ? 'selected' : '' ?>>Modifié</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="statut_chantier">Statut chantier</label>
                        <select id="statut_chantier" name="statut_chantier" class="form-control">
                            <option value="">Tous les chantiers</option>
                            <option value="sans_chantier" <?= $filters['statut_chantier'] == 'sans_chantier' ? 'selected' : '' ?>>Sans chantier</option>
                            <option value="en_attente" <?= $filters['statut_chantier'] == 'en_attente' ? 'selected' : '' ?>>En attente</option>
                            <option value="en_cours" <?= $filters['statut_chantier'] == 'en_cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="termine" <?= $filters['statut_chantier'] == 'termine' ? 'selected' : '' ?>>Terminé</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="livraison">Livraison</label>
                        <select id="livraison" name="livraison" class="form-control">
                            <option value="">Toutes</option>
                            <option value="a_temps" <?= $filters['livraison'] == 'a_temps' ? 'selected' : '' ?>>À temps</option>
                            <option value="en_avance" <?= $filters['livraison'] == 'en_avance' ? 'selected' : '' ?>>En avance</option>
                            <option value="en_retard" <?= $filters['livraison'] == 'en_retard' ? 'selected' : '' ?>>En retard</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="search">Recherche</label>
                        <input type="text" id="search" name="search" class="form-control" placeholder="Nom, téléphone, motif..." value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                </div>
                
                <div style="margin-top: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="liste_rdv.php" class="btn btn-secondary" style="text-decoration:none;">Réinitialiser</a>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Rendez-vous</h2>
                <div>
                    <span class="badge"><?= $total_items ?> résultats</span>
                    <span class="badge">Page <?= $page ?>/<?= $total_pages ?></span>
                </div>
            </div>
            
            <?php if (count($rendezvous) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Date RDV</th>
                            <th>Statut RDV</th>
                            <th>Chantier</th>
                            <th>Progression</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rendezvous as $rdv): 
                            $statut_class = [
                                'En attente' => 'badge-warning',
                                'Effectué' => 'badge-success',
                                'Annulé' => 'badge-danger',
                                'Modifié' => 'badge-info'
                            ][$rdv['statut_rdv']];
                            
                            $paiement_class = $rdv['statut_paiement'] == 'Payé' ? 'badge-success' : 'badge-danger';
                            
                            // Déterminer la classe du badge chantier
                            $chantier_badge = '';
                            if ($rdv['chantier_id']) {
                                switch ($rdv['statut_travaux']) {
                                    case 'en_attente':
                                        $chantier_badge = '<span class="chantier-badge chantier-en-attente">En attente</span>';
                                        break;
                                    case 'en_cours':
                                        $chantier_badge = '<span class="chantier-badge chantier-en-cours">En cours</span>';
                                        break;
                                    case 'termine':
                                        $chantier_badge = '<span class="chantier-badge chantier-termine">Terminé</span>';
                                        break;
                                }
                            }
                        ?>
                        <tr data-rdv-id="<?= $rdv['id'] ?>">
                            <td>
                                <strong><?= htmlspecialchars($rdv['client_prenom'] . ' ' . $rdv['client_nom']) ?></strong><br>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($rdv['date_rdv'])) ?></td>                            
                            <td class="status-cell">
                                <span class="badge <?= $statut_class ?>"><?= $rdv['statut_rdv'] ?></span><br>
                                <span class="badge <?= $paiement_class ?>"><?= $rdv['statut_paiement'] ?></span>
                            </td>
                            <td>
                                <?php if ($rdv['chantier_id']): ?>
                                    <?= $chantier_badge ?>
                                    <?php if ($rdv['livraison']): ?>
                                        <?php if ($rdv['livraison'] == 'a_temps'): ?>
                                            <span class="livraison-badge livraison-a-temps">À temps</span>
                                        <?php elseif ($rdv['livraison'] == 'en_avance'): ?>
                                            <span class="livraison-badge livraison-en-avance">En avance</span>
                                        <?php elseif ($rdv['livraison'] == 'en_retard'): ?>
                                            <span class="livraison-badge livraison-en-retard">En retard</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge">Sans chantier</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($rdv['chantier_id']): ?>
                                <div class="progress-indicator">
                                    <div class="progress-dot <?= $rdv['date_entretien'] ? 'completed' : ($rdv['chantier_id'] ? 'active' : '') ?>"></div>
                                    <div class="progress-dot <?= $rdv['date_devis_envoye'] ? 'completed' : ($rdv['date_entretien'] && !$rdv['date_debut_travaux'] ? 'active' : '') ?>"></div>
                                    <div class="progress-dot <?= $rdv['date_debut_travaux'] ? 'completed' : ($rdv['statut_devis'] == 'accepte' ? 'active' : '') ?>"></div>
                                    <div class="progress-dot <?= $rdv['date_fin_reelle'] ? 'completed' : ($rdv['date_debut_travaux'] && !$rdv['date_fin_reelle'] ? 'active' : '') ?>"></div>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-view" onclick="openDetailsModal(<?= $rdv['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Affichage <?= (($page - 1) * $items_par_page) + 1 ?> - <?= min($page * $items_par_page, $total_items) ?> sur <?= $total_items ?>
                    </div>
                    
                    <button class="pagination-btn" 
                            onclick="goToPage(1)" 
                            <?= $page <= 1 ? 'disabled' : '' ?>>
                        <i class="fas fa-angle-double-left"></i>
                    </button>
                    
                    <button class="pagination-btn" 
                            onclick="goToPage(<?= $page - 1 ?>)" 
                            <?= $page <= 1 ? 'disabled' : '' ?>>
                        <i class="fas fa-angle-left"></i>
                    </button>
                    
                    <?php 
                    // Afficher les numéros de page
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<span>...</span>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="pagination-current"><?= $i ?></span>
                        <?php else: ?>
                            <button class="pagination-btn" onclick="goToPage(<?= $i ?>)"><?= $i ?></button>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <span>...</span>
                        <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)"><?= $total_pages ?></button>
                    <?php endif; ?>
                    
                    <button class="pagination-btn" 
                            onclick="goToPage(<?= $page + 1 ?>)" 
                            <?= $page >= $total_pages ? 'disabled' : '' ?>>
                        <i class="fas fa-angle-right"></i>
                    </button>
                    
                    <button class="pagination-btn" 
                            onclick="goToPage(<?= $total_pages ?>)" 
                            <?= $page >= $total_pages ? 'disabled' : '' ?>>
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 2rem;">
                <p>Aucun rendez-vous trouvé.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de détails -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Détails du Rendez-vous</h2>
                <button class="close-modal" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
        </div>
    </div>
    
    <script src="../../js/script.js"></script>
    <script>
        // Données pour le modal
        const rdvDetails = <?= json_encode($rendezvous) ?>;
        
        // Fonctions de pagination
        function goToPage(pageNumber) {
            const url = new URL(window.location);
            url.searchParams.set('page', pageNumber);
            window.location.href = url.toString();
        }
        
        // Fonctions pour le modal de détails
        function openDetailsModal(rdvId) {
            const rdv = rdvDetails.find(r => r.id == rdvId);
            if (!rdv) return;
            
            const modalContent = document.getElementById('modalContent');
            
            // Générer le contenu HTML
            let html = `
                <div class="info-section">
                    <h3><i class="fas fa-user"></i> Informations Client</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Nom complet</div>
                            <div class="info-value">${escapeHtml(rdv.client_prenom)} ${escapeHtml(rdv.client_nom)}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Genre</div>
                            <div class="info-value">${rdv.genre == 'M' ? 'Masculin' : (rdv.genre == 'F' ? 'Féminin' : 'Non spécifié')}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Téléphone</div>
                            <div class="info-value">${escapeHtml(rdv.telephone)}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Commune</div>
                            <div class="info-value">${escapeHtml(rdv.commune)}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Canal</div>
                            <div class="info-value">${rdv.canal ? escapeHtml(rdv.canal) : 'Non renseigné'}</div>
                        </div>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-calendar-alt"></i> Informations Rendez-vous</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Date du rendez-vous</div>
                            <div class="info-value">${formatDate(rdv.date_rdv)}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date de contact</div>
                            <div class="info-value">${formatDate(rdv.date_contact)}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Planificateur</div>
                            <div class="info-value">${escapeHtml(rdv.planificateur_prenom)} ${escapeHtml(rdv.planificateur_nom)}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Agent</div>
                            <div class="info-value">${rdv.agent_id ? escapeHtml(rdv.agent_prenom) + ' ' + escapeHtml(rdv.agent_nom) : 'Non assigné'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Statut RDV</div>
                            <div class="info-value"><span class="badge ${getStatusClass(rdv.statut_rdv)}">${rdv.statut_rdv}</span></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Statut paiement</div>
                            <div class="info-value"><span class="badge ${rdv.statut_paiement == 'Payé' ? 'badge-success' : 'badge-danger'}">${rdv.statut_paiement}</span></div>
                        </div>
                    </div>
                </div>
                
                <div class="info-section">
                    <h3><i class="fas fa-file-alt"></i> Motif</h3>
                    <div class="info-value" style="min-height: 60px; white-space: pre-wrap;">${escapeHtml(rdv.motif)}</div>
                </div>`;
            
            // Ajouter les informations chantier si disponible
            if (rdv.chantier_id) {
                html += `
                    <div class="info-section">
                        <h3><i class="fas fa-tasks"></i> Suivi du Chantier</h3>
                        
                        <div class="timeline-modal">
                            <div class="etape-modal">
                                <div class="cercle-modal ${rdv.date_entretien ? 'completed' : (rdv.chantier_id ? 'active' : '')}">1</div>
                                <div class="etape-label-modal">Entretien</div>
                                ${rdv.date_entretien ? `<div class="etape-date-modal">${formatDate(rdv.date_entretien)}</div>` : ''}
                            </div>
                            
                            <div class="etape-modal">
                                <div class="cercle-modal ${rdv.date_devis_envoye ? 'completed' : (rdv.date_entretien && !rdv.date_debut_travaux ? 'active' : '')}">2</div>
                                <div class="etape-label-modal">Devis</div>
                                ${rdv.date_devis_envoye ? `<div class="etape-date-modal">${formatDate(rdv.date_devis_envoye)}</div>` : ''}
                            </div>
                            
                            <div class="etape-modal">
                                <div class="cercle-modal ${rdv.date_debut_travaux ? 'completed' : (rdv.statut_devis == 'accepte' ? 'active' : '')}">3</div>
                                <div class="etape-label-modal">Travaux</div>
                                ${rdv.date_debut_travaux ? `<div class="etape-date-modal">${formatDate(rdv.date_debut_travaux)}</div>` : ''}
                            </div>
                            
                            <div class="etape-modal">
                                <div class="cercle-modal ${rdv.date_fin_reelle ? 'completed' : (rdv.date_debut_travaux && !rdv.date_fin_reelle ? 'active' : '')}">4</div>
                                <div class="etape-label-modal">Livraison</div>
                                ${rdv.date_fin_reelle ? `<div class="etape-date-modal">${formatDate(rdv.date_fin_reelle)}</div>` : ''}
                            </div>
                        </div>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Statut chantier</div>
                                <div class="info-value">${getChantierStatus(rdv.statut_travaux)}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Type de devis</div>
                                <div class="info-value">${rdv.type_devis == 'avec_3d' ? 'Avec 3D' : (rdv.type_devis == 'sans_3d' ? 'Sans 3D' : 'Non spécifié')}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Statut devis</div>
                                <div class="info-value">${getDevisStatus(rdv.statut_devis)}</div>
                            </div>
                            ${rdv.duree_estimee ? `
                            <div class="info-item">
                                <div class="info-label">Durée estimée</div>
                                <div class="info-value">${rdv.duree_estimee} jours</div>
                            </div>` : ''}
                            ${rdv.date_fin_estimee ? `
                            <div class="info-item">
                                <div class="info-label">Date fin estimée</div>
                                <div class="info-value">${formatDate(rdv.date_fin_estimee)}</div>
                            </div>` : ''}
                            ${rdv.livraison ? `
                            <div class="info-item">
                                <div class="info-label">Livraison</div>
                                <div class="info-value">${getLivraisonStatus(rdv.livraison)}</div>
                            </div>` : ''}
                        </div>
                        
                        ${rdv.notes_chantier ? `
                        <div class="info-section">
                            <h3><i class="fas fa-sticky-note"></i> Notes du chantier</h3>
                            <div class="info-value" style="min-height: 80px; white-space: pre-wrap;">${escapeHtml(rdv.notes_chantier)}</div>
                        </div>` : ''}
                    </div>`;
            }
            
            modalContent.innerHTML = html;
            document.getElementById('detailsModal').style.display = 'block';
        }
        
        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        // Fonctions utilitaires
        function formatDate(dateString) {
            if (!dateString) return 'Non définie';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function getStatusClass(status) {
            const classes = {
                'En attente': 'badge-warning',
                'Effectué': 'badge-success',
                'Annulé': 'badge-danger',
                'Modifié': 'badge-info'
            };
            return classes[status] || '';
        }
        
        function getChantierStatus(status) {
            const classes = {
                'en_attente': 'chantier-badge chantier-en-attente',
                'en_cours': 'chantier-badge chantier-en-cours',
                'termine': 'chantier-badge chantier-termine'
            };
            const labels = {
                'en_attente': 'En attente',
                'en_cours': 'En cours',
                'termine': 'Terminé'
            };
            return `<span class="${classes[status] || ''}">${labels[status] || status || 'Non commencé'}</span>`;
        }
        
        function getDevisStatus(status) {
            const labels = {
                'envoye': 'Envoyé',
                'accepte': 'Accepté',
                'refuse': 'Refusé'
            };
            return labels[status] || status || 'Non envoyé';
        }
        
        function getLivraisonStatus(status) {
            const classes = {
                'a_temps': 'livraison-badge livraison-a-temps',
                'en_avance': 'livraison-badge livraison-en-avance',
                'en_retard': 'livraison-badge livraison-en-retard'
            };
            const labels = {
                'a_temps': 'À temps',
                'en_avance': 'En avance',
                'en_retard': 'En retard'
            };
            return `<span class="${classes[status] || ''}">${labels[status] || status || ''}</span>`;
        }
        
        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailsModal();
            }
        });
    </script>
</body>
</html>