/**
 * Script principal de l'application
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les tooltips
    initTooltips();
    
    // Initialiser les confirmations de suppression
    initConfirmations();
    
    // Initialiser les onglets
    initTabs();
    
    // Initialiser les menus déroulants
    initDropdowns();
    
    // Initialiser les sélecteurs de date
    initDatePickers();
});

/**
 * Initialiser les tooltips
 */
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltipText = this.getAttribute('data-tooltip');
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.position = 'absolute';
    tooltip.style.background = 'rgba(0, 0, 0, 0.8)';
    tooltip.style.color = 'white';
    tooltip.style.padding = '5px 10px';
    tooltip.style.borderRadius = '4px';
    tooltip.style.fontSize = '0.8rem';
    tooltip.style.zIndex = '1000';
    tooltip.style.pointerEvents = 'none';
    
    document.body.appendChild(tooltip);
    
    const rect = this.getBoundingClientRect();
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    
    this.tooltipElement = tooltip;
}

function hideTooltip() {
    if (this.tooltipElement) {
        this.tooltipElement.remove();
        this.tooltipElement = null;
    }
}

/**
 * Initialiser les confirmations de suppression
 */
function initConfirmations() {
    const confirmLinks = document.querySelectorAll('[data-confirm]');
    
    confirmLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Êtes-vous sûr de vouloir effectuer cette action ?';
            
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Initialiser les onglets
 */
function initTabs() {
    const tabContainers = document.querySelectorAll('.tabs');
    
    tabContainers.forEach(container => {
        const tabs = container.querySelectorAll('.tab');
        const tabContents = container.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Désactiver tous les onglets
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Activer l'onglet courant
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });
    });
}

/**
 * Initialiser les menus déroulants
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        
        toggle.addEventListener('click', function() {
            dropdown.classList.toggle('open');
        });
    });
    
    // Fermer les menus déroulants en cliquant à l'extérieur
    document.addEventListener('click', function(e) {
        dropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });
    });
}

/**
 * Initialiser les sélecteurs de date
 */
function initDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"], input[type="datetime-local"]');
    
    dateInputs.forEach(input => {
        // Ajouter une icône de calendrier
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        wrapper.style.display = 'inline-block';
        wrapper.style.width = '100%';
        
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        
        const icon = document.createElement('i');
        icon.className = 'fas fa-calendar-alt';
        icon.style.position = 'absolute';
        icon.style.right = '10px';
        icon.style.top = '50%';
        icon.style.transform = 'translateY(-50%)';
        icon.style.color = '#8B5A2B';
        icon.style.pointerEvents = 'none';
        
        wrapper.appendChild(icon);
    });
}

/**
 * Afficher une notification
 */
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="notification-icon ${getNotificationIcon(type)}"></i>
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        </div>
    `;
    
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.background = 'white';
    notification.style.padding = '15px';
    notification.style.borderRadius = '5px';
    notification.style.boxShadow = '0 3px 10px rgba(0, 0, 0, 0.2)';
    notification.style.zIndex = '10000';
    notification.style.maxWidth = '350px';
    notification.style.borderLeft = `4px solid ${getNotificationColor(type)}`;
    
    document.body.appendChild(notification);
    
    // Animation d'entrée
    notification.style.transform = 'translateX(100%)';
    notification.style.transition = 'transform 0.3s';
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // Bouton de fermeture
    notification.querySelector('.notification-close').addEventListener('click', function() {
        hideNotification(notification);
    });
    
    // Fermeture automatique
    if (duration > 0) {
        setTimeout(() => {
            hideNotification(notification);
        }, duration);
    }
    
    return notification;
}

function hideNotification(notification) {
    notification.style.transform = 'translateX(100%)';
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    
    return icons[type] || 'fas fa-info-circle';
}

function getNotificationColor(type) {
    const colors = {
        'success': '#28a745',
        'error': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8'
    };
    
    return colors[type] || '#17a2b8';
}

/**
 * Fonctions utilitaires
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Requête AJAX helper
 */
function ajaxRequest(url, options = {}) {
    const defaults = {
        method: 'GET',
        data: null,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    const config = { ...defaults, ...options };
    
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        
        xhr.open(config.method, url);
        
        // Set headers
        for (const [key, value] of Object.entries(config.headers)) {
            xhr.setRequestHeader(key, value);
        }
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject(new Error(`Request failed with status ${xhr.status}`));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        xhr.send(config.data ? JSON.stringify(config.data) : null);
    });
}