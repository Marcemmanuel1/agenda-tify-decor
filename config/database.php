<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'agenda_rdv');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER, 
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die('Erreur de connexion à la base de données: ' . $e->getMessage());
        }
    }
    
    return $db;
}
?>