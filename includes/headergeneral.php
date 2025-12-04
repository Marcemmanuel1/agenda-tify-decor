<?php
// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']);
?>

<header class="header" style=" position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;">
    <h1>Tableau de bord General de Tify decor</h1>
    <?php if ($isLoggedIn): ?>
    <div class="user-info">
        <span>Connecté en tant que: <?= htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']) ?> (<?= ucfirst(str_replace('_', ' ', $_SESSION['user_role'])) ?>)</span>
        <a href="../../logout.php" class="btn-logout" style="text-decoration:none;">Déconnexion</a>
    </div>
    <?php endif; ?>
</header>