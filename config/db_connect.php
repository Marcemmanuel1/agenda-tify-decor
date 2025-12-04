<?php
// Configuration de la base de données
$db_host = 'localhost';
$db_name = 'agenda_rdv';
$db_user = 'root';
$db_pass = '';

try {
    // Crée l'objet PDO
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    // Configure les options d'erreur pour les requêtes
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Affiche un message d'erreur si la connexion échoue
    die('Erreur de connexion à la base de données: ' . $e->getMessage());
}
?>