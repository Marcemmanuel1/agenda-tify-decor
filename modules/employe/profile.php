<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Vérifier si l'utilisateur est connecté et a le bon statut
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
        if ($change_password) {
            // Mettre à jour avec le nouveau mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET nom = ?, prenom = ?, password = ? WHERE id = ?");
            $stmt->execute([$nom, $prenom, $hashed_password, $user_id]);
        } else {
            // Mettre à jour sans changer le mot de passe
            $stmt = $db->prepare("UPDATE users SET nom = ?, prenom = ? WHERE id = ?");
            $stmt->execute([$nom, $prenom, $user_id]);
        }
        
        // Mettre à jour les variables de session
        $_SESSION['user_nom'] = $nom;
        $_SESSION['user_prenom'] = $prenom;
        
        $success = true;
        
        // Recharger les informations de l'utilisateur
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Agenda Rendez-vous</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Variables CSS avec la palette de couleurs */
        :root {
            --doré-foncé: #8B5A2B;
            --doré-clair: #C89B66;
            --ivoire: #F5F1EB;
            --blanc: #FFFFFF;
            --gris-anthracite: #333333;
            --vert-sage: #8A9A5B;
            --ombre: rgba(0, 0, 0, 0.1);
        }

        /* Reset et styles de base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--ivoire);
            color: var(--gris-anthracite);
            line-height: 1.6;
            padding-top: 70px;
        }

        /* En-tête RESPONSIVE */
        .header {
            background: linear-gradient(135deg, var(--doré-foncé), var(--doré-clair));
            color: var(--blanc);
            padding: 1rem clamp(1rem, 3vw, 2rem);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px var(--ombre);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
            gap: 1rem;
        }

        .header h1 {
            font-size: clamp(1.2rem, 4vw, 1.8rem);
            font-weight: 600;
            line-height: 1.2;
            text-align: left;
            flex-shrink: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: clamp(0.5rem, 2vw, 1rem);
            flex-shrink: 0;
        }

        .user-info span {
            font-weight: 500;
            font-size: clamp(0.8rem, 2vw, 1rem);
            text-align: right;
            line-height: 1.3;
        }

        .btn-logout {
            background-color: transparent;
            border: 1px solid var(--blanc);
            color: var(--blanc);
            padding: clamp(0.4rem, 1.5vw, 0.5rem) clamp(0.8rem, 2vw, 1rem);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: clamp(0.8rem, 2vw, 1rem);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-logout:hover {
            background-color: var(--blanc);
            color: var(--doré-foncé);
        }

        

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            padding: 0.8rem 1.5rem;
            margin: 0.5rem 0;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .nav-item:hover, .nav-item.active {
            background-color: var(--ivoire);
            border-right: 4px solid var(--doré-foncé);
        }

        .nav-item i {
            color: var(--doré-foncé);
            width: 20px;
            text-align: center;
        }

        /* Contenu principal */
        .main-content {
          width: 100%;
          padding: clamp(1rem, 3vw, 2rem);
          margin-top: 0;
        }

        .page-header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .page-header-top {
            display: flex;
            align-items: center;
            gap: clamp(0.8rem, 3vw, 1.5rem);
            width: 100%;
            flex-wrap: wrap;
        }

        .page-title {
            font-size: clamp(1.4rem, 4vw, 1.8rem);
            color: var(--doré-foncé);
            font-weight: 600;
            flex: 1;
        }

        /* Styles pour le bouton retour */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            padding: clamp(0.5rem, 2vw, 0.7rem) clamp(0.8rem, 3vw, 1.2rem);
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.3s;
            background-color: var(--vert-sage);
            color: var(--blanc);
            border: none;
            cursor: pointer;
            font-size: clamp(0.8rem, 2vw, 1rem);
            white-space: nowrap;
        }

        .btn-back:hover {
            background-color: #7a8a4b;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px var(--ombre);
        }

        /* Cartes */
        .card {
            background-color: var(--blanc);
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--ombre);
            padding: clamp(1rem, 3vw, 1.5rem);
            margin-bottom: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid var(--ivoire);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-title {
            font-size: clamp(1.1rem, 3vw, 1.2rem);
            color: var(--doré-foncé);
            font-weight: 600;
        }

        /* Formulaires */
        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gris-anthracite);
            font-size: clamp(0.9rem, 2vw, 1rem);
        }

        .form-control {
            width: 100%;
            padding: clamp(0.6rem, 2vw, 0.8rem);
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: clamp(0.9rem, 2vw, 1rem);
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--doré-clair);
            box-shadow: 0 0 0 2px rgba(200, 155, 102, 0.2);
        }

        /* Boutons */
        .btn {
            padding: clamp(0.6rem, 2vw, 0.8rem) clamp(1rem, 3vw, 1.5rem);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: clamp(0.9rem, 2vw, 1rem);
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--doré-foncé);
            color: var(--blanc);
        }

        .btn-primary:hover {
            background-color: var(--doré-clair);
        }

        .btn-secondary {
            background-color: var(--vert-sage);
            color: var(--blanc);
        }

        .btn-secondary:hover {
            background-color: #7a8a4b;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: var(--blanc);
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        /* Alertes */
        .alert {
            padding: clamp(0.6rem, 2vw, 0.8rem) clamp(0.8rem, 2vw, 1rem);
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: clamp(0.8rem, 2vw, 1rem);
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* RESPONSIVE DESIGN */
            

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                height: auto;
                min-height: 110px;
                padding: 0.8rem 1rem;
                gap: 0.8rem;
            }
            
            body {
                padding-top: 0;
            }
            
            .header h1 {
                text-align: center;
                font-size: clamp(1.1rem, 4vw, 1.4rem);
            }
            
            .user-info {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
            
            .user-info span {
                text-align: center;
                font-size: clamp(0.8rem, 3vw, 0.9rem);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .nav-menu {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.5rem;
            }
            
            .nav-item {
                margin: 0;
                padding: 0.6rem 1rem;
                border-radius: 4px;
            }
            
            .nav-item:hover, .nav-item.active {
                border-right: none;
                border-bottom: 4px solid var(--doré-foncé);
            }
            
            .btn-back {
                order: -1;
                margin-bottom: 0;
            }
            
        }

        @media (max-width: 480px) {
            .header {
                padding: 0.6rem 0.8rem;
            }
            
            .header h1 {
                font-size: 1.1rem;
                line-height: 1.3;
            }
            
            .user-info span {
                font-size: 0.8rem;
            }
            
            .btn-logout {
                padding: 0.3rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .btn-back {
                align-self: flex-start;
            }
            
            .card {
                padding: 1rem;
            }
            
            .nav-menu {
                flex-direction: column;
                align-items: center;
            }
        }

        /* Pour les très petits écrans */
        @media (max-width: 360px) {
            .header h1 {
                font-size: 1rem;
            }
            
            .user-info span {
                font-size: 0.75rem;
            }
            
            .btn-logout {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }
            
            .main-content {
                padding: 0.5rem;
            }
            
            .btn-back {
                padding: 0.5rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .btn-back .fas {
                font-size: 0.8rem;
            }
        }

        /* Empêcher le zoom sur iOS pour les inputs */
        @media (max-width: 480px) {
            input, select, textarea {
                font-size: 16px !important;
            }
        }

        /* Styles spécifiques pour le formulaire de profil */
        .profile-form hr {
            margin: 1.5rem 0;
            border: none;
            border-top: 1px solid #eee;
        }

        .profile-form h3 {
            margin-bottom: 1rem;
            color: var(--doré-foncé);
        }

        .profile-form small {
            color: #888;
            font-style: italic;
        }
    </style>
</head>
<body>
    
  <div class="main-content">
      <div class="page-header">
          <div class="page-header-top">
              <a href="index.php" class="btn btn-back">
                  <i class="fas fa-arrow-left"></i>
                  <span>Retour</span>
              </a>
              <h1 class="page-title">Mon Profil</h1>
          </div>
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
              <div>
                  <?php foreach ($errors as $error): ?>
                      <div><?= htmlspecialchars($error) ?></div>
                  <?php endforeach; ?>
              </div>
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
                          value="<?= htmlspecialchars($user['email']) ?>" disabled 
                          style="background-color: #f5f5f5; color: #888;">
                  <small>L'adresse email ne peut pas être modifiée</small>
              </div>
              
              <div class="form-group">
                  <label for="role">Rôle</label>
                  <input type="text" id="role" class="form-control" 
                          value="<?= htmlspecialchars(ucfirst($user['role'])) ?>" disabled 
                          style="background-color: #f5f5f5; color: #888;">
              </div>
              
              <hr>
              
              <h3>Changer le mot de passe</h3>
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