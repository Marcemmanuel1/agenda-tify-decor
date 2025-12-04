<?php

// Inclusions de fichiers nécessaires
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Définir le fuseau horaire pour éviter les décalages
date_default_timezone_set('Africa/Abidjan');

// Vérifier si l'utilisateur est connecté et a le bon statut
redirectIfNotLoggedIn();

// Vérification supplémentaire du statut utilisateur
checkUserStatus();

if (!isPlanificateur() && !isSuperAdmin()) {
    header('Location: ../agent/');
    exit();
}

$db = getDB();
$message = '';
$client = null;
$rdv = null;
$agents = [];

// Récupérer la liste des agents
$stmt = $db->query("SELECT id, nom, prenom FROM users WHERE role = 'agent' AND active = TRUE ORDER BY nom, prenom");
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si on est en mode édition, charger le rendez-vous
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $rdv_id = intval($_GET['edit']);

    $stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.genre, c.commune, c.telephone, c.canal
                         FROM rendezvous r
                         JOIN clients c ON r.client_id = c.id
                         WHERE r.id = ? AND r.planificateur_id = ?");
    $stmt->execute([$rdv_id, $_SESSION['user_id']]);
    $rdv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rdv && !isSuperAdmin()) {
        $message = '<div class="alert error">Rendez-vous non trouvé ou accès non autorisé.</div>';
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = filter_input(INPUT_POST, 'nom', FILTER_SANITIZE_STRING);
    $prenom = filter_input(INPUT_POST, 'prenom', FILTER_SANITIZE_STRING);
    $genre = filter_input(INPUT_POST, 'genre', FILTER_SANITIZE_STRING);
    $commune = filter_input(INPUT_POST, 'commune', FILTER_SANITIZE_STRING);
    $telephone = filter_input(INPUT_POST, 'telephone', FILTER_SANITIZE_STRING);
    $canal = filter_input(INPUT_POST, 'canal', FILTER_SANITIZE_STRING);
    $date_contact = filter_input(INPUT_POST, 'date_contact', FILTER_SANITIZE_STRING);
    $date_rdv = filter_input(INPUT_POST, 'date_rdv', FILTER_SANITIZE_STRING);
    $statut_paiement = filter_input(INPUT_POST, 'statut_paiement', FILTER_SANITIZE_STRING);
    $motif = filter_input(INPUT_POST, 'motif', FILTER_SANITIZE_STRING);
    $agent_id = filter_input(INPUT_POST, 'agent_id', FILTER_VALIDATE_INT);

    if ($nom && $prenom && $genre && $commune && $telephone && $date_contact && $date_rdv && $statut_paiement && $motif) {
        try {
            $db->beginTransaction();

            // Normaliser la valeur du canal (mettre la première lettre en majuscule)
            if ($canal) {
                $canal = ucfirst(strtolower($canal));
                // Gérer les cas spéciaux
                if ($canal === 'Tiktok') {
                    $canal = 'TikTok'; // Format standard
                }
            }

            // Vérifier si le client existe déjà
            $stmt = $db->prepare("SELECT id FROM clients WHERE nom = ? AND prenom = ? AND telephone = ?");
            $stmt->execute([$nom, $prenom, $telephone]);
            $client_id = $stmt->fetchColumn();

            // Créer le client s'il n'existe pas
            if (!$client_id) {
                $stmt = $db->prepare("INSERT INTO clients (nom, prenom, genre, commune, telephone, canal) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $prenom, $genre, $commune, $telephone, $canal]);
                $client_id = $db->lastInsertId();
            } else {
                // Mettre à jour le canal si le client existe déjà
                $stmt = $db->prepare("UPDATE clients SET canal = ? WHERE id = ?");
                $stmt->execute([$canal, $client_id]);
            }

            // Conversion des dates
            $date_contact_timestamp = strtotime($date_contact);
            if ($date_contact_timestamp === false) {
                throw new Exception("Format de date de contact invalide");
            }

            $date_rdv_timestamp = strtotime($date_rdv);
            if ($date_rdv_timestamp === false) {
                throw new Exception("Format de date de rendez-vous invalide");
            }

            $date_contact_mysql = date('Y-m-d H:i:s', $date_contact_timestamp);
            $date_rdv_mysql = date('Y-m-d H:i:s', $date_rdv_timestamp);

            // Récupérer le nom de l'agent pour l'historique
            $agent_nom = '';
            if ($agent_id) {
                $stmt = $db->prepare("SELECT CONCAT(prenom, ' ', nom) as nom_complet FROM users WHERE id = ?");
                $stmt->execute([$agent_id]);
                $agent_nom = $stmt->fetchColumn();
            }

            // Créer ou mettre à jour le rendez-vous
            if ($rdv) {
                // Mode édition - Récupérer les anciennes valeurs pour l'historique
                $old_agent_id = $rdv['agent_id'];
                $old_date_rdv = $rdv['date_rdv'];
                $old_statut_paiement = $rdv['statut_paiement'];

                // Mettre à jour le rendez-vous
                $stmt = $db->prepare("UPDATE rendezvous SET client_id = ?, agent_id = ?, date_contact = ?, date_rdv = ?,
                                       statut_paiement = ?, motif = ? WHERE id = ?");
                $stmt->execute([$client_id, $agent_id, $date_contact_mysql, $date_rdv_mysql, $statut_paiement, $motif, $rdv['id']]);

                // Construire le message d'historique avec les changements
                $changes = [];
                if ($old_date_rdv != $date_rdv_mysql) {
                    $changes[] = "Date: " . date('d/m/Y H:i', strtotime($old_date_rdv)) . " → " . date('d/m/Y H:i', strtotime($date_rdv_mysql));
                }
                if ($old_agent_id != $agent_id) {
                    $old_agent_nom = '';
                    if ($old_agent_id) {
                        $stmt = $db->prepare("SELECT CONCAT(prenom, ' ', nom) as nom_complet FROM users WHERE id = ?");
                        $stmt->execute([$old_agent_id]);
                        $old_agent_nom = $stmt->fetchColumn();
                    }
                    $changes[] = "Agent: " . ($old_agent_nom ?: 'Non assigné') . " → " . ($agent_nom ?: 'Non assigné');
                }
                if ($old_statut_paiement != $statut_paiement) {
                    $changes[] = "Paiement: {$old_statut_paiement} → {$statut_paiement}";
                }

                // Enregistrer dans l'historique
                $action_description = "Modification du rendez-vous avec $prenom $nom";
                if (!empty($changes)) {
                    $action_description .= " | " . implode(', ', $changes);
                }
                logActivity(
                    $_SESSION['user_id'],
                    'modification',
                    $action_description
                );

                $message = '<div class="alert success">Rendez-vous modifié avec succès.</div>';
            } else {
                // Mode création
                $stmt = $db->prepare("INSERT INTO rendezvous (client_id, planificateur_id, agent_id, date_contact, date_rdv, statut_paiement, motif)
                                     VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$client_id, $_SESSION['user_id'], $agent_id, $date_contact_mysql, $date_rdv_mysql, $statut_paiement, $motif]);

                $rdv_id = $db->lastInsertId();

                // Enregistrer dans l'historique
                $action_description = "Création d'un nouveau rendez-vous avec $prenom $nom le " . date('d/m/Y à H:i', strtotime($date_rdv_mysql));
                if ($agent_nom) {
                    $action_description .= " | Agent assigné: $agent_nom";
                }
                $action_description .= " | Commune: $commune | Paiement: $statut_paiement";
                if ($canal) {
                    $action_description .= " | Canal: $canal";
                }

                logActivity(
                    $_SESSION['user_id'],
                    'creation',
                    $action_description
                );

                // Créer une notification pour l'agent
                if ($agent_id) {
                    $message_notif = "Nouveau rendez-vous assigné: $prenom $nom le " . date('d/m/Y à H:i', strtotime($date_rdv_mysql));
                    $stmt = $db->prepare("INSERT INTO notifications (user_id, message, lien) VALUES (?, ?, ?)");
                    $stmt->execute([$agent_id, $message_notif, "../agent/rendezvous.php?id=$rdv_id"]);

                    // Log de la notification dans l'historique
                    logActivity(
                        $_SESSION['user_id'],
                        'notification',
                        "Notification envoyée à $agent_nom pour le rendez-vous avec $prenom $nom"
                    );
                }

                $message = '<div class="alert success">Rendez-vous créé avec succès.</div>';
            }

            $db->commit();

            // Recharger les données si en mode édition
            if ($rdv) {
                $stmt = $db->prepare("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.genre, c.commune, c.telephone, c.canal
                                       FROM rendezvous r
                                       JOIN clients c ON r.client_id = c.id
                                       WHERE r.id = ?");
                $stmt->execute([$rdv['id']]);
                $rdv = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            // Meilleure gestion du rollback
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $message = '<div class="alert error">Erreur: ' . $e->getMessage() . '</div>';

            // Log de l'erreur
            logActivity(
                $_SESSION['user_id'],
                'erreur',
                "Erreur lors de la " . ($rdv ? 'modification' : 'création') . " du rendez-vous: " . $e->getMessage()
            );
        }
    } else {
        $message = '<div class="alert error">Veuillez remplir tous les champs obligatoires.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $rdv ? 'Modifier' : 'Nouveau' ?> Rendez-vous - Agenda Rendez-vous</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><?= $rdv ? 'Modifier le rendez-vous' : 'Nouveau rendez-vous' ?></h1>
            <a href="liste_rdv.php" class="btn btn-secondary" style="text-decoration:none;">Retour à la liste</a>
        </div>

        <?php echo $message; ?>

        <div class="card">
            <form method="POST" id="rdvForm">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" class="form-control"
                               value="<?= $rdv ? htmlspecialchars($rdv['client_nom']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" class="form-control"
                               value="<?= $rdv ? htmlspecialchars($rdv['client_prenom']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="genre">Genre *</label>
                        <select id="genre" name="genre" class="form-control" required>
                            <option value="">Sélectionner</option>
                            <option value="M" <?= $rdv && $rdv['genre'] == 'M' ? 'selected' : '' ?>>Masculin</option>
                            <option value="F" <?= $rdv && $rdv['genre'] == 'F' ? 'selected' : '' ?>>Féminin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="commune">Commune *</label>
                        <input type="text" id="commune" name="commune" class="form-control"
                               value="<?= $rdv ? htmlspecialchars($rdv['commune']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="telephone">Téléphone *</label>
                        <input type="text" id="telephone" name="telephone" class="form-control"
                               value="<?= $rdv ? htmlspecialchars($rdv['telephone']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="canal">Comment avez-vous connu l'entreprise ?</label>
                        <select id="canal" name="canal" class="form-control">
                            <option value="">Sélectionner</option>
                            <option value="Reference" <?= $rdv && isset($rdv['canal']) && strtolower($rdv['canal']) == 'reference' ? 'selected' : '' ?>>Référence (bouche à oreille)</option>
                            <option value="Facebook" <?= $rdv && isset($rdv['canal']) && strtolower($rdv['canal']) == 'facebook' ? 'selected' : '' ?>>Facebook</option>
                            <option value="Instagram" <?= $rdv && isset($rdv['canal']) && strtolower($rdv['canal']) == 'instagram' ? 'selected' : '' ?>>Instagram</option>
                            <option value="TikTok" <?= $rdv && isset($rdv['canal']) && (strtolower($rdv['canal']) == 'tiktok' || strtolower($rdv['canal']) == 'tik tok') ? 'selected' : '' ?>>TikTok</option>
                            <option value="Site_web" <?= $rdv && isset($rdv['canal']) && strtolower($rdv['canal']) == 'site_web' ? 'selected' : '' ?>>Site web</option>
                            <option value="Publicite" <?= $rdv && isset($rdv['canal']) && strtolower($rdv['canal']) == 'publicite' ? 'selected' : '' ?>>Publicité</option>
                            <option value="Salon" <?= $rdv && isset($rdv['canal']) && strtolower($rdv['canal']) == 'salon' ? 'selected' : '' ?>>Salon/Événement</option>
                            <option value="Autre" <?= $rdv && isset($rdv['canal']) && strtolower($rdv['canal']) == 'autre' ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_contact">Date de contact *</label>
                        <input type="datetime-local" id="date_contact" name="date_contact" class="form-control"
                               value="<?= $rdv ? date('Y-m-d\TH:i', strtotime($rdv['date_contact'])) : date('Y-m-d\TH:i') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="date_rdv">Date du rendez-vous *</label>
                        <input type="datetime-local" id="date_rdv" name="date_rdv" class="form-control"
                               value="<?= $rdv ? date('Y-m-d\TH:i', strtotime($rdv['date_rdv'])) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="statut_paiement">Statut de paiement *</label>
                        <select id="statut_paiement" name="statut_paiement" class="form-control" required>
                            <option value="">Sélectionner</option>
                            <option value="Payé" <?= $rdv && $rdv['statut_paiement'] == 'Payé' ? 'selected' : '' ?>>Payé</option>
                            <option value="Impayé" <?= $rdv && $rdv['statut_paiement'] == 'Impayé' ? 'selected' : '' ?>>Impayé</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="agent_id">Agent (optionnel)</label>
                        <select id="agent_id" name="agent_id" class="form-control">
                            <option value="">Non assigné</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['id'] ?>" <?= $rdv && $rdv['agent_id'] == $agent['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label for="motif">Motif *</label>
                        <textarea id="motif" name="motif" class="form-control" rows="4" required><?= $rdv ? htmlspecialchars($rdv['motif']) : '' ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary"><?= $rdv ? 'Modifier' : 'Créer' ?> le rendez-vous</button>
                    <a href="liste_rdv.php" class="btn btn-secondary" style="text-decoration:none;">Annuler</a>
                </div>
            </form>
        </div>
    </div>

    <script src="../../js/script.js"></script>
    <script src="../../js/validation.js"></script>
</body>
</html>