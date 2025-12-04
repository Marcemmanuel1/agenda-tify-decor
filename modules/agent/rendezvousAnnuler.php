<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Vérifier si l'utilisateur est connecté et a le bon statut
redirectIfNotLoggedIn();

// Vérification supplémentaire du statut utilisateur
checkUserStatus();
if (!isAgent()) {
    header('Location: ../planificateur/');
    exit();
}

$db = getDB();
$message = '';

// Récupérer les rendez-vous annulés de l'agent
$stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.commune, c.telephone
                     FROM rendezvous r
                     JOIN clients c ON r.client_id = c.id
                     WHERE r.agent_id = ? AND r.statut_rdv = 'Annulé'
                     ORDER BY r.date_rdv DESC"); // Tri par date décroissante
$stmt->execute([$_SESSION['user_id']]);
$rendezvous_annules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si un ID spécifique est demandé, récupérer ce rendez-vous annulé
$current_rdv = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $rdv_id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.genre, c.commune, c.telephone
                         FROM rendezvous r
                         JOIN clients c ON r.client_id = c.id
                         WHERE r.id = ? AND r.agent_id = ? AND r.statut_rdv = 'Annulé'");
    $stmt->execute([$rdv_id, $_SESSION['user_id']]);
    $current_rdv = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_rdv) {
        $message = '<div class="alert error">Rendez-vous non trouvé ou non annulé.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous Annulés - Agenda Rendez-vous</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Mes Rendez-vous Annulés</h1>
            <div style="display: flex; gap: 1rem;">
                <a href="rendezvous.php" class="btn btn-secondary" style="text-decoration:none;">
                    <i class="fas fa-arrow-left"></i> Rendez-vous en attente
                </a>
                <a href="rendezvousEffectuer.php" class="btn btn-secondary" style="text-decoration:none;">
                    <i class="fas fa-check-circle"></i> Rendez-vous effectués
                </a>
            </div>
        </div>
        
        <?php echo $message; ?>
        
        <?php if ($current_rdv): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Détails du rendez-vous annulé</h2>
                <a href="rendezvous_annules.php" class="btn btn-secondary" style="text-decoration:none;">Retour à la liste</a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label>Client</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= htmlspecialchars($current_rdv['client_prenom'] . ' ' . $current_rdv['client_nom']) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Genre</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= $current_rdv['genre'] == 'M' ? 'Masculin' : ($current_rdv['genre'] == 'F' ? 'Féminin' : 'Autre') ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Téléphone</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= htmlspecialchars($current_rdv['telephone']) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Commune</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= htmlspecialchars($current_rdv['commune']) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Date prévue du rendez-vous</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= date('d/m/Y H:i', strtotime($current_rdv['date_rdv'])) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Date de contact</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= date('d/m/Y H:i', strtotime($current_rdv['date_contact'])) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Statut paiement</label>
                    <div class="form-control" style="background-color: #f9f9f9;">
                        <?= $current_rdv['statut_paiement'] ?>
                    </div>
                </div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label>Motif initial</label>
                    <div class="form-control" style="background-color: #f9f9f9; min-height: 80px;">
                        <?= htmlspecialchars($current_rdv['motif']) ?>
                    </div>
                </div>
                
                <?php if ($current_rdv['notes_agent']): ?>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Raison de l'annulation</label>
                    <div class="form-control" style="background-color: #f9f9f9; min-height: 80px;">
                        <?= htmlspecialchars($current_rdv['notes_agent']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Liste de mes rendez-vous annulés</h2>
                <span class="badge"><?= count($rendezvous_annules) ?> rendez-vous annulés</span>
            </div>
            
            <?php if (empty($rendezvous_annules)): ?>
                <div class="alert info">Vous n'avez annulé aucun rendez-vous.</div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Date prévue</th>
                        <th>Commune</th>
                        <th>Téléphone</th>
                        <th>Paiement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rendezvous_annules as $rdv): ?>
                    <tr>
                        <td><?= htmlspecialchars($rdv['client_prenom'] . ' ' . $rdv['client_nom']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($rdv['date_rdv'])) ?></td>
                        <td><?= htmlspecialchars($rdv['commune']) ?></td>
                        <td><?= htmlspecialchars($rdv['telephone']) ?></td>
                        <td>
                            <span class="badge <?= $rdv['statut_paiement'] == 'Payé' ? 'success' : 'warning' ?>">
                                <?= $rdv['statut_paiement'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="../../js/script.js"></script>
</body>
</html>