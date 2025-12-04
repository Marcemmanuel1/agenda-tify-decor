<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et a le bon statut
redirectIfNotLoggedIn();

// Vérification supplémentaire du statut utilisateur
checkUserStatus();
if (!isSuperAdmin() && !isPlanificateur()) {
    header('Location: ../agent/');
    exit();
}

$db = getDB();

// Récupérer tous les chantiers avec leurs détails
$query = "SELECT c.*, r.date_rdv, cl.nom as client_nom, cl.prenom as client_prenom, 
          cl.commune, cl.telephone, u.nom as agent_nom, u.prenom as agent_prenom
          FROM chantiers c
          JOIN rendezvous r ON c.rdv_id = r.id
          JOIN clients cl ON r.client_id = cl.id
          JOIN users u ON r.agent_id = u.id
          WHERE c.statut_travaux IS NOT NULL
          ORDER BY c.date_debut_travaux DESC, c.date_entretien DESC";

$stmt = $db->query($query);
$chantiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [
    'total' => count($chantiers),
    'en_attente' => 0,
    'en_cours' => 0,
    'termines' => 0,
    'a_temps' => 0,
    'en_retard' => 0,
    'en_avance' => 0
];

foreach ($chantiers as $chantier) {
    if ($chantier['statut_travaux'] == 'en_attente') $stats['en_attente']++;
    if ($chantier['statut_travaux'] == 'en_cours') $stats['en_cours']++;
    if ($chantier['statut_travaux'] == 'termine') $stats['termines']++;
    
    if ($chantier['livraison'] == 'a_temps') $stats['a_temps']++;
    if ($chantier['livraison'] == 'en_retard') $stats['en_retard']++;
    if ($chantier['livraison'] == 'en_avance') $stats['en_avance']++;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des Chantiers - Agenda Rendez-vous</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--doré-foncé);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--gris-anthracite);
            font-size: 0.9rem;
        }
        
        .progress-container {
            height: 10px;
            background-color: #eee;
            border-radius: 5px;
            margin: 1rem 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background-color: var(--doré-clair);
            border-radius: 5px;
            transition: width 0.3s;
        }
        
        .filter-bar {
            background-color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        
        .progress-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .progress-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #ddd;
        }
        
        .progress-dot.active {
            background-color: var(--doré-foncé);
        }
        
        .progress-dot.completed {
            background-color: var(--vert-sage);
        }
        
        .retard-alert {
            color: #dc3545;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .jours-restants {
            font-weight: bold;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        
        .jours-negatifs {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .jours-positifs {
            background-color: #d4edda;
            color: #155724;
        }
        
        .jours-critiques {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Suivi des Chantiers</h1>
            <div>
                <button onclick="exporterDonnees()" class="btn btn-secondary">
                    <i class="fas fa-file-export"></i> Exporter
                </button>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total des chantiers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?= $stats['en_cours'] ?></div>
                <div class="stat-label">Chantiers en cours</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?= $stats['termines'] ?></div>
                <div class="stat-label">Chantiers terminés</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?= $stats['a_temps'] + $stats['en_avance'] ?> / <?= $stats['termines'] ?></div>
                <div class="stat-label">Livraisons à temps/avance</div>
                <?php if ($stats['termines'] > 0): ?>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= (($stats['a_temps'] + $stats['en_avance']) / $stats['termines']) * 100 ?>%"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="filter-bar">
            <select id="filterStatut" class="filter-select" onchange="filtrerTableau()">
                <option value="">Tous les statuts</option>
                <option value="en_attente">En attente</option>
                <option value="en_cours">En cours</option>
                <option value="termine">Terminé</option>
            </select>
            
            <select id="filterLivraison" class="filter-select" onchange="filtrerTableau()">
                <option value="">Toutes les livraisons</option>
                <option value="a_temps">À temps</option>
                <option value="en_avance">En avance</option>
                <option value="en_retard">En retard</option>
            </select>
            
            <input type="text" id="filterClient" class="form-control" placeholder="Rechercher un client..." onkeyup="filtrerTableau()" style="max-width: 200px;">
        </div>
        
        <!-- Tableau des chantiers -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Liste des chantiers</h2>
                <span class="badge"><?= $stats['total'] ?> chantiers</span>
            </div>
            
            <?php if (empty($chantiers)): ?>
                <div class="alert info">Aucun chantier enregistré.</div>
            <?php else: ?>
            <table class="table" id="tableChantiers">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Agent</th>
                        <th>Date début</th>
                        <th>Durée</th>
                        <th>Progression</th>
                        <th>Livraison</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chantiers as $chantier): 
                        // Calculer les jours restants pour les chantiers en cours
                        $jours_restants = null;
                        $classe_jours = '';
                        
                        if ($chantier['statut_travaux'] == 'en_cours' && $chantier['date_fin_estimee']) {
                            $now = time();
                            $fin_estimee = strtotime($chantier['date_fin_estimee']);
                            $jours_restants = round(($fin_estimee - $now) / (60 * 60 * 24));
                            
                            if ($jours_restants < 0) {
                                $classe_jours = 'jours-negatifs';
                            } elseif ($jours_restants <= 3) {
                                $classe_jours = 'jours-critiques';
                            } else {
                                $classe_jours = 'jours-positifs';
                            }
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($chantier['client_prenom'] . ' ' . $chantier['client_nom']) ?></strong><br>
                            <small><?= htmlspecialchars($chantier['telephone']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($chantier['agent_prenom'] . ' ' . $chantier['agent_nom']) ?></td>
                        <td>
                            <?php if ($chantier['date_entretien']): ?>
                                <?= date('d/m/Y', strtotime($chantier['date_entretien'])) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($chantier['duree_estimee']): ?>
                                <?= $chantier['duree_estimee'] ?> jours
                                <?php if ($jours_restants !== null): ?>
                                    <br>
                                    <span class="jours-restants <?= $classe_jours ?>">
                                        <?= $jours_restants >= 0 ? $jours_restants . ' jours restants' : abs($jours_restants) . ' jours de retard' ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="progress-indicator">
                                <div class="progress-dot <?= $chantier['date_entretien'] ? 'completed' : 'active' ?>"></div>
                                <div class="progress-dot <?= $chantier['date_devis_envoye'] ? 'completed' : ($chantier['date_entretien'] ? 'active' : '') ?>"></div>
                                <div class="progress-dot <?= $chantier['date_debut_travaux'] ? 'completed' : ($chantier['statut_devis'] == 'accepte' ? 'active' : '') ?>"></div>
                                <div class="progress-dot <?= $chantier['date_fin_reelle'] ? 'completed' : ($chantier['date_debut_travaux'] ? 'active' : '') ?>"></div>
                                <span style="margin-left: 0.5rem;">
                                    <?= $chantier['statut_travaux'] == 'en_attente' ? 'Entretien' : 
                                        ($chantier['statut_travaux'] == 'en_cours' ? 'Travaux' : 
                                        ($chantier['statut_travaux'] == 'termine' ? 'Terminé' : 'Non commencé')) ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if ($chantier['livraison'] == 'a_temps'): ?>
                                <span class="badge badge-success">À temps</span>
                            <?php elseif ($chantier['livraison'] == 'en_avance'): ?>
                                <span class="badge badge-success">En avance</span>
                            <?php elseif ($chantier['livraison'] == 'en_retard'): ?>
                                <span class="badge badge-danger">En retard</span>
                            <?php elseif ($chantier['statut_travaux'] == 'en_cours' && $jours_restants < 0): ?>
                                <span class="retard-alert">EN RETARD</span>
                            <?php else: ?>
                                <span class="badge">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function filtrerTableau() {
            const statut = document.getElementById('filterStatut').value.toLowerCase();
            const livraison = document.getElementById('filterLivraison').value.toLowerCase();
            const client = document.getElementById('filterClient').value.toLowerCase();
            const rows = document.querySelectorAll('#tableChantiers tbody tr');
            
            rows.forEach(row => {
                const textStatut = row.cells[4].textContent.toLowerCase();
                const textLivraison = row.cells[5].textContent.toLowerCase();
                const textClient = row.cells[0].textContent.toLowerCase();
                
                const matchStatut = !statut || textStatut.includes(statut);
                const matchLivraison = !livraison || textLivraison.includes(livraison);
                const matchClient = !client || textClient.includes(client);
                
                row.style.display = (matchStatut && matchLivraison && matchClient) ? '' : 'none';
            });
        }
        
        function exporterDonnees() {
            let csv = "Client,Agent,Date entretien,Date devis,Type devis,Statut devis,Date début travaux,Durée estimée,Date fin estimée,Date fin réelle,Statut travaux,Livraison\n";
            
            document.querySelectorAll('#tableChantiers tbody tr').forEach(row => {
                const cells = row.cells;
                const rowData = [
                    cells[0].textContent.replace(/,/g, ';'),
                    cells[1].textContent,
                    cells[2].textContent,
                    '', // Date devis
                    '', // Type devis
                    '', // Statut devis
                    '', // Date début
                    cells[3].textContent.split('\n')[0],
                    '', // Date fin estimée
                    '', // Date fin réelle
                    cells[4].textContent,
                    cells[5].textContent
                ];
                csv += rowData.join(',') + "\n";
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'chantiers_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
    
    <script src="../../js/script.js"></script>
</body>
</html>