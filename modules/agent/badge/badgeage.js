
document.addEventListener('DOMContentLoaded', function() {
  const arrivalCard = document.getElementById('arriver-card');
  const departCard = document.getElementById('depart-card');
  const arrivalTime = document.getElementById('arrival-time');
  const departureTime = document.getElementById('departure-time');
  const overtimeInfo = document.getElementById('overtime-info');
  const arrivalStatus = document.getElementById('arrival-status');
  const departureStatus = document.getElementById('departure-status');
  const wifiStatus = document.getElementById('wifiStatus');
  const modal = document.getElementById('report-modal');
  const closeModal = document.querySelector('.close-modal');
  const skipReport = document.getElementById('skip-report');
  const reportForm = document.getElementById('report-form');
  const notification = document.getElementById('notification');
  
  const COMPANY_WIFI_IP = '102.209.220.88';
  let isOnCompanyWifi = false;
  
  // Vérifier la connexion WiFi au chargement
  checkWifiConnection();
  
  // Vérifier les badgeages existants
  checkExistingBadgeages();
  
  // Gestion du badgeage d'arrivée
  arrivalCard.addEventListener('click', function() {
    if (!isOnCompanyWifi) {
      showNotification('Vous devez être connecté au WiFi de l\'entreprise pour badger votre arrivée', 'error');
      return;
    }
    badgeArrival();
  });
  
  // Gestion du badgeage de départ
  departCard.addEventListener('click', function() {
    badgeDeparture();
  });
  
  // Fermeture du modal
  closeModal.addEventListener('click', function() {
    modal.style.display = 'none';
  });
  
  // Fermer le modal en cliquant à l'extérieur
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      modal.style.display = 'none';
    }
  });
  
  skipReport.addEventListener('click', function() {
    submitDeparture('');
  });
  
  // Gestion du formulaire de rapport
  reportForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const observations = document.getElementById('observations').value;
    submitDeparture(observations);
  });
  
  // Fonction pour vérifier la connexion WiFi
  async function checkWifiConnection() {
    try {
      const response = await fetch('https://api.ipify.org?format=json');
      const data = await response.json();
      isOnCompanyWifi = data.ip === COMPANY_WIFI_IP;
      
      if (isOnCompanyWifi) {
        wifiStatus.innerHTML = '<i class="fas fa-wifi"></i> Connecté au WiFi entreprise';
        wifiStatus.className = 'wifi-status connected';
        arrivalCard.classList.remove('disabled');
      } else {
        wifiStatus.innerHTML = '<i class="fas fa-wifi-slash"></i> Non connecté au WiFi entreprise';
        wifiStatus.className = 'wifi-status disconnected';
        arrivalCard.classList.add('disabled');
      }
    } catch (error) {
      console.error('Erreur de vérification WiFi:', error);
      wifiStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erreur de vérification';
      wifiStatus.className = 'wifi-status disconnected';
      arrivalCard.classList.add('disabled');
    }
  }
  
  // Fonction pour vérifier les badgeages existants
  function checkExistingBadgeages() {
    fetch('badgeage.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'check'
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (data.arrival) {
          enableArrivalState(data.arrival.time);
        }
        if (data.departure) {
          enableDepartureState(data.departure.recorded_time, data.departure.overtime);
        }
      }
    })
    .catch(error => {
      console.error('Erreur:', error);
    });
  }
  
  // Fonction pour badger l'arrivée
  function badgeArrival() {
    if (!arrivalCard.classList.contains('active')) {
      fetch('badgeage.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'arrival'
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          enableArrivalState(data.time);
          showNotification(data.message, 'success');
        } else {
          showNotification(data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur de connexion', 'error');
      });
    } else {
      showNotification('Vous avez déjà badgé votre arrivée aujourd\'hui', 'error');
    }
  }
  
  // Fonction pour badger le départ
  function badgeDeparture() {
    if (arrivalCard.classList.contains('active') && !departCard.classList.contains('active')) {
      modal.style.display = 'flex';
      // Focus sur le textarea quand le modal s'ouvre
      setTimeout(() => {
        document.getElementById('observations').focus();
      }, 300);
    } else if (!arrivalCard.classList.contains('active')) {
      showNotification('Vous devez d\'abord badger votre arrivée', 'error');
    } else {
      showNotification('Vous avez déjà badgé votre départ aujourd\'hui', 'error');
    }
  }
  
  // Fonction pour soumettre le départ
  function submitDeparture(observations) {
    fetch('badgeage.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'departure',
        observations: observations
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        enableDepartureState(data.time, data.overtime);
        modal.style.display = 'none';
        reportForm.reset();
        showNotification(data.message, 'success');
      } else {
        showNotification(data.message, 'error');
      }
    })
    .catch(error => {
      console.error('Erreur:', error);
      showNotification('Erreur de connexion', 'error');
    });
  }
  
  // Fonction pour activer l'état d'arrivée
  function enableArrivalState(time) {
    arrivalTime.textContent = `Arrivé à ${time}`;
    arrivalStatus.classList.add('active');
    arrivalCard.classList.add('active');
  }
  
  // Fonction pour activer l'état de départ
  function enableDepartureState(time, overtime) {
    departureTime.textContent = `Parti à ${time}`;
    if (overtime > 0) {
      overtimeInfo.textContent = `+${overtime}h supplémentaires`;
    }
    departureStatus.classList.add('active');
    departCard.classList.add('active');
  }
  
  // Fonction pour afficher les notifications
  function showNotification(message, type) {
    notification.textContent = message;
    notification.className = `notification ${type}`;
    notification.classList.add('show');
    
    setTimeout(() => {
      notification.classList.remove('show');
    }, 3000);
  }

  // Gestion du redimensionnement de la fenêtre
  window.addEventListener('resize', function() {
    // Ajustements supplémentaires si nécessaire
  });
});
