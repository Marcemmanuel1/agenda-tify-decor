<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Redirection si l'utilisateur n'est pas connecté
redirectIfNotLoggedIn();

// Vérification supplémentaire du statut utilisateur
checkUserStatus();

$db = getDB();
$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;

// Récupérer les informations de l'utilisateur
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($nom)) {
        $errors[] = "Le nom est requis";
    }
    
    if (empty($prenom)) {
        $errors[] = "Le prénom est requis";
    }
    
    // Vérifier si l'utilisateur veut changer son mot de passe
    $change_password = !empty($current_password) || !empty($new_password) || !empty($confirm_password);
    
    if ($change_password) {
        // Vérifier que tous les champs de mot de passe sont remplis
        if (empty($current_password)) {
            $errors[] = "L'ancien mot de passe est requis pour modifier le mot de passe";
        } elseif (empty($new_password)) {
            $errors[] = "Le nouveau mot de passe est requis";
        } elseif (empty($confirm_password)) {
            $errors[] = "La confirmation du mot de passe est requise";
        } else {
            // Vérifier l'ancien mot de passe
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "L'ancien mot de passe est incorrect";
            }
            
            // Vérifier la longueur du nouveau mot de passe
            if (strlen($new_password) < 6) {
                $errors[] = "Le nouveau mot de passe doit contenir au moins 6 caractères";
            }
            
            // Vérifier que les nouveaux mots de passe correspondent
            if ($new_password !== $confirm_password) {
                $errors[] = "Les nouveaux mots de passe ne correspondent pas";
            }
        }
    }
    
    // Si aucune erreur, mettre à jour les informations
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            if ($change_password) {
                // Mettre à jour avec le nouveau mot de passe
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET nom = ?, prenom = ?, password = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $hashed_password, $user_id]);
                
                // Enregistrer dans l'historique
                logActivity(
                    $user_id,
                    'modification',
                    "Modification du profil et du mot de passe par l'utilisateur: " . 
                    "Nom: {$user['nom']} -> $nom, " .
                    "Prénom: {$user['prenom']} -> $prenom"
                );
            } else {
                // Mettre à jour sans changer le mot de passe
                $stmt = $db->prepare("UPDATE users SET nom = ?, prenom = ? WHERE id = ?");
                $stmt->execute([$nom, $prenom, $user_id]);
                
                // Enregistrer dans l'historique
                logActivity(
                    $user_id,
                    'modification',
                    "Modification du profil par l'utilisateur: " . 
                    "Nom: {$user['nom']} -> $nom, " .
                    "Prénom: {$user['prenom']} -> $prenom"
                );
            }
            
            $db->commit();
            
            // Mettre à jour les variables de session
            $_SESSION['user_nom'] = $nom;
            $_SESSION['user_prenom'] = $prenom;
            
            $success = true;
            
            // Recharger les informations de l'utilisateur
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Une erreur s'est produite lors de la mise à jour du profil";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Agenda Rendez-vous</title>
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
        }

        .form-control:focus {
            outline: none;
            border-color: var(--vert-sage);
            box-shadow: 0 0 0 2px rgba(138, 154, 91, 0.2);
        }

        .form-control:disabled {
            background-color: #f5f5f5;
            color: #888;
            cursor: not-allowed;
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

        hr {
            margin: 1.5rem 0;
            border: none;
            border-top: 1px solid #eee;
        }

        small {
            font-size: 0.9rem;
            color: #888;
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
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php 
    // Inclure le header approprié selon le rôle
    if (isSuperAdmin()) {
        include 'header.php';
    } else {
        include '../planificateur/header.php';
    }
    ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Mon Profil</h1>
            <span>Gérez vos informations personnelles</span>
        </div>
        
        <?php if ($success): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> Votre profil a été mis à jour avec succès.
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> 
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Informations personnelles</h2>
            </div>
            
            <form method="POST" class="profile-form">
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" class="form-control" 
                           value="<?= htmlspecialchars($user['nom']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" class="form-control" 
                           value="<?= htmlspecialchars($user['prenom']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" class="form-control" 
                           value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    <small>L'adresse email ne peut pas être modifiée</small>
                </div>
                
                <div class="form-group">
                    <label for="role">Rôle</label>
                    <input type="text" id="role" class="form-control" 
                           value="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))) ?>" disabled>
                </div>
                
                <hr>
                
                <h3 style="margin-bottom: 1rem; color: var(--doré-foncé);">Changer le mot de passe</h3>
                <p style="margin-bottom: 1rem; color: #888; font-style: italic;">
                    Remplissez ces champs uniquement si vous souhaitez modifier votre mot de passe
                </p>
                
                <div class="form-group">
                    <label for="current_password">Ancien mot de passe</label>
                    <input type="password" id="current_password" name="current_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" id="new_password" name="new_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../../js/script.js"></script>
</body>
</html>