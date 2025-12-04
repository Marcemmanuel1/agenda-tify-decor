<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Vérifier si l'utilisateur est connecté et a le bon statut
redirectIfNotLoggedIn();

if (!isSuperAdmin()) {
    header('Location: ../planificateur/');
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
    
<body>
    <?php include '../../includes/header.php'; ?>
    
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Calendrier Global</h1>
        </div>
        
        <div id="calendar"></div>
    </div>
    
    <script>
        // Définir le rôle utilisateur pour le JS
        const userRole = '<?= $_SESSION['user_role'] ?>';
    </script>
    <script src="https://unpkg.com/dayjs@1.8.21/dayjs.min.js"></script>
    <script src="https://unpkg.com/dayjs@1.8.21/locale/fr.js"></script>
</head>
    <script src="../../js/calendar.js"></script>
</body>
</html>