<?php
// Inclure le fichier de base de données si ce n'est pas déjà fait
require_once __DIR__ . '/../config/database.php';

/**
 * Crée une nouvelle notification pour un utilisateur spécifique.
 * @param int $user_id L'ID de l'utilisateur destinataire.
 * @param string $message Le message de la notification.
 * @param string $lien Le lien vers la ressource associée.
 * @return bool True en cas de succès, False en cas d'échec.
 */
function createNotification($user_id, $message, $lien) {
    $db = getDB();
    try {
        $stmt = $db->prepare("INSERT INTO notification_planif (user_id, message, lien) VALUES (?, ?, ?)");
        return $stmt->execute([$user_id, $message, $lien]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la création de la notification : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les notifications non lues d'un utilisateur.
 * @param int $user_id L'ID de l'utilisateur.
 * @return array Un tableau des notifications non lues.
 */
function getUnreadNotifications($user_id) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT id, message, lien, created_at FROM notification_planif WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des notifications : " . $e->getMessage());
        return [];
    }
}

/**
 * Marque une notification comme lue.
 * @param int $notification_id L'ID de la notification à marquer.
 * @return bool True en cas de succès, False en cas d'échec.
 */
function markNotificationAsRead($notification_id) {
    $db = getDB();
    try {
        $stmt = $db->prepare("UPDATE notification_planif SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$notification_id]);
    } catch (PDOException $e) {
        error_log("Erreur lors du marquage de la notification : " . $e->getMessage());
        return false;
    }
}

/**
 * Marque toutes les notifications d'un utilisateur comme lues.
 * @param int $user_id L'ID de l'utilisateur.
 * @return bool True en cas de succès, False en cas d'échec.
 */
function markAllNotificationsAsRead($user_id) {
    $db = getDB();
    try {
        $stmt = $db->prepare("UPDATE notification_planif SET is_read = 1 WHERE user_id = ?");
        return $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Erreur lors du marquage de toutes les notifications : " . $e->getMessage());
        return false;
    }
}