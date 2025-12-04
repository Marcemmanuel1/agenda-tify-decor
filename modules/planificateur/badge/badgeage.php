<?php
// badgeage.php

// Afficher toutes les erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once '../../../config/db_connect.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
redirectIfNotLoggedIn();

// Définir le fuseau horaire de la Côte d'Ivoire
date_default_timezone_set('Africa/Abidjan');

header('Content-Type: application/json');

class BadgeageManager {
    private $pdo;
    private $userId;
    
    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    // Vérifier les badgeages existants pour aujourd'hui
    public function checkTodayBadgeages() {
        $today = date('Y-m-d');
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT type_badgeage, heure_badgeage, recorded_datetime, overtime_hours 
                FROM badgeages_collab 
                WHERE user_id = ? AND date_badgeage = ?
                ORDER BY recorded_datetime DESC
            ");
            $stmt->execute([$this->userId, $today]);
            $badgeages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [
                'arrival' => null,
                'departure' => null
            ];
            
            foreach ($badgeages as $badgeage) {
                if ($badgeage['type_badgeage'] === 'arrival' && !$result['arrival']) {
                    $result['arrival'] = [
                        'time' => $badgeage['heure_badgeage'],
                        'recorded_time' => date('H:i', strtotime($badgeage['recorded_datetime']))
                    ];
                } elseif ($badgeage['type_badgeage'] === 'departure' && !$result['departure']) {
                    $result['departure'] = [
                        'recorded_time' => date('H:i', strtotime($badgeage['recorded_datetime'])),
                        'overtime' => floatval($badgeage['overtime_hours'])
                    ];
                }
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la vérification des badgeages: " . $e->getMessage());
            return ['arrival' => null, 'departure' => null];
        }
    }
    
    // Enregistrer un badgeage d'arrivée
    public function badgeArrival() {
        $today = date('Y-m-d');
        
        // Vérifier si l'utilisateur a déjà badgé aujourd'hui
        $existing = $this->checkTodayBadgeages();
        if ($existing['arrival']) {
            return [
                'success' => false,
                'message' => 'Vous avez déjà badgé votre arrivée aujourd\'hui'
            ];
        }
        
        $currentTime = date('H:i:s');
        $recordedDatetime = date('Y-m-d H:i:s');
        $ipAddress = $this->getClientIp();
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO badgeages_collab 
                (user_id, type_badgeage, date_badgeage, heure_badgeage, recorded_datetime, ip_address) 
                VALUES (?, 'arrival', ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->userId,
                $today,
                $currentTime,
                $recordedDatetime,
                $ipAddress
            ]);
            
            return [
                'success' => true,
                'message' => 'Arrivée enregistrée avec succès',
                'time' => $currentTime
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors du badgeage d'arrivée: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de l\'arrivée'
            ];
        }
    }
    
    // Enregistrer un badgeage de départ
    public function badgeDeparture($observations = '') {
        $today = date('Y-m-d');
        
        // Vérifier si l'utilisateur a déjà badgé son départ aujourd'hui
        $existing = $this->checkTodayBadgeages();
        if ($existing['departure']) {
            return [
                'success' => false,
                'message' => 'Vous avez déjà badgé votre départ aujourd\'hui'
            ];
        }
        
        // Vérifier si l'utilisateur a badgé son arrivée aujourd'hui
        if (!$existing['arrival']) {
            return [
                'success' => false,
                'message' => 'Vous devez d\'abord badger votre arrivée'
            ];
        }
        
        $currentTime = date('H:i:s');
        $recordedDatetime = date('Y-m-d H:i:s');
        $ipAddress = $this->getClientIp();
        
        // Calculer les heures supplémentaires (heures travaillées au-delà de 8 heures)
        $overtime = $this->calculateOvertime($existing['arrival']['time'], $currentTime);
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO badgeages_collab 
                (user_id, type_badgeage, date_badgeage, heure_badgeage, recorded_datetime, observations, overtime_hours, ip_address) 
                VALUES (?, 'departure', ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->userId,
                $today,
                $currentTime,
                $recordedDatetime,
                $observations,
                $overtime,
                $ipAddress
            ]);
            
            return [
                'success' => true,
                'message' => 'Départ enregistré avec succès' . ($overtime > 0 ? " (+{$overtime}h supplémentaires)" : ""),
                'time' => $currentTime,
                'overtime' => $overtime
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors du badgeage de départ: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du départ'
            ];
        }
    }
    
    // Calculer les heures supplémentaires
    private function calculateOvertime($arrivalTime, $departureTime) {
        // Heure de travail normale : 8 heures
        $normalWorkHours = 8;
        
        // Convertir les heures en secondes
        $arrivalSeconds = strtotime($arrivalTime);
        $departureSeconds = strtotime($departureTime);
        
        // Calculer le temps travaillé en heures
        $workedHours = ($departureSeconds - $arrivalSeconds) / 3600;
        
        // Soustraire la pause déjeuner (1 heure) si le temps travaillé est supérieur à 5 heures
        if ($workedHours > 5) {
            $workedHours -= 1;
        }
        
        // Calculer les heures supplémentaires
        $overtime = max(0, $workedHours - $normalWorkHours);
        
        // Arrondir à 2 décimales
        return round($overtime, 2);
    }
    
    // Obtenir l'adresse IP du client
    private function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}

// Traitement des requêtes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $badgeageManager = new BadgeageManager($pdo, $_SESSION['user_id']);
    
    switch ($action) {
        case 'check':
            $badgeages = $badgeageManager->checkTodayBadgeages();
            echo json_encode([
                'success' => true,
                'arrival' => $badgeages['arrival'],
                'departure' => $badgeages['departure']
            ]);
            break;
            
        case 'arrival':
            $result = $badgeageManager->badgeArrival();
            echo json_encode($result);
            break;
            
        case 'departure':
            $observations = $input['observations'] ?? '';
            $result = $badgeageManager->badgeDeparture($observations);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue'
            ]);
            break;
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
}
?>