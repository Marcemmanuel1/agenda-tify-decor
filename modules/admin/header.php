<?php 
$currentPage = basename($_SERVER['PHP_SELF']); 
?>

<div class="sidebar">
    <ul class="nav-menu">
        <li class="nav-item <?= ($currentPage == 'home.php') ? 'active' : '' ?>">
            <a href="home.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'liste_rdv.php') ? 'active' : '' ?>">
            <a href="liste_rdv.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-list"></i> Liste des rendez-vous
            </a>
        </li>
       <!-- <li class="nav-item <?= ($currentPage == 'calendrier.php') ? 'active' : '' ?>">
            <a href="calendrier.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-calendar-alt"></i> Calendrier
            </a>
        </li> -->
        <li class="nav-item <?= ($currentPage == 'profile.php') ? 'active' : '' ?>">
            <a href="profile.php" style="text-decoration: none; color: inherit;">
                <i class="fa-solid fa-user"></i> Profile
            </a>
        </li>

        
    </ul>
</div>
