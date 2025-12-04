<?php

// Afficher toutes les erreurs
error_reporting(E_ALL);

// Afficher les erreurs à l'écran
ini_set('display_errors', 1);

// Activer le reporting des erreurs pendant le développement
ini_set('display_startup_errors', 1);
session_start();
require_once '../../../config/db_connect.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
redirectIfNotLoggedIn();

// Coordonnées de l'entreprise
define('COMPANY_LATITUDE', 5.3877483);
define('COMPANY_LONGITUDE', -3.9266557);
define('ALLOWED_RADIUS_KM', 0.05);

// Fonction pour calculer la distance entre deux points en kilomètres
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // Rayon de la Terre en kilomètres
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

// Fonction pour vérifier si l'utilisateur est dans la zone autorisée
function isInAllowedLocation($userLat, $userLon) {
    $distance = calculateDistance(
        $userLat, 
        $userLon, 
        COMPANY_LATITUDE, 
        COMPANY_LONGITUDE
    );
    
    return $distance <= ALLOWED_RADIUS_KM;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <title>Badgeage Employé</title>
  <style>
    :root {
      --doré-foncé: #8B5A2B;
      --doré-clair: #C89B66;
      --ivoire: #F5F1EB;
      --blanc: #FFFFFF;
      --gris-anthracite: #333333;
      --vert-sage: #8A9A5B;
      --ombre: rgba(0, 0, 0, 0.1);
      --transition: all 0.3s ease;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background: var(--ivoire);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .container {
      width: 100%;
      max-width: 800px;
      background: var(--blanc);
      border-radius: 20px;
      box-shadow: 0 10px 30px var(--ombre);
      overflow: hidden;
      margin: 0 auto;
    }

    .header {
      background: linear-gradient(135deg, var(--doré-clair), var(--doré-foncé));
      color: var(--blanc);
      padding: 30px 40px;
      text-align: center;
      position: relative;
    }

    .retour {
      position: absolute;
      top: 25px;
      left: 25px;
    }

    .retour a {
      margin-top: .8rem;
      color: var(--blanc);
      text-decoration: none;
      font-size: 1.2rem;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: var(--transition);
      padding: 10px 20px;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.2);
      white-space: nowrap;
    }

    .retour a:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateX(-5px);
    }

    .header h1 {
      font-size: 1.8rem;
      font-weight: 600;
      margin-top: 10px;
      padding: 0 20px;
    }

    .location-status {
      margin-top: 15px;
      padding: 10px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.2);
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-size: 0.9rem;
      max-width: 90%;
    }

    .location-status.connected {
      background: rgba(138, 154, 91, 0.3);
    }

    .location-status.disconnected {
      background: rgba(139, 90, 43, 0.3);
    }

    .badge-container {
      padding: 60px 40px 40px;
      display: flex;
      justify-content: center;
      gap: 80px;
      flex-wrap: wrap;
    }

    .badge-card {
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      background: var(--blanc);
      border-radius: 50%;
      width: 220px;
      height: 220px;
      box-shadow: 0 5px 15px var(--ombre);
      transition: var(--transition);
      overflow: visible;
      cursor: pointer;
      border: 3px solid var(--doré-clair);
      flex-shrink: 0;
    }

    .badge-card.disabled {
      cursor: not-allowed;
      opacity: 0.6;
      border-color: #ccc;
    }

    .badge-card.disabled .badge-icon {
      background: #ccc;
    }

    .badge-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--doré-clair);
      opacity: 0;
      transition: var(--transition);
      border-radius: 50%;
    }

    .badge-card:not(.disabled):hover::before {
      opacity: 0.05;
    }

    .badge-card.active {
      transform: scale(1.05);
      box-shadow: 0 10px 25px rgba(139, 90, 43, 0.2);
      border-color: var(--doré-foncé);
    }

    .badge-icon {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-top: 35px;
      font-size: 2.8rem;
      color: var(--blanc);
      z-index: 2;
      transition: var(--transition);
      background: var(--doré-clair);
    }

    .badge-card.active .badge-icon {
      transform: scale(1.1);
      background: var(--doré-foncé);
    }

    .badge-card h3 {
      margin-top: 20px;
      font-size: 1.2rem;
      color: var(--gris-anthracite);
      z-index: 2;
      text-align: center;
      padding: 0 10px;
    }

    .badge-time {
      font-size: 0.9rem;
      color: var(--doré-foncé);
      margin-top: 8px;
      z-index: 2;
      text-align: center;
      padding: 0 15px;
      font-weight: 500;
      word-break: break-word;
    }

    .overtime-info {
      font-size: 0.8rem;
      color: var(--vert-sage);
      margin-top: 5px;
      font-weight: 500;
      text-align: center;
      padding: 0 10px;
    }

    .ripple-effect {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 100%;
      height: 100%;
      border-radius: 50%;
      overflow: visible;
      pointer-events: none;
    }

    .ripple {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 100%;
      height: 100%;
      border-radius: 50%;
      opacity: 0;
      animation: ripple 3.5s ease-out infinite;
      box-shadow: 
        0 0 8px 2px rgba(139, 90, 43, 0.15),
        0 0 15px 4px rgba(139, 90, 43, 0.08),
        inset 0 2px 4px rgba(255, 255, 255, 0.3);
      background: radial-gradient(
        circle at center,
        rgba(139, 90, 43, 0.03) 0%,
        rgba(139, 90, 43, 0.08) 50%,
        rgba(139, 90, 43, 0.12) 100%
      );
      filter: blur(1px);
    }

    .ripple:nth-child(2) {
      animation-delay: 1.2s;
    }

    .ripple:nth-child(3) {
      animation-delay: 2.4s;
    }

    @keyframes ripple {
      0% {
        width: 100%;
        height: 100%;
        opacity: 0;
        box-shadow: 
          0 0 8px 2px rgba(139, 90, 43, 0.15),
          0 0 15px 4px rgba(139, 90, 43, 0.08),
          inset 0 2px 4px rgba(255, 255, 255, 0.3);
      }
      20% {
        opacity: 0.6;
      }
      100% {
        width: 200%;
        height: 200%;
        opacity: 0;
        box-shadow: 
          0 0 20px 6px rgba(139, 90, 43, 0.05),
          0 0 35px 10px rgba(139, 90, 43, 0.02),
          inset 0 2px 4px rgba(255, 255, 255, 0.1);
      }
    }

    .badge-card.active .ripple {
      background: radial-gradient(
        circle at center,
        rgba(139, 90, 43, 0.05) 0%,
        rgba(139, 90, 43, 0.12) 50%,
        rgba(139, 90, 43, 0.18) 100%
      );
      box-shadow: 
        0 0 10px 3px rgba(139, 90, 43, 0.2),
        0 0 20px 6px rgba(139, 90, 43, 0.12),
        inset 0 2px 5px rgba(255, 255, 255, 0.4);
    }

    .status-badge {
      position: absolute;
      top: -8px;
      right: -8px;
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: var(--vert-sage);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--blanc);
      font-size: 0.9rem;
      z-index: 3;
      opacity: 0;
      transition: var(--transition);
      box-shadow: 0 2px 5px var(--ombre);
    }

    .status-badge.active {
      opacity: 1;
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .modal-content {
      background: var(--blanc);
      border-radius: 20px;
      width: 100%;
      max-width: 600px;
      padding: 35px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
      position: relative;
      animation: modalAppear 0.3s ease;
      max-height: 90vh;
      overflow-y: auto;
    }

    @keyframes modalAppear {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--ivoire);
    }

    .modal-header h2 {
      color: var(--gris-anthracite);
      font-size: 1.5rem;
    }

    .close-modal {
      background: none;
      border: none;
      font-size: 1.8rem;
      cursor: pointer;
      color: var(--doré-foncé);
      transition: var(--transition);
      flex-shrink: 0;
    }

    .close-modal:hover {
      color: var(--gris-anthracite);
    }

    .form-group {
      margin-bottom: 25px;
    }

    .form-group label {
      display: block;
      margin-bottom: 10px;
      font-weight: 500;
      color: var(--gris-anthracite);
      font-size: 1.1rem;
    }

    .optional-note {
      font-size: 0.9rem;
      color: var(--doré-clair);
      font-style: italic;
      margin-top: 5px;
    }

    .form-group textarea {
      width: 100%;
      padding: 18px;
      border: 1px solid var(--doré-clair);
      border-radius: 12px;
      resize: vertical;
      min-height: 180px;
      font-family: inherit;
      transition: var(--transition);
      background: var(--ivoire);
      font-size: 1rem;
    }

    .form-group textarea:focus {
      outline: none;
      border-color: var(--doré-foncé);
      box-shadow: 0 0 0 3px rgba(139, 90, 43, 0.1);
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 20px;
      margin-top: 30px;
    }

    .btn {
      padding: 14px 30px;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      font-weight: 500;
      transition: var(--transition);
      font-size: 1rem;
    }

    .btn-primary {
      background: var(--doré-clair);
      color: var(--blanc);
    }

    .btn-primary:hover {
      background: var(--doré-foncé);
      transform: translateY(-2px);
    }

    .btn-secondary {
      background: var(--ivoire);
      color: var(--gris-anthracite);
    }

    .btn-secondary:hover {
      background: #e8e2d9;
      transform: translateY(-2px);
    }

    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 18px 28px;
      border-radius: 12px;
      color: var(--blanc);
      font-weight: 500;
      box-shadow: 0 5px 15px var(--ombre);
      z-index: 1100;
      transform: translateX(150%);
      transition: transform 0.4s ease;
      font-size: 1rem;
      max-width: calc(100vw - 40px);
      word-break: break-word;
    }

    .notification.show {
      transform: translateX(0);
    }

    .notification.success {
      background: var(--vert-sage);
    }

    .notification.error {
      background: var(--doré-foncé);
    }

    /* Responsive amélioré */
    @media (max-width: 900px) {
      .badge-container {
        gap: 60px;
        padding: 50px 30px 35px;
      }
    }

    @media (max-width: 768px) {
      body {
        padding: 15px;
        align-items: flex-start;
        min-height: 100vh;
        height: auto;
      }
      
      .container {
        margin-top: 20px;
        margin-bottom: 20px;
      }
      
      .badge-container {
        gap: 50px;
        padding: 40px 25px 30px;
      }
      
      .header {
        padding: 25px 20px;
      }
      
      .header h1 {
        font-size: 1.5rem;
        margin-top: 15px;
      }
      
      .retour {
        position: relative;
        top: auto;
        left: auto;
        display: inline-block;
        margin-bottom: 10px;
      }
      
      .badge-card {
        width: 200px;
        height: 200px;
      }
      
      .badge-icon {
        width: 80px;
        height: 80px;
        margin-top: 30px;
        font-size: 2.5rem;
      }
      
      .badge-card h3 {
        font-size: 1.1rem;
      }
      
      .modal-content {
        padding: 25px;
        margin: 10px;
      }
    }

    @media (max-width: 650px) {
      .badge-container {
        gap: 40px;
        padding: 35px 20px 25px;
      }
      
      .badge-card {
        width: 180px;
        height: 180px;
      }
      
      .badge-icon {
        width: 70px;
        height: 70px;
        margin-top: 25px;
        font-size: 2.2rem;
      }
    }

    @media (max-width: 580px) {
      .badge-container {
        flex-direction: column;
        align-items: center;
        gap: 40px;
        padding: 30px 20px 25px;
      }
      
      .header {
        padding: 20px 15px;
      }
      
      .header h1 {
        font-size: 1.3rem;
        padding: 0 10px;
      }
      
      .location-status {
        font-size: 0.85rem;
        padding: 8px 12px;
      }
      
      .badge-card {
        width: 170px;
        height: 170px;
      }
      
      .badge-icon {
        width: 65px;
        height: 65px;
        margin-top: 25px;
        font-size: 2rem;
      }
      
      .badge-card h3 {
        font-size: 1rem;
        margin-top: 15px;
      }
      
      .badge-time {
        font-size: 0.85rem;
      }
      
      .modal-content {
        padding: 20px 15px;
      }
      
      .modal-header h2 {
        font-size: 1.3rem;
      }
      
      .modal-actions {
        flex-direction: column;
        gap: 10px;
      }
      
      .btn {
        width: 100%;
        padding: 12px 20px;
      }
      
      .notification {
        right: 10px;
        left: 10px;
        max-width: none;
        text-align: center;
      }
    }

    @media (max-width: 380px) {
      body {
        padding: 10px;
      }
      
      .container {
        border-radius: 15px;
      }
      
      .header {
        padding: 15px 10px;
        border-radius: 15px 15px 0 0;
      }
      
      .header h1 {
        font-size: 1.2rem;
      }
      
      .retour a {
        font-size: 1rem;
        padding: 8px 15px;
      }
      
      .badge-container {
        padding: 25px 15px 20px;
        gap: 30px;
      }
      
      .badge-card {
        width: 150px;
        height: 150px;
      }
      
      .badge-icon {
        width: 55px;
        height: 55px;
        margin-top: 20px;
        font-size: 1.8rem;
      }
      
      .badge-card h3 {
        font-size: 0.95rem;
        margin-top: 12px;
      }
      
      .badge-time {
        font-size: 0.8rem;
        margin-top: 5px;
      }
      
      .status-badge {
        width: 25px;
        height: 25px;
        font-size: 0.8rem;
      }
    }

    /* Support des très petits écrans */
    @media (max-width: 320px) {
      .badge-card {
        width: 140px;
        height: 140px;
      }
      
      .badge-icon {
        width: 50px;
        height: 50px;
        margin-top: 18px;
        font-size: 1.6rem;
      }
      
      .header h1 {
        font-size: 1.1rem;
      }
    }

    /* Support de l'orientation paysage sur mobile */
    @media (max-height: 600px) and (orientation: landscape) {
      body {
        align-items: flex-start;
        padding: 10px;
      }
      
      .container {
        margin-top: 10px;
        margin-bottom: 10px;
      }
      
      .badge-container {
        padding: 20px 30px;
        gap: 40px;
      }
      
      .badge-card {
        width: 150px;
        height: 150px;
      }
      
      .badge-icon {
        width: 60px;
        height: 60px;
        margin-top: 20px;
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="retour">
        <a href="../index.php"><i class="fas fa-arrow-left"></i> Retour</a>
      </div>
      <h1>Badgeage Employé</h1>
      <div class="location-status" id="locationStatus">
        <i class="fas fa-map-marker-alt"></i>
        <span>Vérification de la localisation...</span>
      </div>
    </div>
    
    <div class="badge-container">
      <div class="badge-card" id="arriver-card">
        <div class="ripple-effect">
          <div class="ripple"></div>
          <div class="ripple"></div>
          <div class="ripple"></div>
        </div>
        <div class="badge-icon">
          <i class="fas fa-sign-in-alt"></i>
        </div>
        <h3>Arrivée</h3>
        <div class="badge-time" id="arrival-time"></div>
        <div class="status-badge" id="arrival-status">
          <i class="fas fa-check"></i>
        </div>
      </div>
      
      <div class="badge-card" id="depart-card">
        <div class="ripple-effect">
          <div class="ripple"></div>
          <div class="ripple"></div>
          <div class="ripple"></div>
        </div>
        <div class="badge-icon">
          <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3>Départ</h3>
        <div class="badge-time" id="departure-time"></div>
        <div class="overtime-info" id="overtime-info"></div>
        <div class="status-badge" id="departure-status">
          <i class="fas fa-check"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal pour le rapport facultatif -->
  <div class="modal" id="report-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Rapport de Fin de Journée</h2>
        <button class="close-modal">&times;</button>
      </div>
      <form id="report-form">
        <div class="form-group">
          <label for="observations">Observations et remarques</label>
          <div class="optional-note">(Facultatif)</div>
          <textarea id="observations" placeholder="Notez ici vos observations, incidents ou remarques concernant votre journée de travail..."></textarea>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" id="skip-report">Passer</button>
          <button type="submit" class="btn btn-primary">Soumettre</button>
        </div>
      </form>
    </div>
  </div>

  <div class="notification" id="notification"></div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const arrivalCard = document.getElementById('arriver-card');
      const departCard = document.getElementById('depart-card');
      const arrivalTime = document.getElementById('arrival-time');
      const departureTime = document.getElementById('departure-time');
      const overtimeInfo = document.getElementById('overtime-info');
      const arrivalStatus = document.getElementById('arrival-status');
      const departureStatus = document.getElementById('departure-status');
      const locationStatus = document.getElementById('locationStatus');
      const modal = document.getElementById('report-modal');
      const closeModal = document.querySelector('.close-modal');
      const skipReport = document.getElementById('skip-report');
      const reportForm = document.getElementById('report-form');
      const notification = document.getElementById('notification');
      
      // Coordonnées de l'entreprise (mêmes que dans le PHP)
      const COMPANY_LATITUDE = 5.3877483;
      const COMPANY_LONGITUDE = -3.9266557;
      const ALLOWED_RADIUS_KM = 0.05; 
      
      let isInCompanyLocation = false;
      let userLatitude = null;
      let userLongitude = null;
      
      // Vérifier la localisation au chargement
      checkLocation();
      
      // Vérifier les badgeages existants
      checkExistingBadgeages();
      
      // Gestion du badgeage d'arrivée
      arrivalCard.addEventListener('click', function() {
        if (!isInCompanyLocation) {
          showNotification('Vous devez être dans l\'enceinte de l\'entreprise pour badger votre arrivée', 'error');
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
      
      // Fonction pour calculer la distance entre deux points (formule de Haversine)
      function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Rayon de la Terre en kilomètres
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = 
          Math.sin(dLat/2) * Math.sin(dLat/2) +
          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
          Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
      }
      
      // Fonction pour vérifier la localisation
      function checkLocation() {
        if (!navigator.geolocation) {
          locationStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Géolocalisation non supportée';
          locationStatus.className = 'location-status disconnected';
          arrivalCard.classList.add('disabled');
          return;
        }
        
        navigator.geolocation.getCurrentPosition(
          function(position) {
            userLatitude = position.coords.latitude;
            userLongitude = position.coords.longitude;
            
            const distance = calculateDistance(
              userLatitude, 
              userLongitude, 
              COMPANY_LATITUDE, 
              COMPANY_LONGITUDE
            );
            
            isInCompanyLocation = distance <= ALLOWED_RADIUS_KM;
            
            if (isInCompanyLocation) {
              locationStatus.innerHTML = `<i class="fas fa-map-marker-alt"></i> Sur site`;
              locationStatus.className = 'location-status connected';
              arrivalCard.classList.remove('disabled');
            } else {
              locationStatus.innerHTML = `<i class="fas fa-map-marker-alt"></i> Hors site`;
              locationStatus.className = 'location-status disconnected';
              arrivalCard.classList.add('disabled');
            }
          },
          function(error) {
            console.error('Erreur de géolocalisation:', error);
            let errorMessage = 'Erreur de localisation';
            
            switch(error.code) {
              case error.PERMISSION_DENIED:
                errorMessage = 'Localisation refusée';
                break;
              case error.POSITION_UNAVAILABLE:
                errorMessage = 'Position indisponible';
                break;
              case error.TIMEOUT:
                errorMessage = 'Timeout de localisation';
                break;
            }
            
            locationStatus.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${errorMessage}`;
            locationStatus.className = 'location-status disconnected';
            arrivalCard.classList.add('disabled');
          },
          {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 60000
          }
        );
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
          // Envoyer les coordonnées GPS avec la requête
          fetch('badgeage.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              action: 'arrival',
              latitude: userLatitude,
              longitude: userLongitude
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
        // Envoyer les coordonnées GPS avec la requête de départ
        fetch('badgeage.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            action: 'departure',
            observations: observations,
            latitude: userLatitude,
            longitude: userLongitude
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
        arrivalTime.textContent = ` à ${time}`;
        arrivalStatus.classList.add('active');
        arrivalCard.classList.add('active');
      }
      
      // Fonction pour activer l'état de départ
      function enableDepartureState(time, overtime) {
        departureTime.textContent = `à ${time}`;
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

      // Rafraîchir la localisation périodiquement
      setInterval(checkLocation, 30000); // Toutes les 30 secondes
    });
  </script>
</body>
</html>