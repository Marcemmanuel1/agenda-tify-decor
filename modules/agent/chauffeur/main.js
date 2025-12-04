
document.addEventListener('DOMContentLoaded', function() {
  const arrivalCard = document.getElementById('arriver-card');
  const departCard = document.getElementById('depart-card');
  const arrivalTime = document.getElementById('arrival-time');
  const departureTime = document.getElementById('departure-time');
  const arrivalStatus = document.getElementById('arrival-status');
  const departureStatus = document.getElementById('departure-status');
  const modal = document.getElementById('report-modal');
  const closeModal = document.querySelector('.close-modal');
  const cancelReport = document.getElementById('cancel-report');
  const reportForm = document.getElementById('report-form');
  const notification = document.getElementById('notification');
  
  // Vérifier les badgeages existants au chargement
  checkExistingBadgeages();
  
  // Gestion du badgeage d'arrivée
  arrivalCard.addEventListener('click', function() {
    badgeArrival();
  });
  
  // Gestion du badgeage de descente
  departCard.addEventListener('click', function() {
    badgeDeparture();
  });
  
  // Fermeture du modal
  closeModal.addEventListener('click', function() {
    modal.style.display = 'none';
  });
  
  cancelReport.addEventListener('click', function() {
    modal.style.display = 'none';
  });
  
  // Gestion du formulaire de rapport
  reportForm.addEventListener('submit', function(e) {
    e.preventDefault();
    submitReport();
  });
  
  // Fonction pour vérifier les badgeages existants
  function checkExistingBadgeages() {
    fetch('check_badgeage.php')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (data.arrival) {
            enableArrivalState(data.arrival);
          }
          if (data.departure) {
            enableDepartureState(data.departure);
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
    } else if (!arrivalCard.classList.contains('active')) {
      showNotification('Vous devez d\'abord badger votre arrivée', 'error');
    } else {
      showNotification('Vous avez déjà badgé votre départ aujourd\'hui', 'error');
    }
  }
  
  // Fonction pour soumettre le rapport
  function submitReport() {
    const observations = document.getElementById('observations').value;
    
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
        enableDepartureState(data.time);
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
    arrivalTime.textContent = `Badgé à ${time}`;
    arrivalStatus.classList.add('active');
    arrivalCard.classList.add('active');
  }
  
  // Fonction pour activer l'état de descente
  function enableDepartureState(time) {
    departureTime.textContent = `Badgé à ${time}`;
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
});
