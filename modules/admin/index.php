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
    <title>Tableau de bord - <?= defined('APP_NAME') ? APP_NAME : 'General' ?></title>
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
        }

        body {
            background: linear-gradient(135deg, var(--ivoire) 0%, var(--blanc) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            margin-top: 5rem;
        }

        .welcome-section {
            text-align: center;
            margin-bottom: 3rem;
        }

        .welcome-title {
            color: var(--gris-anthracite);
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: var(--doré-foncé);
            font-size: 1.2rem;
            font-weight: 400;
        }

        .navigation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .nav-card {
            background: var(--blanc);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            text-decoration: none;
            color: var(--gris-anthracite);
            box-shadow: 0 10px 30px var(--ombre);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .nav-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--doré-foncé), var(--doré-clair));
        }

        .nav-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px var(--ombre);
            border-color: var(--doré-clair);
        }

        .nav-card:hover .nav-icon {
            transform: scale(1.1);
            color: var(--doré-foncé);
        }

        .nav-icon {
            font-size: 4rem;
            color: var(--doré-clair);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .nav-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--gris-anthracite);
        }

        .nav-description {
            color: var(--gris-anthracite);
            opacity: 0.8;
            line-height: 1.6;
            font-size: 1.1rem;
        }

        .stats-section {
            background: var(--blanc);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px var(--ombre);
            margin-bottom: 2rem;
        }

        .stats-title {
            color: var(--gris-anthracite);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: var(--ivoire);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            border-left: 4px solid var(--vert-sage);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--doré-foncé);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gris-anthracite);
            font-size: 1rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .navigation-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-card {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
  
    <?php include '../../includes/headergeneral.php'; ?>
    <div class="dashboard-container">
        <!-- Section de bienvenue -->
        <div class="welcome-section">
            <h1 class="welcome-title">Bienvenue sur votre tableau de bord</h1>
            <p class="welcome-subtitle">Gérez efficacement vos activités quotidiennes</p>
        </div>

        <!-- Grille de navigation principale -->
        <div class="navigation-grid">
            <!-- section pour l'agenda -->
            <a href="home.php" class="nav-card">
                <div class="nav-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="nav-title">Agenda</h3>
            </a>
            <!-- section pour le chauffeur -->
            <a href="chauffeur/index.php" class="nav-card">
                <div class="nav-icon">
                    <i class="fas fa-car"></i>
                </div>
                <h3 class="nav-title">Chauffeurs</h3>
            </a>
            <!-- section pour les badge -->
            <a href="badge/index.php" class="nav-card animate-in" style="animation-delay: 0.1s">
              <div class="nav-icon">
                  <i class="fas fa-fingerprint"></i>
              </div>
              <h3 class="nav-title">Badge</h3>
              <p class="nav-description">
                  Clicker ici pour voir la liste de presence
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
        </div>
    </div>
 
    <script>
        // Animation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.nav-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>