<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

    // Vérifier si l'utilisateur est connecté et a le bon statut
    redirectIfNotLoggedIn();

    // Vérification supplémentaire du statut utilisateur
    checkUserStatus();

if (!isPlanificateur() && !isSuperAdmin()) {
    header('Location: ../agent/');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier - Agenda Rendez-vous</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/dayjs@1.8.21/dayjs.min.js"></script>
    <script src="https://unpkg.com/dayjs@1.8.21/locale/fr.js"></script>
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Calendrier des Rendez-vous</h1>
            <a href="add_rdv.php" style="text-decoration:none;" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau rendez-vous</a>
        </div>
        
        <div id="calendar"></div>
    </div>
    
    <script>
        // Définir le rôle utilisateur pour le JS
        const userRole = '<?= $_SESSION['user_role'] ?>';
    </script>
    <script src="../../js/calendar.js"></script>
</body>
</html>