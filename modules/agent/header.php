<?php 
$currentPage = basename($_SERVER['PHP_SELF']); 
?>

<div class="sidebar">
    <ul class="nav-menu">
        
        <li class="nav-item <?= ($currentPage == 'home.php') ? 'active' : '' ?>">
            <a href="home.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'calendrier.php') ? 'active' : '' ?>">
            <a href="calendrier.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-calendar-alt"></i> Calendrier
            </a>
        </li>
        

        <li class="nav-item <?= ($currentPage == 'rendezvous.php') ? 'active' : '' ?>">
            <a href="rendezvous.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-list"></i> Rendez-vous en attente
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'rendezvousEffectuer.php') ? 'active' : '' ?>">
            <a href="rendezvousEffectuer.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-list"></i> Rendez-vous effectués
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'rendezvousAnnuler.php') ? 'active' : '' ?>">
            <a href="rendezvousAnnuler.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-list"></i> Rendez-vous annulés
            </a>
        </li>
        
        <li class="nav-item <?= ($currentPage == 'profile.php') ? 'active' : '' ?>">
            <a href="profile.php" style="text-decoration: none; color: inherit;">
                <i class="fa-solid fa-user"></i> Profile
            </a>
        </li>
        <li class="nav-item <?= ($currentPage == 'badge/index.php') ? 'active' : '' ?>">
            <a href="badge/index.php" style="text-decoration: none; color: inherit;">
                <i class="fa-solid fas fa-fingerprint"></i> badgage
            </a>
        </li>
        <li class="nav-item <?= (isset($_GET['read_notifications']) ? 'active' : '') ?>">
            <a href="?read_notifications=1" style="text-decoration: none; color: inherit;" id="notifications-link">
                <i class="fas fa-bell"></i> Notifications <span id="notification-badge" class="notification-badge" style="display: none;">0</span>
            </a>
        </li>
    </ul>
</div>

<div id="notifications-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Notifications</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body" id="notifications-list">
            </div>
        <div class="modal-footer">
            <button id="mark-all-read" class="btn btn-secondary">Tout marquer comme lu</button>
        </div>
    </div>
</div>

<script>
// Fonction pour récupérer les notifications
function fetchNotifications() {
    fetch('../ajax/get_notifications.php')
        .then(response => response.json())
        .then(notifications => {
            // Filtrer uniquement les notifications non lues pour le badge
            const unreadNotifications = notifications.filter(notif => notif.lue == 0);
            updateNotificationBadge(unreadNotifications.length);
            
            // Mettre à jour le modal des notifications avec toutes les notifications reçues
            updateNotificationsModal(notifications);
        })
        .catch(error => console.error('Erreur:', error));
}

// Mettre à jour le badge de notifications
function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }
}

// Mettre à jour le modal des notifications
function updateNotificationsModal(notifications) {
    const container = document.getElementById('notifications-list');
    
    if (notifications.length === 0) {
        container.innerHTML = '<p class="no-notifications">Aucune notification</p>';
        return;
    }
    
    let html = '';
    notifications.forEach(notif => {
        html += `
            <a href="${notif.lien}" class="notification-item ${notif.lue == 1 ? 'read' : 'unread'}" data-id="${notif.id}" onclick="markAndRedirect(event, ${notif.id}, '${notif.lien}')">
                <div class="notification-content">
                    <p>${notif.message}</p>
                    <small>${new Date(notif.created_at).toLocaleString('fr-FR')}</small>
                </div>
                ${notif.lue == 0 ? '<button class="mark-read-btn" onclick="event.stopPropagation(); markAsRead(${notif.id})">✓</button>' : ''}
            </a>
        `;
    });
    container.innerHTML = html;
}

// Marquer une notification comme lue
function markAsRead(notificationId) {
    fetch('../ajax/mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: notificationId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            fetchNotifications();
        }
    });
}

// Marquer comme lu et rediriger
function markAndRedirect(event, notificationId, lien) {
    event.preventDefault();
    fetch('../ajax/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: notificationId })
    })
    .then(() => {
        window.location.href = lien;
    });
}

// Marquer toutes les notifications comme lues
document.getElementById('mark-all-read')?.addEventListener('click', function() {
    fetch('../ajax/mark_all_notifications_read.php', { method: 'POST' })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            fetchNotifications();
        }
    });
});

// Ouvrir le modal des notifications
document.getElementById('notifications-link')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('notifications-modal').style.display = 'block';
    fetchNotifications();
});

// Fermer le modal
document.querySelector('.close')?.addEventListener('click', function() {
    document.getElementById('notifications-modal').style.display = 'none';
});

// Fermer le modal si l'utilisateur clique en dehors
window.onclick = function(event) {
    const modal = document.getElementById('notifications-modal');
    if (event.target === modal) {
        modal.style.display = "none";
    }
}

// Vérifier les notifications toutes les 30 secondes
setInterval(fetchNotifications, 30000);

// Charger les notifications au démarrage
document.addEventListener('DOMContentLoaded', fetchNotifications);
</script>

<style>
/* Style pour la notification */
.notification-badge {
    background-color: #e74c3c;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 12px;
    margin-left: 5px;
    position: relative;
    top: -5px;
}

/* Styles pour le modal */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 0;
    border-radius: 8px;
    width: 400px;
    max-width: 90%;
}

.modal-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 15px;
    max-height: 250px; /* Hauteur ajustée pour afficher 3-4 notifications */
    overflow-y: auto;
}

.modal-footer {
    padding: 15px;
    border-top: 1px solid #eee;
    text-align: right;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

/* Styles pour les éléments de notification */
.notification-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 10px;
    border-bottom: 1px solid #f0f0f0;
    text-decoration: none;
    color: #333;
    transition: background-color 0.2s ease;
}

.notification-item:hover {
    background-color: #f0f0f0;
}

.notification-item.unread {
    font-weight: bold;
    background-color: #e8f5e9;
}

.notification-content {
    flex: 1;
}

.notification-content p {
    margin: 0 0 5px 0;
}

.notification-content small {
    color: #6c757d;
}

.mark-read-btn {
    background: none;
    border: none;
    color: #28a745;
    cursor: pointer;
    font-size: 16px;
    padding: 5px;
    margin-left: 10px;
}

.no-notifications {
    text-align: center;
    color: #6c757d;
    padding: 20px;
}
</style>