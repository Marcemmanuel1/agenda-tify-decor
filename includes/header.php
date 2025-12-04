<?php 
// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']);

// Récupérer l'URL actuelle
$currentUrl = $_SERVER['REQUEST_URI'];

// Définir les chemins où le retour doit s'afficher
$allowedPaths = [
    '/modules/agent/',
    '/modules/admin/'
];

// Vérifier si l'URL actuelle contient un des chemins autorisés
$showRetour = false;
foreach ($allowedPaths as $path) {
    if (strpos($currentUrl, $path) !== false) {
        $showRetour = true;
        break;
    }
}

// Déterminer le chemin de retour
$retourPath = '/index.php';

if (strpos($currentUrl, '/modules/admin/') !== false) {
    $retourPath = '/modules/admin/index.php';
} elseif (strpos($currentUrl, '/modules/agent/') !== false) {
    $retourPath = '/modules/agent/index.php';
}
?>
<style>
    .retour {
        top: 25px;
        left: 25px;
    }
    .retour a {
        color: var(--blanc);
        text-decoration: none;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        padding: 8px 20px;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.2);
        transition: .8s ease-in-out;
    }
    .retour a:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateX(-5px);
    }
</style>

<header class="header" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000; height: 70px;">
    <?php if ($showRetour): ?>
    <div class="retour">
        <a href="<?= $retourPath ?>"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
    <?php endif; ?>

    <h1>Agenda de Suivi des Rendez-vous</h1>

    <?php if ($isLoggedIn): ?>
    <div class="user-info">
        <span>Connecté en tant que: <?= htmlspecialchars($_SESSION['user_prenom'] . ' ' . $_SESSION['user_nom']) ?> (<?= ucfirst(str_replace('_', ' ', $_SESSION['user_role'])) ?>)</span>
        <a href="../../logout.php" class="btn-logout" style="text-decoration:none;">Déconnexion</a>
    </div>
    <?php endif; ?>
</header>
