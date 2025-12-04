<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

redirectIfNotLoggedIn();
if (!isAdminGeneral()) {
    header('Location: ../admin_general/');
    exit();
}

// Fonction pour déconnecter un utilisateur par son ID
function forceLogoutUser($user_id) {
    // Démarrer la session si elle n'est pas déjà démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Si l'utilisateur cible est actuellement connecté, le déconnecter
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
        // Enregistrer la déconnexion forcée dans l'historique
        logActivity(
            $user_id, 
            'deconnexion_forcee', 
            'Déconnexion forcée suite à suppression/désactivation du compte depuis l\'adresse IP: ' . $_SERVER['REMOTE_ADDR']
        );
        
        // Détruire la session
        session_destroy();
        
        // Rediriger vers la page de connexion avec un message
        header('Location: ../../login.php?message=compte_supprime');
        exit();
    }
}

// Récupérer la liste des utilisateurs
$db = getDB();
$stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$messageType = ''; // 'success' ou 'error'

// Traitement de la suppression
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // Ne pas permettre de se supprimer soi-même
    if ($user_id != $_SESSION['user_id']) {
        try {
            $db->beginTransaction();
            
            // 1. Supprimer les logs de suppression liés à l'utilisateur
            $stmt = $db->prepare("DELETE FROM logs_suppression WHERE user_id = :id");
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // 2. Supprimer les notifications liées à l'utilisateur
            $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = :id");
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            
            // 7. Finalement, supprimer l'utilisateur
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $db->commit();
                $message = "Utilisateur supprimé avec succès.";
                $messageType = 'success';
                
                // Déconnecter l'utilisateur s'il est connecté
                forceLogoutUser($user_id);
                
                // Recharger la liste des utilisateurs après suppression
                $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $db->rollBack();
                $message = "Une erreur s'est produite lors de la suppression.";
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $db->rollBack();
            $message = "Erreur lors de la suppression: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "Vous ne pouvez pas supprimer votre propre compte.";
        $messageType = 'error';
    }
}

// Traitement du changement de statut actif/inactif
if (isset($_POST['toggle_status'])) {
    $user_id = $_POST['user_id'];
    $current_status = $_POST['current_status'];
    $new_status = $current_status ? 0 : 1;
    
    $stmt = $db->prepare("UPDATE users SET active = :active WHERE id = :id");
    $stmt->bindParam(':active', $new_status, PDO::PARAM_INT);
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $status_type = $new_status ? 'activate' : 'deactivate';
        $message = $new_status ? "Utilisateur activé avec succès." : "Utilisateur désactivé avec succès.";
        $messageType = 'success';
        
        // Si l'utilisateur est désactivé, le déconnecter s'il est connecté
        if (!$new_status) {
            forceLogoutUser($user_id);
        }
        
        // Recharger la liste des utilisateurs après modification
        $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $message = "Une erreur s'est produite lors de la modification du statut.";
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Agenda Rendez-vous</title>
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--ivoire);
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

        .btn-danger {
            background-color: var(--rouge);
            color: white;
        }

        .btn-danger:hover {
            background-color: #bd2130;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
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

        .badge-success {
            background-color: var(--vert-clair);
            color: var(--vert-fonce);
        }

        .badge-danger {
            background-color: var(--rouge-clair);
            color: var(--rouge-fonce);
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background-color: var(--vert-clair);
            color: var(--vert-fonce);
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: var(--rouge-clair);
            color: var(--rouge-fonce);
            border: 1px solid #f5c6cb;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-active {
            background-color: var(--vert-clair);
            color: var(--vert-fonce);
        }

        .status-inactive {
            background-color: var(--rouge-clair);
            color: var(--rouge-fonce);
        }

        form {
            display: inline;
        }

        .card {
            background-color: var(--blanc);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--ombre);
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--ivoire);
        }

        .card-title {
            margin: 0;
            color: var(--doré-foncé);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: var(--blanc);
            margin: 15% auto;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            width: 400px;
            max-width: 80%;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            color: var(--doré-foncé);
            margin: 0;
        }

        .modal-close {
            color: #aaa;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-close:hover {
            color: var(--gris-anthracite);
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .table {
                font-size: 0.9rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .modal-content {
                width: 90%;
                margin: 20% auto;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Gestion des Utilisateurs</h1>
            <a href="creer_utilisateur.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Créer un utilisateur
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas <?= $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Liste des utilisateurs</h2>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): 
                                $role_class = [
                                    'super_admin' => 'badge-warning',
                                    'planificateur' => 'badge-info',
                                    'agent' => 'badge-success',
                                    'admingeneral' => 'badge-danger',
                                    'employe' => 'badge-success'
                                 ][$user['role']];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><span class="badge <?= $role_class ?>"><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></span></td> 
                                <td>
                                    <span class="status-badge <?= $user['active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $user['active'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                <td class="actions">
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $user['active'] ?>">
                                        <button type="submit" name="toggle_status" class="btn btn-sm <?= $user['active'] ? 'btn-secondary' : 'btn-primary' ?>">
                                            <i class="fas <?= $user['active'] ? 'fa-user-times' : 'fa-user-check' ?>"></i>
                                            <?= $user['active'] ? 'Désactiver' : 'Activer' ?>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-danger btn-sm delete-btn" data-user-id="<?= $user['id'] ?>" data-user-name="<?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">
                                    Aucun utilisateur trouvé.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirmer la suppression</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="user-name"></strong> ?</p>
                <p class="text-danger">Cette action est irréversible et supprimera également toutes les données associées (notifications, logs, etc.).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelDelete">Annuler</button>
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="user_id" id="user-id">
                    <button type="submit" name="delete_user" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../../js/script.js"></script>
    <script>
        // Gestion du modal de suppression
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('deleteModal');
            const deleteBtns = document.querySelectorAll('.delete-btn');
            const userNameSpan = document.getElementById('user-name');
            const userIdInput = document.getElementById('user-id');
            const closeBtn = document.querySelector('.modal-close');
            const cancelBtn = document.getElementById('cancelDelete');
            const deleteForm = document.getElementById('deleteForm');
            
            // Ouvrir le modal lors du clic sur un bouton de suppression
            deleteBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name');
                    
                    userIdInput.value = userId;
                    userNameSpan.textContent = userName;
                    
                    modal.style.display = 'block';
                });
            });
            
            // Fermer le modal
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            cancelBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Cacher les messages d'alerte après 5 secondes
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.display = 'none';
                });
            }, 5000);
        });
    </script>
</body>
</html>