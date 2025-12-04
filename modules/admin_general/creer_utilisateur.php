<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require '../../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

redirectIfNotLoggedIn();
if (!isAdminGeneral()) {
    header('Location: ../admin_general/');
    exit();
}

// Génération mot de passe aléatoire - FONCTION DÉFINIE EN PREMIER
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    $charsLength = strlen($chars);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $charsLength - 1)];
    }
    return $password;
}

// Fonction pour envoyer email via PHPMailer - AMÉLIORÉE
function sendCredentialsEmail($email, $nom, $prenom, $password) {
    $mail = new PHPMailer(true);
    try {
        // Configuration SMTP avec debug
        $mail->isSMTP();
        $mail->Host = 'mail.tifydecor.org';
        $mail->SMTPAuth = true;
        $mail->Username = 'assistancetech@tifydecor.org';
        $mail->Password = 'Goldegelil@1'; 
        
        // Essayer différentes configurations
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Activer le debug temporairement (commenter en production)
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = 'html';
        
        // Timeout et encodage
        $mail->Timeout = 60;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Utiliser l'adresse authentifiée comme expéditeur
        $mail->setFrom('broumarc@sp-p6.com', 'Agenda Rendez-vous');
        $mail->addAddress($email, "$prenom $nom");
        $mail->addReplyTo('broumarc@sp-p6.com', 'Agenda Rendez-vous');

        $mail->isHTML(true);
        $mail->Subject = 'Vos identifiants pour Agenda Rendez-vous';

        // URL du site
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $url = $protocol . "://" . $_SERVER['HTTP_HOST'];

        // HTML du mail simplifié pour éviter les problèmes
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; background-color:#F5F1EB; color:#333; margin:0; padding:20px; }
                .container { max-width:600px; margin:0 auto; padding:20px; background: #FFFFFF; border-radius:10px; }
                .header { background: #8A9A5B; color:#fff; padding:20px; text-align:center; border-radius:10px 10px 0 0; }
                .content { padding:20px; line-height:1.6; }
                .credentials { background:#FDF6E3; padding:15px; margin:15px 0; border-left:5px solid #C89B66; }
                .button { display:inline-block; padding:10px 20px; background:#8A9A5B; color:#fff; text-decoration:none; border-radius:5px; margin-top:10px; }
                .warning { color:#dc3545; font-weight:bold; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Agenda Rendez-vous</h1>
                </div>
                <div class="content">
                    <h2>Bonjour ' . htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . ',</h2>
                    <p>Votre compte a été créé avec succès sur notre plateforme Agenda Rendez-vous.</p>
                    <div class="credentials">
                        <h3>Vos identifiants :</h3>
                        <p><strong>Email :</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>
                        <p><strong>Mot de passe :</strong> ' . htmlspecialchars($password, ENT_QUOTES, 'UTF-8') . '</p>
                    </div>
                    <p class="warning">Pour des raisons de sécurité, changez votre mot de passe après votre première connexion.</p>
                    <a class="button" href="' . $url . '">Se connecter</a>
                    <p>Cordialement,<br>L\'équipe Agenda Rendez-vous</p>
                </div>
            </div>
        </body>
        </html>';

        // Version texte alternative
        $mail->AltBody = "Bonjour $prenom $nom,\n\n";
        $mail->AltBody .= "Votre compte a été créé avec succès.\n\n";
        $mail->AltBody .= "Vos identifiants :\n";
        $mail->AltBody .= "Email : $email\n";
        $mail->AltBody .= "Mot de passe : $password\n\n";
        $mail->AltBody .= "Changez votre mot de passe après votre première connexion.\n\n";
        $mail->AltBody .= "Lien de connexion : $url\n\n";
        $mail->AltBody .= "Cordialement,\nL'équipe Agenda Rendez-vous";

        $result = $mail->send();
        return $result;
        
    } catch (Exception $e) {
        // Log détaillé de l'erreur
        $errorMessage = "Erreur envoi email pour $email: " . $mail->ErrorInfo;
        if ($e->getMessage()) {
            $errorMessage .= " | Exception: " . $e->getMessage();
        }
        error_log($errorMessage);
        return false;
    }
}

// Fonction alternative avec configuration différente
function sendCredentialsEmailAlternative($email, $nom, $prenom, $password) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'mail.sp-p6.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'broumarc@sp-p6.com';
        $mail->Password = 'Goldegelil@1';
        
        // Configuration alternative sans cryptage
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
        $mail->Port = 25;
        
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('broumarc@sp-p6.com', 'Agenda Rendez-vous');
        $mail->addAddress($email, "$prenom $nom");
        
        $mail->isHTML(true);
        $mail->Subject = 'Vos identifiants pour Agenda Rendez-vous';
        
        // Email simplifié
        $mail->Body = "<h2>Bonjour $prenom $nom</h2>";
        $mail->Body .= "<p>Votre compte a été créé avec succès.</p>";
        $mail->Body .= "<p><strong>Email:</strong> $email</p>";
        $mail->Body .= "<p><strong>Mot de passe:</strong> $password</p>";
        $mail->Body .= "<p><strong>Important:</strong> Changez votre mot de passe après la première connexion.</p>";
        $mail->Body .= "<p>Cordialement,<br>L'équipe Agenda Rendez-vous</p>";
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Erreur email alternative: " . $e->getMessage());
        return false;
    }
}

// Initialisation des variables
$errors = [];
$success = false;

// Gestion du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';

    // Validation des données
    if (empty($nom)) {
        $errors[] = "Le nom est requis.";
    }
    
    if (empty($prenom)) {
        $errors[] = "Le prénom est requis.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    }
    
    if (empty($role) || !in_array($role, ['super_admin', 'planificateur', 'agent'])) {
        $errors[] = "Rôle invalide.";
    }

    // Vérification de l'unicité de l'email
    if (empty($errors)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Cet email est déjà utilisé.";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur de base de données : " . $e->getMessage();
        }
    }

    // Création de l'utilisateur si pas d'erreurs
    if (empty($errors)) {
        try {
            // Génération du mot de passe
            $password = generatePassword();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insertion en base de données
            $stmt = $db->prepare("INSERT INTO users (nom, prenom, email, password, role) VALUES (:nom, :prenom, :email, :password, :role)");
            $stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
            $stmt->bindParam(':prenom', $prenom, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(':role', $role, PDO::PARAM_STR);

            if ($stmt->execute()) {
                // Tentative d'envoi d'email
                $emailSent = sendCredentialsEmail($email, $nom, $prenom, $password);
                
                // Si échec, essayer la méthode alternative
                if (!$emailSent) {
                    error_log("Premier essai d'envoi échoué, tentative alternative...");
                    $emailSent = sendCredentialsEmailAlternative($email, $nom, $prenom, $password);
                }
                
                if ($emailSent) {
                    // Redirection en cas de succès
                    header("Location: gestion-utilisateur.php?success=create");
                    exit();
                } else {
                    // Succès mais email non envoyé
                    $success = true;
                    $errors[] = "Utilisateur créé avec succès, mais l'email n'a pas pu être envoyé. Identifiants : Email = $email, Mot de passe = $password";
                }
            } else {
                $errors[] = "Erreur lors de la création de l'utilisateur en base de données.";
            }
            
        } catch (PDOException $e) {
            $errors[] = "Erreur de base de données : " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "Erreur inattendue : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Utilisateur - Agenda Rendez-vous</title>
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
            --rouge: #dc3545;
        }

        .form-container {
            max-width: 600px;
            margin: 0 auto;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--vert-sage);
            box-shadow: 0 0 0 2px rgba(138, 154, 91, 0.2);
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
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .password-info {
            background-color: #e2f0fb;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
            border-left: 4px solid var(--vert-sage);
        }

        .credentials-display {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Créer un Utilisateur</h1>
            <a href="gestion_utilisateurs.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la liste
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h3>Erreurs :</h3>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Succès !</strong> L'utilisateur a été créé avec succès.
                <?php if (!empty($errors)): ?>
                    <br><em>Note : L'email de notification n'a pas pu être envoyé.</em>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Nouvel utilisateur</h2>
            </div>
            
            <div class="form-container">
                <form method="POST">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" required value="<?= htmlspecialchars($_POST['prenom'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Rôle *</label>
                        <select id="role" name="role" required>
                            <option value="">Sélectionner un rôle</option>
                            <option value="agent" <?= (($_POST['role'] ?? '') == 'agent') ? 'selected' : '' ?>>Agent</option>
                            <option value="planificateur" <?= (($_POST['role'] ?? '') == 'planificateur') ? 'selected' : '' ?>>Planificateur</option>
                            <option value="super_admin" <?= (($_POST['role'] ?? '') == 'super_admin') ? 'selected' : '' ?>>Super Admin</option>
                            <option value="super_admin" <?= (($_POST['role'] ?? '') == 'employe') ? 'selected' : '' ?>>Collaborateur</option>                        </select>
                    </div>
                    
                    <div class="password-info">
                        <p><strong>Information :</strong> Un mot de passe aléatoire sera généré automatiquement et envoyé par email à l'utilisateur.</p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-user-plus"></i> Créer l'utilisateur
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../../js/script.js"></script>
</body>
</html>