// Navigation par onglets
document.querySelectorAll(".tab-button").forEach((button) => {
  button.addEventListener("click", function () {
    // Désactiver tous les onglets
    document
      .querySelectorAll(".tab-button")
      .forEach((btn) => btn.classList.remove("active"));
    document
      .querySelectorAll(".tab-content")
      .forEach((content) => content.classList.remove("active"));

    // Activer l'onglet courant
    this.classList.add("active");
    const tabId = this.getAttribute("data-tab") + "-tab";
    document.getElementById(tabId).classList.add("active");
  });
});

// Calcul automatique de la durée
const dateDebut = document.getElementById("date_debut");
const dateFin = document.getElementById("date_fin");
const dureeIndicator = document.getElementById("duree-indicator");
const dureeCalcul = document.getElementById("duree-calcul");

function calculerDuree() {
  if (dateDebut.value && dateFin.value) {
    const debut = new Date(dateDebut.value);
    const fin = new Date(dateFin.value);

    const diffTime = fin.getTime() - debut.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

    dureeCalcul.textContent = diffDays;
    dureeIndicator.classList.add("show");
  } else {
    dureeIndicator.classList.remove("show");
  }
}

dateDebut.addEventListener("change", calculerDuree);
dateFin.addEventListener("change", calculerDuree);

// Validation améliorée
document.getElementById("form-conges").addEventListener("submit", function (e) {
  const submitBtn = document.getElementById("submit-btn");
  const debut = new Date(dateDebut.value);
  const fin = new Date(dateFin.value);
  const aujourdhui = new Date();
  aujourdhui.setHours(0, 0, 0, 0);

  if (fin < debut) {
    e.preventDefault();
    showToast("La date de fin doit être après la date de début", "error");
    return false;
  }

  if (debut < aujourdhui) {
    e.preventDefault();
    showToast("La date de début ne peut pas être dans le passé", "error");
    return false;
  }

  // Animation de chargement
  submitBtn.classList.add("loading");
  submitBtn.disabled = true;
});

// Fonction pour les notifications toast (optionnelle)
function showToast(message, type = "info") {
  // Implémentation basique - vous pouvez utiliser une librairie pour plus de fonctionnalités
  alert(message);
}

// Amélioration de l'UX sur mobile
document.addEventListener("DOMContentLoaded", function () {
  // Focus sur le premier champ du formulaire quand l'onglet nouvelle demande est activé
  const nouvelleTab = document.getElementById("nouvelle-tab");
  const observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (
        mutation.type === "attributes" &&
        mutation.attributeName === "class"
      ) {
        if (nouvelleTab.classList.contains("active")) {
          dateDebut.focus();
        }
      }
    });
  });

  observer.observe(nouvelleTab, { attributes: true });

  // Prévenir la fermeture accidentelle du navigateur pendant la saisie
  window.addEventListener("beforeunload", function (e) {
    if (document.getElementById("motif").value !== "") {
      e.preventDefault();
      e.returnValue = "";
    }
  });
});

// Amélioration du clavier numérique sur mobile
const numberInputs = document.querySelectorAll('input[type="date"]');
numberInputs.forEach((input) => {
  input.addEventListener("focus", function () {
    this.style.fontSize = "16px"; // Prévenir le zoom sur iOS
  });

  input.addEventListener("blur", function () {
    this.style.fontSize = "";
  });
});

// Animation du bouton de retour au survol
document
  .querySelector(".back-button")
  .addEventListener("mouseenter", function () {
    this.style.transform = "translateX(-2px)";
  });

document
  .querySelector(".back-button")
  .addEventListener("mouseleave", function () {
    this.style.transform = "translateX(0)";
  });
