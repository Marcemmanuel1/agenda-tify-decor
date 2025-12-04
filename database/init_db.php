<?php
// Script d'initialisation de la base de données

// Configuration de la base de données
$host = 'localhost';
$dbname = 'agenda_rdv';
$user = 'root';
$pass = '';

try {
    // Connexion à MySQL sans base de données
    $pdo = new PDO("mysql:host=$host;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Création de la base de données
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $dbname");
    
    echo "Base de données créée avec succès.<br>";
    
    // Table des utilisateurs
    $pdo->exec("
        CREATE TABLE users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            role ENUM('superadmin', 'planificateur', 'agent') NOT NULL,
            email VARCHAR(100),
            telephone VARCHAR(20),
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
            actif BOOLEAN DEFAULT TRUE
        )
    ");
    echo "Table 'users' créée avec succès.<br>";
    
    // Table des clients
    $pdo->exec("
        CREATE TABLE clients (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            genre ENUM('M', 'F', 'Autre') NOT NULL,
            commune VARCHAR(100) NOT NULL,
            telephone VARCHAR(20) NOT NULL,
            date_contact DATETIME,
            created_by INT,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    echo "Table 'clients' créée avec succès.<br>";
    
    // Table des rendez-vous
    $pdo->exec("
        CREATE TABLE rendezvous (
            id INT PRIMARY KEY AUTO_INCREMENT,
            client_id INT NOT NULL,
            date_rdv DATETIME NOT NULL,
            motif TEXT NOT NULL,
            statut ENUM('planifie', 'effectue', 'annule', 'modifie') DEFAULT 'planifie',
            agent_id INT NOT NULL,
            planificateur_id INT NOT NULL,
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_modification DATETIME ON UPDATE CURRENT_TIMESTAMP,
            notes TEXT,
            paiement ENUM('paye', 'impaye') DEFAULT 'impaye',
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (planificateur_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "Table 'rendezvous' créée avec succès.<br>";
    
    // Table des notifications
    $pdo->exec("
        CREATE TABLE notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            lien VARCHAR(255),
            lue BOOLEAN DEFAULT FALSE,
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "Table 'notifications' créée avec succès.<br>";
    
    // Table des logs de suppression
    $pdo->exec("
        CREATE TABLE logs_suppression (
            id INT PRIMARY KEY AUTO_INCREMENT,
            rdv_id INT,
            motif TEXT NOT NULL,
            supprime_par INT NOT NULL,
            date_suppression DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supprime_par) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "Table 'logs_suppression' créée avec succès.<br>";
    
    // Insertion d'un super administrateur par défaut
    $password = password_hash('password', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (username, password, nom, prenom, role, email) 
        VALUES ('admin', '$password', 'Admin', 'Super', 'superadmin', 'admin@example.com')
    ");
    echo "Utilisateur admin créé avec succès.<br>";
    
    // Insertion de quelques données de test
    // Ajouter un planificateur
    $pdo->exec("
        INSERT INTO users (username, password, nom, prenom, role, email) 
        VALUES ('planificateur', '$password', 'Dupont', 'Marie', 'planificateur', 'marie.dupont@example.com')
    ");
    
    // Ajouter un agent
    $pdo->exec("
        INSERT INTO users (username, password, nom, prenom, role, email) 
        VALUES ('agent', '$password', 'Martin', 'Pierre', 'agent', 'pierre.martin@example.com')
    ");
    
    // Ajouter un client
    $pdo->exec("
        INSERT INTO clients (nom, prenom, genre, commune, telephone, date_contact) 
        VALUES ('Dubois', 'Sophie', 'F', 'Paris', '0123456789', NOW())
    ");
    
    // Ajouter un rendez-vous
    $pdo->exec("
        INSERT INTO rendezvous (client_id, date_rdv, motif, agent_id, planificateur_id, paiement) 
        VALUES (1, NOW() + INTERVAL 7 DAY, 'Premier rendez-vous', 3, 2, 'impaye')
    ");
    
    echo "Données de test insérées avec succès.<br>";
    echo "<br><strong>Initialisation terminée avec succès!</strong><br>";
    echo "Vous pouvez maintenant vous connecter avec:<br>";
    echo "Super Admin: admin / password<br>";
    echo "Planificateur: planificateur / password<br>";
    echo "Agent: agent / password<br>";
    
} catch (PDOException $e) {
    die("Erreur lors de l'initialisation: " . $e->getMessage());
}