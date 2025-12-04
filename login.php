<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier les messages de déconnexion
$message = '';
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'compte_desactive':
            $message = "Votre compte a été désactivé. Veuillez contacter l'administrateur.";
            break;
        case 'compte_supprime':
            $message = "Votre compte a été supprimé. Vous avez été déconnecté.";
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    
    if ($email && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nom, prenom, password, role, active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Vérifier si le compte est actif
            if (!$user['active']) {
                $error = "Votre compte a été désactivé. Veuillez contacter l'administrateur.";
            } else {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_prenom'] = $user['prenom'];
                $_SESSION['user_role'] = $user['role'];
                
                // Enregistrer la connexion dans l'historique
                logActivity(
                    $user['id'], 
                    'connexion', 
                    'Connexion au système depuis l\'adresse IP: ' . $_SERVER['REMOTE_ADDR']
                );
                
                // Redirection selon le rôle
                switch ($user['role']) {
                    case 'super_admin':
                        header('Location: modules/admin/');
                        break;
                    case 'planificateur':
                        header('Location: modules/planificateur/');
                        break;
                    case 'admingeneral':
                        header('Location: modules/admin_general/');
                        break;
                    case 'employe':
                        header('Location: modules/employe/');
                        break;
                    default:
                        header('Location: modules/agent/');
                        break;
                }
                exit();
            }
        } else {
            $error = "Email ou mot de passe incorrect";
        }
    } else {
        $error = "Veuillez saisir des informations valides";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Agenda Rendez-vous</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <!-- Logo entreprise -->
            <img src="logo.png" alt="Logo Entreprise">
            <h1>Agenda de Suivi des Rendez-vous</h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-info text-center" id="info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger text-center" id="erreur"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="mb-3">
                <label for="email" class="form-label">Adresse Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Entrez votre email" required>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Entrez votre mot de passe" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Se connecter</button>
        </form>
    </div>
    <script>
        // Masquer les messages après 5 secondes
        const erreurDiv = document.getElementById('erreur');
        if (erreurDiv) {
            setTimeout(() => {
                erreurDiv.style.display = 'none';
            }, 5000);
        }
        
        const infoDiv = document.getElementById('info');
        if (infoDiv) {
            setTimeout(() => {
                infoDiv.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>