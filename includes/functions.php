<?php
/**
 * Fonctions utilitaires pour l'application
 */

/**
 * Valider un numéro de téléphone français
 */
function validatePhoneNumber($phone) {
    // Supprimer tous les caractères non numériques
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Vérifier la longueur (10 chiffres pour la France)
    if (strlen($phone) !== 10) {
        return false;
    }
    
    // Vérifier que le numéro commence par 0
    if (substr($phone, 0, 1) !== '0') {
        return false;
    }
    
    // Vérifier que le deuxième chiffre est entre 1 et 9
    $secondDigit = substr($phone, 1, 1);
    if ($secondDigit < 1 || $secondDigit > 9) {
        return false;
    }
    
    return true;
}

/**
 * Formater un numéro de téléphone français
 */
function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) === 10) {
        return preg_replace('/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', '$1 $2 $3 $4 $5', $phone);
    }
    
    return $phone;
}

/**
 * Générer un mot de passe aléatoire
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * Obtenir le libellé du statut avec la classe CSS appropriée
 */
function getStatusBadge($status) {
    $classes = [
        'En attente' => 'badge-warning',
        'Effectué' => 'badge-success',
        'Annulé' => 'badge-danger',
        'Modifié' => 'badge-info'
    ];
    
    $class = $classes[$status] ?? 'badge-secondary';
    
    return '<span class="badge ' . $class . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Rediriger avec un message flash
 */
function redirectWithMessage($url, $type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
    
    header('Location: ' . $url);
    exit();
}

/**
 * Afficher un message flash s'il existe
 */
function displayFlashMessage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        echo '<div class="alert ' . $message['type'] . '">' . htmlspecialchars($message['message']) . '</div>';
        unset($_SESSION['flash_message']);
    }
}

/**
 * Valider une date au format français
 */
function validateFrenchDate($date) {
    $pattern = '/^(0[1-9]|[1-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\/[0-9]{4}$/';
    
    if (!preg_match($pattern, $date)) {
        return false;
    }
    
    list($day, $month, $year) = explode('/', $date);
    
    return checkdate($month, $day, $year);
}

/**
 * Convertir une date française en format MySQL
 */
function frenchDateToMySQL($date) {
    if (!validateFrenchDate($date)) {
        return false;
    }
    
    list($day, $month, $year) = explode('/', $date);
    
    return $year . '-' . $month . '-' . $day;
}

/**
 * Convertir une date MySQL en format français
 */
function mysqlDateToFrench($date) {
    return date('d/m/Y', strtotime($date));
}

/**
 * Échapper les données pour l'affichage HTML
 */
function escape($data) {
    if (is_array($data)) {
        return array_map('escape', $data);
    }
    
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function logActivity($userId, $action, $details = null) {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO historique (user_id, action, details, created_at)
        VALUES (:user_id, :action, :details, NOW())
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':details' => $details
    ]);
}