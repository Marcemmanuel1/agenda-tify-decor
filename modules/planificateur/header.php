<?php 
$currentPage = basename($_SERVER['PHP_SELF']); 
?>

<div class="sidebar">
    <ul class="nav-menu">
        <li class="nav-item <?= ($currentPage == 'index.php') ? 'active' : '' ?>">
            <a href="index.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-chevron-left"></i> Retour
            </a>
        </li>
        <li class="nav-item <?= ($currentPage == 'home.php') ? 'active' : '' ?>">
            <a href="home.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'add_rdv.php') ? 'active' : '' ?>">
            <a href="add_rdv.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-plus-circle"></i> Nouveau rendez-vous
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'calendrier.php') ? 'active' : '' ?>">
            <a href="calendrier.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-calendar-alt"></i> Calendrier
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'liste_rdv.php') ? 'active' : '' ?>">
            <a href="liste_rdv.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-list"></i> Liste des rendez-vous
            </a>
        </li>

        <li class="nav-item <?= ($currentPage == 'suivi_chantiers.php') ? 'active' : '' ?>">
            <a href="suivi_chantiers.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-clipboard-check"></i> Suivi de chantier
            </a>
        </li>
        <li class="nav-item <?= ($currentPage == 'profile.php') ? 'active' : '' ?>">
            <a href="profile.php" style="text-decoration: none; color: inherit;">
                <i class="fa-solid fa-user"></i> Profile
            </a>
        </li>

        <li class="nav-item">
            <a href="#" style="text-decoration: none; color: inherit;" id="notifications-link">
                <i class="fas fa-bell"></i> Notifications
                <span id="notification-badge" class="notification-badge" style="display: none;">0</span>
            </a>
        </li>
    </ul>
</div>

<div id="notifications-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Notifications</h3>
            <span class="close-btn">&times;</span>
        </div>
        <div class="modal-body" id="notifications-list">
        </div>
        <div class="modal-footer">
            <button id="mark-all-read" class="btn btn-secondary">Tout marquer comme lu</button>
        </div>
    </div>
</div>

<script>
    // Fonction pour récupérer les notifications via AJAX
    function fetchNotifications() {
        fetch('../ajax/get_notifications_planif.php')
            .then(response => response.json())
            .then(notifications => {
                const unreadNotifications = notifications.filter(notif => notif.is_read == 0);
                updateNotificationBadge(unreadNotifications.length);
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
            container.innerHTML = '<p class="no-notifications">Aucune nouvelle notification.</p>';
            return;
        }

        let html = '';
        notifications.forEach(notif => {
            html += `
                <a href="${notif.lien}" class="notification-item ${notif.is_read == 1 ? 'read' : 'unread'}" data-id="${notif.id}">
                    <div class="notification-content">
                        <p>${notif.message}</p>
                        <small>${new Date(notif.created_at).toLocaleString('fr-FR')}</small>
                    </div>
                </a>
            `;
        });
        container.innerHTML = html;
    }

    // Marquer toutes les notifications comme lues
    document.getElementById('mark-all-read')?.addEventListener('click', function() {
        fetch('../ajax/mark_all_notifications_read_planif.php', { method: 'POST' })
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
    document.querySelector('.close-btn')?.addEventListener('click', function() {
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
    max-height: 250px;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px;
    border-top: 1px solid #eee;
    text-align: right;
}

.close-btn {
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