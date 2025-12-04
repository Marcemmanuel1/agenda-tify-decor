<?php 
$currentPage = basename($_SERVER['PHP_SELF']); 
?>

<div class="sidebar">
    <ul class="nav-menu">
        <li class="nav-item <?= ($currentPage == 'index.php') ? 'active' : '' ?>">
            <a href="index.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'liste-rdv.php') ? 'active' : '' ?>">
            <a href="liste-rdv.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-list"></i> Liste des rendez-vous
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'gestion-utilisateur.php') ? 'active' : '' ?>">
            <a href="gestion-utilisateur.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-users"></i> Gestion utilisateurs
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'historique.php') ? 'active' : '' ?>">
            <a href="historique.php" style="text-decoration: none; color: inherit;">
                <i class="fa-solid fa-clock-rotate-left"></i> Historique
            </a>
        </li>
        <li class="nav-item <?= ($currentPage == 'badge/index.php') ? 'active' : '' ?>">
            <a href="badge/index.php" style="text-decoration: none; color: inherit;">
                <i class="fa-solid fas fa-fingerprint"></i> badgage
            </a>
        </li>
        <li class="nav-item <?= ($currentPage == 'conges/') ? 'active' : '' ?>">
            <a href="conges/" style="text-decoration: none; color: inherit;">
               <i class="fas fa-umbrella-beach"></i> Congés
            </a>
        </li>
        <li class="nav-item <?= ($currentPage == 'profile.php') ? 'active' : '' ?>">
            <a href="profile.php" style="text-decoration: none; color: inherit;">
                <i class="fa-solid fa-user"></i> Profile
            </a>
        </li>
    </ul>
</div>
