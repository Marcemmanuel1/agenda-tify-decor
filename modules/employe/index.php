<?php
require_once '../../config/db_connect.php';
require_once '../../includes/auth.php'; 
require_once '../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
redirectIfNotLoggedIn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - <?= defined('APP_NAME') ? APP_NAME : 'Agenda Rendez-vous' ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
  
    <?php include '../../includes/headergeneral.php'; ?>
    <div class="dashboard-container">
        <!-- Section de bienvenue -->
        <div class="welcome-section animate-in">
            <h1 class="welcome-title">Bienvenue sur votre tableau de bord</h1>
            <p class="welcome-subtitle">Gérez efficacement vos activités quotidiennes</p>
        </div>

        <!-- Grille de navigation principale -->
        <div class="navigation-grid">
            <a href="badge/index.php" class="nav-card animate-in" style="animation-delay: 0.1s">
              <div class="nav-icon">
                  <i class="fas fa-fingerprint"></i>
              </div>
              <h3 class="nav-title">Badge</h3>
              <p class="nav-description">
                  Marquez votre présence en cliquant ici
              </p>
            </a>

            <!-- section pour les congés -->
            <a href="conges/" class="nav-card animate-in" style="animation-delay: 0.2s">
                <div class="nav-icon">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <h3 class="nav-title">Congés</h3>
                <p class="nav-description">
                    Gérer vos demandes de congés et absences
                </p>
            </a>

            <a href="profile.php" class="nav-card animate-in" style="animation-delay: 0.3s">
                <div class="nav-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h3 class="nav-title">Profile</h3>
            </a>
        </div>
    </div>
</body>
</html>