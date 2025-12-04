<?php
// badgeage.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once '../../../config/db_connect.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

redirectIfNotLoggedIn();
date_default_timezone_set('Africa/Abidjan');
header('Content-Type: application/json');

class BadgeageManager {
    private $pdo;
    private $userId;
    
    // Constantes pour les calculs
    const NORMAL_WORK_HOURS = 9.5; // 9h30 = 9.5 heures
    const MAX_ALLOWED_PAUSE = 2.0; // 2 heures maximum de pause autorisées
    
    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    public function checkTodayBadgeages() {
        $today = date('Y-m-d');
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT type_badgeage, heure_badgeage, recorded_datetime, overtime_hours 
                FROM badgeages_collab 
                WHERE user_id = ? AND date_badgeage = ? AND type_badgeage IN ('arrival', 'departure')
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
    
    public function getTodayPauses() {
        $today = date('Y-m-d');
        
        try {
            // Récupérer toutes les pauses d'aujourd'hui
            $stmt = $this->pdo->prepare("
                SELECT 
                    p1.recorded_datetime as start_time,
                    p2.recorded_datetime as end_time,
                    TIMEDIFF(p2.recorded_datetime, p1.recorded_datetime) as duration
                FROM badgeages_collab p1
                LEFT JOIN badgeages_collab p2 ON p1.pause_pair_id = p2.id
                WHERE p1.user_id = ? 
                AND DATE(p1.recorded_datetime) = ?
                AND p1.type_badgeage = 'pause_start'
                ORDER BY p1.recorded_datetime DESC
            ");
            $stmt->execute([$this->userId, $today]);
            $pauses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Vérifier s'il y a une pause en cours
            $stmt = $this->pdo->prepare("
                SELECT recorded_datetime as start_time
                FROM badgeages_collab 
                WHERE user_id = ? 
                AND DATE(recorded_datetime) = ?
                AND type_badgeage = 'pause_start'
                AND pause_pair_id IS NULL
                ORDER BY recorded_datetime DESC
                LIMIT 1
            ");
            $stmt->execute([$this->userId, $today]);
            $currentPause = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Formater les données
            $formattedPauses = [];
            foreach ($pauses as $pause) {
                $formattedPauses[] = [
                    'start_time' => date('H:i', strtotime($pause['start_time'])),
                    'end_time' => $pause['end_time'] ? date('H:i', strtotime($pause['end_time'])) : null,
                    'duration' => $pause['duration'] ?: null
                ];
            }
            
            return [
                'pauses' => $formattedPauses,
                'current_pause' => $currentPause ? [
                    'start_time' => date('H:i', strtotime($currentPause['start_time']))
                ] : null
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des pauses: " . $e->getMessage());
            return ['pauses' => [], 'current_pause' => null];
        }
    }
    
    public function badgeArrival() {
        $today = date('Y-m-d');
        
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
    
    public function badgeDeparture($observations = '') {
        $today = date('Y-m-d');
        
        $existing = $this->checkTodayBadgeages();
        if ($existing['departure']) {
            return [
                'success' => false,
                'message' => 'Vous avez déjà badgé votre départ aujourd\'hui'
            ];
        }
        
        if (!$existing['arrival']) {
            return [
                'success' => false,
                'message' => 'Vous devez d\'abord badger votre arrivée'
            ];
        }
        
        $currentTime = date('H:i:s');
        $recordedDatetime = date('Y-m-d H:i:s');
        $ipAddress = $this->getClientIp();
        
        // Vérifier s'il y a une pause en cours
        $currentPause = $this->getTodayPauses();
        if ($currentPause['current_pause']) {
            return [
                'success' => false,
                'message' => 'Vous devez d\'abord terminer votre pause en cours'
            ];
        }
        
        // Calculer les heures supplémentaires avec la nouvelle logique
        $overtimeData = $this->calculateOvertimeWithPauseAdjustment($existing['arrival']['time'], $currentTime);
        $overtime = $overtimeData['overtime'];
        $pauseAdjustment = $overtimeData['pause_adjustment'];
        
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
            
            // Message personnalisé selon l'ajustement des pauses
            $message = 'Départ enregistré avec succès';
            if ($overtime > 0) {
                $message .= " (+{$overtime}h supplémentaires)";
            }
            if ($pauseAdjustment > 0) {
                $message .= " - Ajustement pause: -{$pauseAdjustment}h";
            }
            
            return [
                'success' => true,
                'message' => $message,
                'time' => $currentTime,
                'overtime' => $overtime,
                'pause_adjustment' => $pauseAdjustment
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors du badgeage de départ: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du départ'
            ];
        }
    }
    
    public function badgePauseStart() {
        $today = date('Y-m-d');
        
        // Vérifier si l'utilisateur a badgé son arrivée
        $existing = $this->checkTodayBadgeages();
        if (!$existing['arrival']) {
            return [
                'success' => false,
                'message' => 'Vous devez d\'abord badger votre arrivée'
            ];
        }
        
        // Vérifier s'il y a déjà une pause en cours
        $currentPauses = $this->getTodayPauses();
        if ($currentPauses['current_pause']) {
            return [
                'success' => false,
                'message' => 'Vous avez déjà une pause en cours'
            ];
        }
        
        $currentTime = date('H:i:s');
        $recordedDatetime = date('Y-m-d H:i:s');
        $ipAddress = $this->getClientIp();
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO badgeages_collab 
                (user_id, type_badgeage, date_badgeage, heure_badgeage, recorded_datetime, ip_address) 
                VALUES (?, 'pause_start', ?, ?, ?, ?)
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
                'message' => 'Début de pause enregistré avec succès',
                'time' => $currentTime
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors du badgeage de début de pause: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du début de pause'
            ];
        }
    }
    
    public function badgePauseEnd() {
        $today = date('Y-m-d');
        
        // Vérifier s'il y a une pause en cours
        $currentPauses = $this->getTodayPauses();
        if (!$currentPauses['current_pause']) {
            return [
                'success' => false,
                'message' => 'Aucune pause en cours'
            ];
        }
        
        $currentTime = date('H:i:s');
        $recordedDatetime = date('Y-m-d H:i:s');
        $ipAddress = $this->getClientIp();
        
        try {
            // Récupérer l'ID de la pause en cours
            $stmt = $this->pdo->prepare("
                SELECT id 
                FROM badgeages_collab 
                WHERE user_id = ? 
                AND DATE(recorded_datetime) = ?
                AND type_badgeage = 'pause_start'
                AND pause_pair_id IS NULL
                ORDER BY recorded_datetime DESC
                LIMIT 1
            ");
            $stmt->execute([$this->userId, $today]);
            $pauseStart = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pauseStart) {
                return [
                    'success' => false,
                    'message' => 'Aucune pause en cours trouvée'
                ];
            }
            
            // Insérer la fin de pause
            $stmt = $this->pdo->prepare("
                INSERT INTO badgeages_collab 
                (user_id, type_badgeage, date_badgeage, heure_badgeage, recorded_datetime, ip_address, pause_pair_id) 
                VALUES (?, 'pause_end', ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->userId,
                $today,
                $currentTime,
                $recordedDatetime,
                $ipAddress,
                $pauseStart['id']
            ]);
            
            // Mettre à jour la pause start avec l'ID de la fin
            $lastId = $this->pdo->lastInsertId();
            $stmt = $this->pdo->prepare("
                UPDATE badgeages_collab 
                SET pause_pair_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$lastId, $pauseStart['id']]);
            
            return [
                'success' => true,
                'message' => 'Fin de pause enregistrée avec succès',
                'time' => $currentTime
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur lors du badgeage de fin de pause: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de la fin de pause'
            ];
        }
    }
    
    /**
     * Nouvelle méthode pour calculer les heures supplémentaires avec ajustement des pauses
     * Si le temps de pause total dépasse 2h, l'excédent est soustrait des heures supplémentaires
     */
    private function calculateOvertimeWithPauseAdjustment($arrivalTime, $departureTime) {
        // Calcul du temps travaillé brut
        $arrivalSeconds = strtotime($arrivalTime);
        $departureSeconds = strtotime($departureTime);
        $workedHours = ($departureSeconds - $arrivalSeconds) / 3600;
        
        // Récupérer le temps total des pauses
        $totalPauseTime = $this->getTotalPauseTime();
        
        // Temps de travail effectif (sans les pauses)
        $effectiveWorkHours = $workedHours - $totalPauseTime;
        
        // Calcul des heures supplémentaires brutes
        $rawOvertime = max(0, $effectiveWorkHours - self::NORMAL_WORK_HOURS);
        
        // Calcul de l'excédent de pause (au-delà de 2h)
        $pauseExcess = max(0, $totalPauseTime - self::MAX_ALLOWED_PAUSE);
        
        // Ajustement des heures supplémentaires
        $adjustedOvertime = max(0, $rawOvertime - $pauseExcess);
        
        return [
            'overtime' => round($adjustedOvertime, 2),
            'pause_adjustment' => round($pauseExcess, 2),
            'raw_overtime' => round($rawOvertime, 2),
            'total_pause_time' => round($totalPauseTime, 2),
            'effective_work_hours' => round($effectiveWorkHours, 2)
        ];
    }
    
    /**
     * Ancienne méthode conservée pour compatibilité
     */
    private function calculateOvertime($arrivalTime, $departureTime) {
        $overtimeData = $this->calculateOvertimeWithPauseAdjustment($arrivalTime, $departureTime);
        return $overtimeData['overtime'];
    }
    
    private function getTotalPauseTime() {
        $today = date('Y-m-d');
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT SUM(TIMEDIFF(p2.recorded_datetime, p1.recorded_datetime)) as total_pause_time
                FROM badgeages_collab p1
                JOIN badgeages_collab p2 ON p1.pause_pair_id = p2.id
                WHERE p1.user_id = ? 
                AND DATE(p1.recorded_datetime) = ?
                AND p1.type_badgeage = 'pause_start'
                AND p2.type_badgeage = 'pause_end'
            ");
            $stmt->execute([$this->userId, $today]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['total_pause_time']) {
                // Convertir le temps total en heures
                $timeParts = explode(':', $result['total_pause_time']);
                $hours = (int)$timeParts[0];
                $minutes = (int)$timeParts[1];
                $seconds = (int)$timeParts[2];
                
                return $hours + ($minutes / 60) + ($seconds / 3600);
            }
            
            return 0;
            
        } catch (PDOException $e) {
            error_log("Erreur lors du calcul du temps de pause: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Méthode pour obtenir des statistiques détaillées (utile pour le débogage ou les rapports)
     */
    public function getWorkStatistics($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $existing = $this->checkTodayBadgeages();
        if (!$existing['arrival']) {
            return null;
        }
        
        $departureTime = $existing['departure'] ? $existing['departure']['recorded_time'] : date('H:i:s');
        
        return $this->calculateOvertimeWithPauseAdjustment(
            $existing['arrival']['time'], 
            $departureTime
        );
    }
    
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
            $statistics = $badgeageManager->getWorkStatistics();
            
            echo json_encode([
                'success' => true,
                'arrival' => $badgeages['arrival'],
                'departure' => $badgeages['departure'],
                'statistics' => $statistics
            ]);
            break;
            
        case 'get_pauses':
            $pauses = $badgeageManager->getTodayPauses();
            echo json_encode([
                'success' => true,
                'pauses' => $pauses['pauses'],
                'current_pause' => $pauses['current_pause']
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
            
        case 'pause_start':
            $result = $badgeageManager->badgePauseStart();
            echo json_encode($result);
            break;
            
        case 'pause_end':
            $result = $badgeageManager->badgePauseEnd();
            echo json_encode($result);
            break;
            
        case 'get_statistics':
            $date = $input['date'] ?? null;
            $statistics = $badgeageManager->getWorkStatistics($date);
            echo json_encode([
                'success' => true,
                'statistics' => $statistics
            ]);
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