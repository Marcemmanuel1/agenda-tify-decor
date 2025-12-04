<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

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

// Traitement de la mise à jour des statuts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $rdv_id = filter_input(INPUT_POST, 'rdv_id', FILTER_VALIDATE_INT);
    $statut_rdv = filter_input(INPUT_POST, 'statut_rdv', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $statut_paiement = filter_input(INPUT_POST, 'statut_paiement', FILTER_SANITIZE_STRING);

    if ($rdv_id && $statut_rdv) {
        try {
            $db->beginTransaction();

            // Récupérer les anciennes valeurs avant mise à jour
            $stmt = $db->prepare("SELECT statut_rdv, statut_paiement FROM rendezvous WHERE id = ? AND agent_id = ?");
            $stmt->execute([$rdv_id, $_SESSION['user_id']]);
            $old_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_data) {
                throw new Exception("Rendez-vous non trouvé ou vous n'avez pas l'autorisation.");
            }

            // Déterminer le nouveau statut de paiement
            $new_statut_paiement = $statut_paiement;
            // Si le RDV est marqué comme "Effectué", le paiement est automatiquement "Payé"
            if ($statut_rdv === 'Effectué') {
                $new_statut_paiement = 'Payé';
            }

            $stmt = $db->prepare("UPDATE rendezvous SET statut_rdv = ?, notes_agent = ?, statut_paiement = ? WHERE id = ?");
            if ($stmt->execute([$statut_rdv, $notes, $new_statut_paiement, $rdv_id])) {
                // Enregistrer dans l'historique
                logActivity(
                    $_SESSION['user_id'],
                    'modification',
                    "Mise à jour du statut du rendez-vous #$rdv_id: " .
                    "Ancien statut RDV: {$old_data['statut_rdv']}, Nouveau: $statut_rdv. " .
                    "Ancien statut paiement: {$old_data['statut_paiement']}, Nouveau: $new_statut_paiement. " .
                    "Notes: " . ($notes ? $notes : 'Aucune note')
                );

                $db->commit();
                
                // Redirection après succès
                if ($statut_rdv === 'Effectué') {
                    header('Location: rendezvousEffectuer.php?success=1');
                } else if ($statut_rdv === 'Annulé') {
                    header('Location: rendezvousAnnuler.php?success=1');
                } else {
                    header('Location: rendezvous.php?id=' . $rdv_id . '&success=1');
                }
                exit();
            } else {
                throw new Exception("Erreur lors de la mise à jour du statut.");
            }
        } catch (Exception $e) {
            $db->rollBack();
            $message = '<div class="alert error">Erreur lors de la mise à jour: ' . $e->getMessage() . '</div>';
        }
    }
}

// Récupérer seulement les rendez-vous en attente de l'agent
$stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.commune, c.telephone
                     FROM rendezvous r
                     JOIN clients c ON r.client_id = c.id
                     WHERE r.agent_id = ? AND r.statut_rdv = 'En attente'
                     ORDER BY r.date_rdv ASC"); // Tri par date croissante pour voir les prochains RDV en premier
$stmt->execute([$_SESSION['user_id']]);
$rendezvous = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si un ID spécifique est demandé, récupérer ce rendez-vous (même s'il n'est plus en attente)
$current_rdv = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $rdv_id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.genre, c.commune, c.telephone
                         FROM rendezvous r
                         JOIN clients c ON r.client_id = c.id
                         WHERE r.id = ? AND r.agent_id = ?");
    $stmt->execute([$rdv_id, $_SESSION['user_id']]);
    $current_rdv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_rdv) {
        $message = '<div class="alert error">Rendez-vous non trouvé ou vous n\'avez pas l\'autorisation.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous - Agenda Rendez-vous</title>
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
            color: var(--doré-foncé);
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gris-anthracite);
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
            background-color: #f9f9f9;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--vert-sage);
            box-shadow: 0 0 0 2px rgba(138, 154, 91, 0.2);
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
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

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.success {
            background-color: var(--vert-clair);
            color: var(--vert-fonce);
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: var(--rouge-clair);
            color: var(--rouge-fonce);
            border: 1px solid #f5c6cb;
        }

        .alert.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
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

        .badge-success {
            background-color: var(--vert-clair);
            color: var(--vert-fonce);
        }

        .badge-danger {
            background-color: var(--rouge-clair);
            color: var(--rouge-fonce);
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
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

            .card {
                padding: 1rem;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        /* Styles pour la notification (sans affecter la nav) */
        .notification-badge {
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: 5px;
            position: relative;
            top: -5px;
        }

        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
        }

        .modal-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 15px;
            max-height: 250px;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 15px;
            border-top: 1px solid #eee;
            text-align: right;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: #333;
            transition: background-color 0.2s ease;
        }

        .notification-item:hover {
            background-color: #f0f0f0;
        }

        .notification-item.unread {
            font-weight: bold;
            background-color: #e8f5e9;
        }

        .notification-content {
            flex: 1;
        }

        .notification-content p {
            margin: 0 0 5px 0;
        }

        .notification-content small {
            color: #6c757d;
        }

        .mark-read-btn {
            background: none;
            border: none;
            color: #28a745;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
            margin-left: 10px;
        }

        .no-notifications {
            text-align: center;
            color: #6c757d;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <?php include '../../includes/header.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Mes Rendez-vous</h1>
        </div>
        
        <?php echo $message; ?>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> Le statut du rendez-vous a été mis à jour avec succès.
        </div>
        <?php endif; ?>
        
        <?php if ($current_rdv): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Détails du rendez-vous</h2>
                <a href="rendezvous.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour à la liste</a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label>Client</label>
                    <div class="form-control">
                        <?= htmlspecialchars($current_rdv['client_prenom'] . ' ' . $current_rdv['client_nom']) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Genre</label>
                    <div class="form-control">
                        <?= $current_rdv['genre'] == 'M' ? 'Masculin' : ($current_rdv['genre'] == 'F' ? 'Féminin' : 'Autre') ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Téléphone</label>
                    <div class="form-control">
                        <?= htmlspecialchars($current_rdv['telephone']) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Commune</label>
                    <div class="form-control">
                        <?= htmlspecialchars($current_rdv['commune']) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Date du rendez-vous</label>
                    <div class="form-control">
                        <?= date('d/m/Y H:i', strtotime($current_rdv['date_rdv'])) ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Statut</label>
                    <div class="form-control">
                        <span class="badge badge-<?= 
                            $current_rdv['statut_rdv'] == 'En attente' ? 'warning' : 
                            ($current_rdv['statut_rdv'] == 'Effectué' ? 'success' : 
                            ($current_rdv['statut_rdv'] == 'Annulé' ? 'danger' : 'info')) 
                        ?>">
                            <?= $current_rdv['statut_rdv'] ?>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Statut de paiement</label>
                    <div class="form-control">
                        <span class="badge badge-<?= $current_rdv['statut_paiement'] == 'Payé' ? 'success' : 'danger' ?>">
                            <?= $current_rdv['statut_paiement'] ?>
                        </span>
                    </div>
                </div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label>Motif</label>
                    <div class="form-control">
                        <?= htmlspecialchars($current_rdv['motif']) ?>
                    </div>
                </div>
                
                <?php if ($current_rdv['notes_agent']): ?>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Notes précédentes</label>
                    <div class="form-control">
                        <?= htmlspecialchars($current_rdv['notes_agent']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee;">
                <h3>Mettre à jour le rendez-vous</h3>
                <form method="POST" id="status-form">
                    <input type="hidden" name="rdv_id" value="<?= $current_rdv['id'] ?>">
                    
                    <div class="form-group">
                        <label for="statut_rdv">Nouveau statut du rendez-vous</label>
                        <select id="statut_rdv" name="statut_rdv" class="form-control" required>
                            <option value="En attente" <?= $current_rdv['statut_rdv'] == 'En attente' ? 'selected' : '' ?>>En attente</option>
                            <option value="Effectué" <?= $current_rdv['statut_rdv'] == 'Effectué' ? 'selected' : '' ?>>Effectué</option>
                            <option value="Annulé" <?= $current_rdv['statut_rdv'] == 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="statut_paiement">Statut de paiement</label>
                        <select id="statut_paiement" name="statut_paiement" class="form-control" required>
                            <option value="Impayé" <?= $current_rdv['statut_paiement'] == 'Impayé' ? 'selected' : '' ?>>Impayé</option>
                            <option value="Payé" <?= $current_rdv['statut_paiement'] == 'Payé' ? 'selected' : '' ?>>Payé</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes (optionnel)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="4" placeholder="Ajoutez des notes sur le rendez-vous..."><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : htmlspecialchars($current_rdv['notes_agent']) ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_status" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Mettre à jour</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Liste de mes rendez-vous en attente</h2>
            </div>
            
            <?php if (empty($rendezvous)): ?>
                <div class="alert info"><i class="fas fa-info-circle"></i> Vous n'avez aucun rendez-vous en attente.</div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Commune</th>
                        <th>Téléphone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rendezvous as $rdv): ?>
                    <tr>
                        <td><?= htmlspecialchars($rdv['client_prenom'] . ' ' . $rdv['client_nom']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($rdv['date_rdv'])) ?></td>
                        <td><?= htmlspecialchars($rdv['commune']) ?></td>
                        <td><?= htmlspecialchars($rdv['telephone']) ?></td>
                        <td>
                            <a href="rendezvous.php?id=<?= $rdv['id'] ?>" class="btn btn-primary"><i class="fas fa-eye"></i> Détails</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statutRdvSelect = document.getElementById('statut_rdv');
            const statutPaiementSelect = document.getElementById('statut_paiement');
            
            if (statutRdvSelect && statutPaiementSelect) {
                // Fonction pour mettre à jour le statut de paiement
                function updatePaymentStatus() {
                    if (statutRdvSelect.value === 'Effectué') {
                        statutPaiementSelect.value = 'Payé';
                        statutPaiementSelect.disabled = true; // Désactiver la sélection pour éviter les erreurs
                    } else {
                        statutPaiementSelect.disabled = false;
                    }
                }
                
                // Mettre à jour à l'initialisation de la page
                updatePaymentStatus();

                // Écouter les changements sur le statut du RDV
                statutRdvSelect.addEventListener('change', updatePaymentStatus);
            }
        });
    </script>
    <?php include 'header.php'; ?>
</body>
</html>