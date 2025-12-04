<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <title>Badgeage Chauffeur</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="retour">
        <a href="../index.php"><i class="fas fa-arrow-left"></i> Retour</a>
      </div>
      <h1>Badgeage et Rapport du Chauffeur</h1>
    </div>
    
    <div class="badge-container">
      <div class="badge-card" id="arriver-card">
        <div class="ripple-effect">
          <div class="ripple"></div>
          <div class="ripple"></div>
          <div class="ripple"></div>
        </div>
        <div class="badge-icon">
          <i class="fas fa-play-circle"></i>
        </div>
        <h3>Début de Service</h3>
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
          <i class="fas fa-flag-checkered"></i>
        </div>
        <h3>Fin de Service</h3>
        <div class="badge-time" id="departure-time"></div>
        <div class="status-badge" id="departure-status">
          <i class="fas fa-check"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal pour le rapport de descente -->
  <div class="modal" id="report-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Rapport de Fin de Service</h2>
        <button class="close-modal">&times;</button>
      </div>
      <form id="report-form">
        <div class="form-group">
          <label for="observations">Observations et remarques</label>
          <textarea id="observations" placeholder="Notez ici vos observations, incidents ou remarques concernant votre journée de travail..."></textarea>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" id="cancel-report">Annuler</button>
          <button type="submit" class="btn btn-primary">Soumettre le rapport</button>
        </div>
      </form>
    </div>
  </div>

  <div class="notification" id="notification"></div>

  <script src="main.js"></script>
</body>
</html>