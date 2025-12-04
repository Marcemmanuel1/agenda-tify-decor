/**
 * Validation des formulaires côté client
 */

document.addEventListener('DOMContentLoaded', function() {
    // Validation du formulaire de rendez-vous
    const rdvForm = document.querySelector('form');
    if (rdvForm) {
        rdvForm.addEventListener('submit', function(e) {
            if (!validateRdvForm(this)) {
                e.preventDefault();
            }
        });
    }
    
    // Validation du formulaire d'utilisateur
    const userForm = document.querySelector('.user-form');
    if (userForm) {
        userForm.addEventListener('submit', function(e) {
            if (!validateUserForm(this)) {
                e.preventDefault();
            }
        });
    }
    
    // Masquer les messages flash après 5 secondes
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});

/**
 * Validation du formulaire de rendez-vous
 */
function validateRdvForm(form) {
    let isValid = true;
    
    // Validation du nom
    const nom = form.querySelector('#nom');
    if (!nom.value.trim()) {
        showError(nom, 'Le nom est obligatoire');
        isValid = false;
    } else {
        clearError(nom);
    }
    
    // Validation du prénom
    const prenom = form.querySelector('#prenom');
    if (!prenom.value.trim()) {
        showError(prenom, 'Le prénom est obligatoire');
        isValid = false;
    } else {
        clearError(prenom);
    }
    
    // Validation du téléphone
    const telephone = form.querySelector('#telephone');
    if (!validatePhone(telephone.value)) {
        showError(telephone, 'Numéro de téléphone invalide');
        isValid = false;
    } else {
        clearError(telephone);
    }
    
    // Validation de la date de rendez-vous
    const dateRdv = form.querySelector('#date_rdv');
    if (!dateRdv.value) {
        showError(dateRdv, 'La date de rendez-vous est obligatoire');
        isValid = false;
    } else {
        // La vérification de la date future a été supprimée
        clearError(dateRdv);
    }
    
    // Validation de la date de contact
    const dateContact = form.querySelector('#date_contact');
    if (!dateContact.value) {
        showError(dateContact, 'La date de contact est obligatoire');
        isValid = false;
    } else {
        clearError(dateContact);
    }
    
    return isValid;
}

/**
 * Validation du formulaire d'utilisateur
 */
function validateUserForm(form) {
    let isValid = true;
    
    // Validation du nom
    const nom = form.querySelector('#nom');
    if (!nom.value.trim()) {
        showError(nom, 'Le nom est obligatoire');
        isValid = false;
    } else {
        clearError(nom);
    }
    
    // Validation du prénom
    const prenom = form.querySelector('#prenom');
    if (!prenom.value.trim()) {
        showError(prenom, 'Le prénom est obligatoire');
        isValid = false;
    } else {
        clearError(prenom);
    }
    
    // Validation de l'email
    const email = form.querySelector('#email');
    if (!validateEmail(email.value)) {
        showError(email, 'Email invalide');
        isValid = false;
    } else {
        clearError(email);
    }
    
    // Validation du mot de passe (seulement pour l'ajout)
    const password = form.querySelector('#password');
    if (password && !password.value) {
        showError(password, 'Le mot de passe est obligatoire');
        isValid = false;
    } else if (password) {
        clearError(password);
    }
    
    return isValid;
}

/**
 * Validation d'un numéro de téléphone français
 */
function validatePhone(phone) {
    // Supprimer tous les caractères non numériques
    const cleaned = phone.replace(/[^0-9]/g, '');
    
    // Vérifier la longueur et le format
    return cleaned.length === 10 && cleaned.startsWith('0') && /^0[1-9]/.test(cleaned);
}

/**
 * Validation d'une adresse email
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validation d'une date future
 */
function validateFutureDate(dateString) {
    const inputDate = new Date(dateString);
    const now = new Date();
    return inputDate > now;
}

/**
 * Afficher un message d'erreur pour un champ
 */
function showError(input, message) {
    clearError(input);
    
    const error = document.createElement('div');
    error.className = 'error-message';
    error.style.color = '#e74c3c';
    error.style.fontSize = '0.8rem';
    error.style.marginTop = '0.3rem';
    error.textContent = message;
    
    input.style.borderColor = '#e74c3c';
    input.parentNode.appendChild(error);
}

/**
 * Effacer les messages d'erreur d'un champ
 */
function clearError(input) {
    input.style.borderColor = '';
    
    const error = input.parentNode.querySelector('.error-message');
    if (error) {
        error.remove();
    }
}

/**
 * Formater un numéro de téléphone lors de la saisie
 */
function formatPhoneInput(input) {
    let value = input.value.replace(/[^0-9]/g, '');
    
    if (value.length > 10) {
        value = value.substring(0, 10);
    }
    
    if (value.length > 6) {
        value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
    } else if (value.length > 4) {
        value = value.replace(/(\d{2})(\d{2})(\d{2})/, '$1 $2 $3');
    } else if (value.length > 2) {
        value = value.replace(/(\d{2})(\d{2})/, '$1 $2');
    }
    
    input.value = value;
}

/**
 * Formater une date en français
 */
function formatDateFrench(date) {
    const d = new Date(date);
    return d.toLocaleDateString('fr-FR');
}

/**
 * Formater une date et heure en français
 */
function formatDateTimeFrench(date) {
    const d = new Date(date);
    return d.toLocaleDateString('fr-FR') + ' à ' + d.toLocaleTimeString('fr-FR', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}