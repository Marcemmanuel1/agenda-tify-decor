<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le bon statut
redirectIfNotLoggedIn();
checkUserStatus();

if (!isPlanificateur() && !isSuperAdmin()) {
    header('Location: ../agent/');
    exit();
}

$db = getDB();

$message = '';

// Gérer la requête de suppression POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_rdv'])) {
    $rdv_id = filter_input(INPUT_POST, 'rdv_id', FILTER_VALIDATE_INT);
    $motif_suppression = filter_input(INPUT_POST, 'motif_suppression', FILTER_SANITIZE_STRING);

    if ($rdv_id && $motif_suppression) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom
                                   FROM rendezvous r
                                   JOIN clients c ON r.client_id = c.id
                                   WHERE r.id = ? AND r.planificateur_id = ?");
            $stmt->execute([$rdv_id, $_SESSION['user_id']]);
            $rdv_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rdv_details) {
                $stmt = $db->prepare("INSERT INTO logs_suppression (rdv_id, user_id, motif_suppression) VALUES (?, ?, ?)");
                $stmt->execute([$rdv_id, $_SESSION['user_id'], $motif_suppression]);

                logActivity(
                    $_SESSION['user_id'],
                    'suppression',
                    "Suppression du rendez-vous: " .
                    "Client: {$rdv_details['client_prenom']} {$rdv_details['client_nom']}, " .
                    "Date: " . date('d/m/Y H:i', strtotime($rdv_details['date_rdv'])) . ", " .
                    "Motif: {$rdv_details['motif']}, " .
                    "Motif suppression: $motif_suppression"
                );

                $stmt = $db->prepare("DELETE FROM rendezvous WHERE id = ?");
                $stmt->execute([$rdv_id]);

                $db->commit();
                $message = '<div class="alert success">Rendez-vous supprimé avec succès.</div>';
            } else {
                $message = '<div class="alert error">Rendez-vous non trouvé ou accès non autorisé.</div>';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $message = '<div class="alert error">Erreur lors de la suppression: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert error">Veuillez saisir un motif de suppression.</div>';
    }
}

// Gérer les requêtes AJAX GET pour le filtrage et la pagination
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    // Pagination
    $limit = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    // Filtres et recherche
    $filters = [
        'statut' => isset($_GET['statut']) ? $_GET['statut'] : '',
        'commune' => isset($_GET['commune']) ? $_GET['commune'] : '',
        'date_debut' => isset($_GET['date_debut']) ? $_GET['date_debut'] : '',
        'date_fin' => isset($_GET['date_fin']) ? $_GET['date_fin'] : '',
        'search' => isset($_GET['search']) ? $_GET['search'] : ''
    ];

    $sql = "SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.commune, c.telephone,
                   u.nom as agent_nom, u.prenom as agent_prenom
            FROM rendezvous r
            JOIN clients c ON r.client_id = c.id
            LEFT JOIN users u ON r.agent_id = u.id
            WHERE r.planificateur_id = ?";

    $sql_count = "SELECT COUNT(*) as total FROM rendezvous r JOIN clients c ON r.client_id = c.id WHERE r.planificateur_id = ?";

    $params = [$_SESSION['user_id']];
    $params_count = [$_SESSION['user_id']];

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

    // CORRECTION : LIMIT et OFFSET ne peuvent pas être des paramètres liés dans une requête préparée
    $sql .= " ORDER BY r.date_rdv DESC LIMIT " . $limit . " OFFSET " . $offset;

    try {
        $stmt_count = $db->prepare($sql_count);
        $stmt_count->execute($params_count);
        $total_records = $stmt_count->fetchColumn();

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rendezvous = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rendezvous as &$rdv) {
            $rdv['date_rdv_formatted'] = date('d/m/Y H:i', strtotime($rdv['date_rdv']));
            $rdv['agent_name'] = $rdv['agent_id'] ? $rdv['agent_prenom'] . ' ' . $rdv['agent_nom'] : 'Non assigné';
        }
        unset($rdv);

        $response = [
            'rendezvous' => $rendezvous,
            'total' => $total_records,
            'current_page' => $page,
            'total_pages' => ceil($total_records / $limit)
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
    }
    exit;
}

// Récupérer les communes distinctes pour le filtre (pour l'affichage initial de la page)
$communes_stmt = $db->prepare("SELECT DISTINCT commune FROM clients ORDER BY commune");
$communes_stmt->execute();
$communes = $communes_stmt->fetchAll(PDO::FETCH_COLUMN);
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
            --vert-clair: #d4edda;
            --vert-fonce: #28a745;
            --rouge-clair: #f8d7da;
            --rouge-fonce: #dc3545;
        }

        .main-content { margin-left: 250px; padding: 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--ivoire); }
        .page-title { color: var(--doré-foncé); margin: 0; }
        .card { background-color: var(--blanc); border-radius: 8px; box-shadow: 0 2px 10px var(--ombre); padding: 1.5rem; margin-bottom: 1.5rem; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.8rem; border-bottom: 1px solid var(--ivoire); }
        .card-title { color: var(--doré-foncé); margin: 0; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--gris-anthracite); }
        .form-control { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; transition: border-color 0.3s; }
        .form-control:focus { outline: none; border-color: var(--vert-sage); box-shadow: 0 0 0 2px rgba(138, 154, 91, 0.2); }
        .btn { padding: 0.8rem 1.5rem; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: 500; transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-primary { background-color: var(--vert-sage); color: var(--blanc); }
        .btn-primary:hover { background-color: #7a8a4b; }
        .btn-secondary { background-color: var(--doré-clair); color: var(--blanc); }
        .btn-secondary:hover { background-color: var(--doré-foncé); }
        .btn-danger { background-color: var(--rouge); color: white; }
        .btn-danger:hover { background-color: #bd2130; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .alert.success { background-color: var(--vert-clair); color: var(--vert-fonce); border: 1px solid #c3e6cb; }
        .alert.error { background-color: var(--rouge-clair); color: var(--rouge-fonce); border: 1px solid #f5c6cb; }
        .table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .table th, .table td { padding: 0.8rem; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background-color: var(--ivoire); color: var(--doré-foncé); font-weight: 600; }
        .table tr:hover { background-color: rgba(245, 241, 235, 0.5); }
        .badge { padding: 0.3rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .badge-warning { background-color: #fff3cd; color: #856404; }
        .badge-success { background-color: var(--vert-clair); color: var(--vert-fonce); }
        .badge-danger { background-color: var(--rouge-clair); color: var(--rouge-fonce); }
        .badge-info { background-color: #d1ecf1; color: #0c5460; }
        #deleteModal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background-color: white; width: 500px; margin: 100px auto; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .pagination { display: flex; justify-content: center; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 16px; text-decoration: none; border: 1px solid #ddd; color: var(--doré-foncé); margin: 0 4px; border-radius: 4px; }
        .pagination a:hover { background-color: var(--doré-clair); color: white; }
        .pagination .active { background-color: var(--doré-foncé); color: white; border: 1px solid var(--doré-foncé); }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 1rem; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .card { padding: 1rem; }
            .modal-content { width: 90%; margin: 20% auto; }
            .filter-form > div { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Liste des Rendez-vous</h1>
            <a href="add_rdv.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau rendez-vous</a>
        </div>

        <?php if (!empty($message)) echo $message; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Filtres de recherche</h2>
            </div>

            <form id="filter-form">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label for="statut">Statut</label>
                        <select id="statut" name="statut" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="En attente">En attente</option>
                            <option value="Effectué">Effectué</option>
                            <option value="Annulé">Annulé</option>
                            <option value="Modifié">Modifié</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="commune">Commune</label>
                        <select id="commune" name="commune" class="form-control">
                            <option value="">Toutes les communes</option>
                            <?php foreach ($communes as $commune): ?>
                                <option value="<?= htmlspecialchars($commune) ?>">
                                    <?= htmlspecialchars($commune) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_debut">Date début</label>
                        <input type="date" id="date_debut" name="date_debut" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="date_fin">Date fin</label>
                        <input type="date" id="date_fin" name="date_fin" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="search">Recherche</label>
                        <input type="text" id="search" name="search" class="form-control" placeholder="Nom, téléphone, motif...">
                    </div>
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <button type="button" id="reset-btn" class="btn btn-secondary">Réinitialiser</button>
                    <button type="button" id="export-btn" class="btn btn-secondary">Exporter</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Rendez-vous (<span id="total-rdv-count">0</span>)</h2>
            </div>

            <div id="rdv-table-container">
                <p style="text-align: center; padding: 2rem;">Chargement des données...</p>
            </div>
        </div>
    </div>

    <div id="deleteModal">
        <div class="modal-content">
            <h2>Supprimer le rendez-vous</h2>
            <p>Vous êtes sur le point de supprimer le rendez-vous avec <strong id="delete-client-name"></strong>.</p>
            <p>Cette action est irréversible. Veuillez indiquer le motif de la suppression.</p>

            <form method="POST" id="deleteForm">
                <input type="hidden" name="rdv_id" id="delete-rdv-id">

                <div class="form-group">
                    <label for="motif_suppression">Motif de suppression *</label>
                    <textarea id="motif_suppression" name="motif_suppression" class="form-control" rows="4" required></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('deleteModal').style.display = 'none'">Annuler</button>
                    <button type="submit" name="delete_rdv" class="btn btn-danger">Confirmer la suppression</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rdvTableContainer = document.getElementById('rdv-table-container');
            const totalRdvCount = document.getElementById('total-rdv-count');
            const filterForm = document.getElementById('filter-form');
            const deleteModal = document.getElementById('deleteModal');
            const deleteForm = document.getElementById('deleteForm');

            function fetchRendezvous(page = 1) {
                const formData = new FormData(filterForm);
                const queryParams = new URLSearchParams(formData).toString();
                const url = `liste_rdv.php?page=${page}&${queryParams}`;

                rdvTableContainer.innerHTML = '<p style="text-align: center; padding: 2rem;">Chargement des données...</p>';

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        renderTable(data);
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        rdvTableContainer.innerHTML = `<div class="alert error">Erreur lors du chargement des données. Veuillez réessayer.</div>`;
                    });
            }

            function renderTable(data) {
                if (data.rendezvous.length === 0) {
                    rdvTableContainer.innerHTML = `<div style="text-align: center; padding: 2rem;">
                        <p>Aucun rendez-vous trouvé.</p>
                        <a href="add_rdv.php" class="btn btn-primary">Créer un premier rendez-vous</a>
                    </div>`;
                    totalRdvCount.textContent = '0';
                    return;
                }

                totalRdvCount.textContent = data.total;

                let tableHtml = `
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Téléphone</th>
                                    <th>Commune</th>
                                    <th>Date RDV</th>
                                    <th>Agent</th>
                                    <th>Statut</th>
                                    <th>Paiement</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>`;

                data.rendezvous.forEach(rdv => {
                    const statutClass = {
                        'En attente': 'badge-warning',
                        'Effectué': 'badge-success',
                        'Annulé': 'badge-danger',
                        'Modifié': 'badge-info'
                    }[rdv.statut_rdv];
                    const paiementClass = rdv.statut_paiement === 'Payé' ? 'badge-success' : 'badge-danger';

                    tableHtml += `
                        <tr>
                            <td>${rdv.client_prenom} ${rdv.client_nom}</td>
                            <td>${rdv.telephone}</td>
                            <td>${rdv.commune}</td>
                            <td>${rdv.date_rdv_formatted}</td>
                            <td>${rdv.agent_name}</td>
                            <td><span class="badge ${statutClass}">${rdv.statut_rdv}</span></td>
                            <td><span class="badge ${paiementClass}">${rdv.statut_paiement}</span></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="add_rdv.php?edit=${rdv.id}" class="btn btn-primary" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-danger delete-rdv" data-id="${rdv.id}" data-client="${rdv.client_prenom} ${rdv.client_nom}" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                });

                tableHtml += `</tbody></table></div>`;

                const totalPages = data.total_pages;
                const currentPage = data.current_page;

                if (totalPages > 1) {
                    tableHtml += `<div class="pagination">`;
                    if (currentPage > 1) {
                        tableHtml += `<a href="#" data-page="${currentPage - 1}">&laquo; Précédent</a>`;
                    }
                    for (let i = 1; i <= totalPages; i++) {
                        tableHtml += `<a href="#" class="${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</a>`;
                    }
                    if (currentPage < totalPages) {
                        tableHtml += `<a href="#" data-page="${currentPage + 1}">Suivant &raquo;</a>`;
                    }
                    tableHtml += `</div>`;
                }

                rdvTableContainer.innerHTML = tableHtml;

                attachEventListeners();
            }

            function attachEventListeners() {
                document.querySelectorAll('.pagination a').forEach(link => {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        const page = e.target.getAttribute('data-page');
                        fetchRendezvous(page);
                    });
                });

                document.querySelectorAll('.delete-rdv').forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const clientName = this.getAttribute('data-client');

                        document.getElementById('delete-rdv-id').value = id;
                        document.getElementById('delete-client-name').textContent = clientName;
                        deleteModal.style.display = 'block';
                    });
                });
            }

            fetchRendezvous();

            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                fetchRendezvous();
            });

            document.getElementById('reset-btn').addEventListener('click', () => {
                filterForm.reset();
                fetchRendezvous();
            });

            document.getElementById('export-btn').addEventListener('click', function() {
                const formData = new FormData(filterForm);
                const queryParams = new URLSearchParams(formData).toString();
                window.open(`export_rdv.php?${queryParams}`, '_blank');
            });

            deleteModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>