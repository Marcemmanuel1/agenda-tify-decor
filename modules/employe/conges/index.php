<?php
require_once '../../../config/db_connect.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
redirectIfNotLoggedIn();

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $type_conge = $_POST['type_conge'];
    $motif = $_POST['motif'];
    $commentaires = $_POST['commentaires'];
    
    // Validation des dates
    if (strtotime($date_debut) > strtotime($date_fin)) {
        $error_msg = "La date de fin doit être postérieure à la date de début.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO demandes_conges 
                                  (user_id, date_debut, date_fin, type_conge, motif, commentaires, statut, date_demande) 
                                  VALUES (?, ?, ?, ?, ?, ?, 'en_attente', NOW())");
            $stmt->execute([$user_id, $date_debut, $date_fin, $type_conge, $motif, $commentaires]);
            
            $success_msg = "Votre demande de congés a été soumise avec succès.";
        } catch (PDOException $e) {
            $error_msg = "Erreur lors de la soumission de la demande : " . $e->getMessage();
        }
    }
}

// Récupérer les demandes de congés de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM demandes_conges WHERE user_id = ? ORDER BY date_demande DESC");
$stmt->execute([$user_id]);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les informations de l'utilisateur
$stmt_user = $pdo->prepare("SELECT nom, prenom, email FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Congés - <?= defined('APP_NAME') ? APP_NAME : 'Agenda Rendez-vous' ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="mobile-container">
        <!-- Header mobile avec bouton de retour -->
        <div class="mobile-header">
            <!-- Bouton de retour -->
            <a href="../" class="back-button">
                <i class="fas fa-arrow-left"></i>
            </a>
            
            <!-- Contenu central -->
            <div class="header-content">
                <h1>
                    <i class="fas fa-umbrella-beach"></i>
                    Mes Congés
                </h1>
                <p>Gérez vos demandes de congés</p>
            </div>
            
            <!-- Espace vide pour l'alignement symétrique -->
            <div style="width: 40px;"></div>
        </div>

        <!-- Navigation par onglets -->
        <div class="tabs-navigation">
            <button class="tab-button active" data-tab="nouvelle">
                <i class="fas fa-plus-circle"></i>
                Nouvelle
            </button>
            <button class="tab-button" data-tab="historique">
                <i class="fas fa-history"></i>
                Historique
            </button>
            <button class="tab-button" data-tab="informations">
                <i class="fas fa-info-circle"></i>
                Infos
            </button>
        </div>

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

        <!-- Onglet Nouvelle Demande -->
        <div class="tab-content active" id="nouvelle-tab">
            <div class="mobile-card">
                <h2 class="card-title">
                    <i class="fas fa-file-alt"></i>
                    Nouvelle Demande
                </h2>

                <form method="POST" action="" id="form-conges">
                    <div class="form-group">
                        <label for="date_debut" class="form-label">
                            Date de début
                        </label>
                        <input type="date" id="date_debut" name="date_debut" class="form-control" required 
                               min="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group">
                        <label for="date_fin" class="form-label">
                            Date de fin
                        </label>
                        <input type="date" id="date_fin" name="date_fin" class="form-control" required 
                               min="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Indicateur de durée -->
                    <div class="duree-indicator" id="duree-indicator">
                        Durée : <span id="duree-calcul">0</span> jour(s)
                    </div>

                    <div class="form-group">
                        <label for="type_conge" class="form-label">
                            Type de congé
                        </label>
                        <select id="type_conge" name="type_conge" class="form-control form-select" required>
                            <option value="">Choisir un type...</option>
                            <option value="conges_payes">Congés Payés</option>
                            <option value="conges_sans_solde">Congés Sans Solde</option>
                            <option value="maladie">Arrêt Maladie</option>
                            <option value="familial">Congé Familial</option>
                            <option value="maternite">Congé Maternité</option>
                            <option value="paternite">Congé Paternité</option>
                            <option value="formation">Congé Formation</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="motif" class="form-label">
                            Motif
                        </label>
                        <input type="text" id="motif" name="motif" class="form-control" 
                               placeholder="Ex: Vacances, Raisons médicales..." required>
                    </div>

                    <div class="form-group">
                        <label for="commentaires" class="form-label">
                            Commentaires
                        </label>
                        <textarea id="commentaires" name="commentaires" class="form-control" 
                                  placeholder="Informations complémentaires..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        Soumettre la demande
                    </button>
                </form>
            </div>
        </div>

        <!-- Onglet Historique -->
        <div class="tab-content" id="historique-tab">
            <div class="mobile-card">
                <h2 class="card-title">
                    <i class="fas fa-history"></i>
                    Mes Demandes
                </h2>

                <?php if (empty($demandes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Aucune demande</h3>
                        <p>Vous n'avez pas encore soumis de demande de congés.</p>
                    </div>
                <?php else: ?>
                    <div class="demandes-list">
                        <?php foreach ($demandes as $demande): ?>
                            <div class="demande-card">
                                <div class="demande-header">
                                    <div class="demande-period">
                                        <?= date('d/m/Y', strtotime($demande['date_debut'])) ?> - 
                                        <?= date('d/m/Y', strtotime($demande['date_fin'])) ?>
                                    </div>
                                    <div class="demande-type">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $demande['type_conge']))) ?>
                                    </div>
                                </div>

                                <div class="demande-motif">
                                    <strong>Motif :</strong> <?= htmlspecialchars($demande['motif']) ?>
                                </div>

                                <?php if (!empty($demande['commentaires'])): ?>
                                    <div class="demande-details">
                                        <strong>Commentaires :</strong> <?= htmlspecialchars($demande['commentaires']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="demande-footer">
                                    <div class="demande-date">
                                        <?= date('d/m/Y', strtotime($demande['date_demande'])) ?>
                                    </div>
                                    <div class="statut-badge statut-<?= $demande['statut'] ?>">
                                        <?php
                                        $statut_labels = [
                                            'en_attente' => 'En Attente',
                                            'approuve' => 'Approuvé',
                                            'refuse' => 'Refusé'
                                        ];
                                        echo $statut_labels[$demande['statut']];
                                        ?>
                                    </div>
                                </div>

                                <?php if (!empty($demande['commentaire_validation'])): ?>
                                    <div class="demande-details" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ccc;">
                                        <strong>Commentaire responsable :</strong> 
                                        <?= htmlspecialchars($demande['commentaire_validation']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Onglet Informations -->
        <div class="tab-content" id="informations-tab">
            <div class="info-card">
                <h3><i class="fas fa-user"></i> Votre Profil</h3>
                <p><strong>Collaborateur :</strong> <?= htmlspecialchars($user_info['prenom'] . ' ' . $user_info['nom']) ?></p>
                <p><strong>Email :</strong> <?= htmlspecialchars($user_info['email']) ?></p>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>