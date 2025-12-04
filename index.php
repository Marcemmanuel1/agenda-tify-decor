<?php
require_once 'includes/auth.php';

// Si l'utilisateur est déjà connecté, rediriger vers le tableau de bord approprié
if (isLoggedIn()) {
    $role = getUserRole();
    
    if ($role === 'super_admin') {
        header('Location: modules/admin/');
    } elseif ($role === 'planificateur') {
        header('Location: modules/planificateur/');
    } else {
        header('Location: modules/agent/');
    }
    
    exit();
} else {
    // Rediriger vers la page de connexion
    header('Location: login.php');
    exit();
}
?>