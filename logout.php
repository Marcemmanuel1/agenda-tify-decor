<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

session_start();

// Enregistrer la déconnexion dans l'historique avant de détruire la session
if (isset($_SESSION['user_id'])) {
    logActivity(
        $_SESSION['user_id'], 
        'deconnexion', 
        'Déconnexion du système depuis l\'adresse IP: ' . $_SERVER['REMOTE_ADDR']
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header('Location: login.php');
exit();