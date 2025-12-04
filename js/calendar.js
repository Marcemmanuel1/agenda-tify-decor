// Configuration de Day.js en français
dayjs.locale('fr');

class Calendar {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.currentDate = dayjs();
        this.view = 'month'; // month, week, day, year
        this.events = [];
        this._eventsBound = false; // pour éviter double binding

        this.init();
        this.loadEvents();
    }

    init() {
        this.render();
        this.bindEvents(); // bind une seule fois (délégation)
    }

    render() {
        this.container.innerHTML = this.getCalendarHTML();

        if (this.view === 'month') {
            this.renderMonthView();
        } else if (this.view === 'week') {
            this.renderWeekView();
        } else if (this.view === 'day') {
            this.renderDayView();
        } else if (this.view === 'year') {
            this.renderYearView();
        }
    }

    getCalendarHTML() {
        return `
            <div class="calendar-container">
                <div class="calendar-header">
                    <button class="calendar-nav-button" id="calendar-prev">‹</button>
                    <h2 class="calendar-title" id="calendar-title">${this.getTitle()}</h2>
                    <button class="calendar-nav-button" id="calendar-next">›</button>
                </div>

                <div class="calendar-view-switcher">
                    <button class="calendar-view-button ${this.view === 'month' ? 'active' : ''}" data-view="month">Mois</button>
                    <button class="calendar-view-button ${this.view === 'week' ? 'active' : ''}" data-view="week">Semaine</button>
                    <button class="calendar-view-button ${this.view === 'day' ? 'active' : ''}" data-view="day">Jour</button>
                    <button class="calendar-view-button ${this.view === 'year' ? 'active' : ''}" data-view="year">Année</button>
                </div>

                <div id="calendar-view"></div>
            </div>

            <div class="calendar-day-modal" id="day-modal" style="display:none;">
                <div class="calendar-day-modal-content">
                    <div class="calendar-day-modal-header">
                        <h3 class="calendar-day-modal-title" id="day-modal-title">Rendez-vous du jour</h3>
                        <button class="calendar-day-modal-close" id="day-modal-close">&times;</button>
                    </div>
                    <div class="calendar-day-modal-body" id="day-modal-events">
                        </div>
                </div>
            </div>

            <div class="calendar-event-modal" id="event-modal" style="display:none;">
                <div class="calendar-event-modal-content">
                    <div class="calendar-event-modal-header">
                        <h3 class="calendar-event-modal-title" id="event-title">Détails du rendez-vous</h3>
                        <button class="calendar-event-modal-close" id="event-close">&times;</button>
                    </div>
                    <div class="calendar-event-details" id="event-details"></div>
                    <div class="calendar-event-actions" id="event-actions"></div>
                </div>
            </div>

            <div class="calendar-success-modal" id="success-modal" style="display:none;">
                <div class="calendar-success-modal-content">
                    <div class="calendar-success-modal-header">
                        <h3 class="calendar-success-modal-title">Succès</h3>
                        <button class="calendar-success-modal-close" id="success-close">&times;</button>
                    </div>
                    <div class="calendar-success-modal-body" id="success-message">
                        </div>
                </div>
            </div>
        `;
    }

    getTitle() {
        switch (this.view) {
            case 'month':
                return this.currentDate.format('MMMM YYYY');
            case 'week':
                const startOfWeek = this.currentDate.startOf('week');
                const endOfWeek = this.currentDate.endOf('week');
                return `${startOfWeek.format('D MMM')} - ${endOfWeek.format('D MMM YYYY')}`;
            case 'day':
                return this.currentDate.format('dddd D MMMM YYYY');
            case 'year':
                return this.currentDate.format('YYYY');
            default:
                return '';
        }
    }

    renderMonthView() {
        const viewElement = document.getElementById('calendar-view');
        viewElement.innerHTML = `
            <div class="calendar-month-view">
                <div class="calendar-weekdays">
                    <div class="calendar-weekday">Lun</div>
                    <div class="calendar-weekday">Mar</div>
                    <div class="calendar-weekday">Mer</div>
                    <div class="calendar-weekday">Jeu</div>
                    <div class="calendar-weekday">Ven</div>
                    <div class="calendar-weekday">Sam</div>
                    <div class="calendar-weekday">Dim</div>
                </div>
                <div class="calendar-days-grid" id="calendar-days"></div>
            </div>
        `;

        const daysGrid = document.getElementById('calendar-days');
        const firstDayOfMonth = this.currentDate.startOf('month');
        const lastDayOfMonth = this.currentDate.endOf('month');

        let firstDayOfWeek = firstDayOfMonth.day();
        if (firstDayOfWeek === 0) firstDayOfWeek = 7;
        firstDayOfWeek -= 1;

        const daysInMonth = this.currentDate.daysInMonth();

        const prevMonth = this.currentDate.subtract(1, 'month');
        const daysInPrevMonth = prevMonth.daysInMonth();

        // Jours du mois précédent
        for (let i = firstDayOfWeek - 1; i >= 0; i--) {
            const day = daysInPrevMonth - i;
            const date = prevMonth.date(day);
            const dayElement = this.createDayElement(date, true);
            daysGrid.appendChild(dayElement);
        }

        // Jours du mois courant
        const today = dayjs();
        for (let i = 1; i <= daysInMonth; i++) {
            const date = this.currentDate.date(i);
            const dayElement = this.createDayElement(date, false, date.isSame(today, 'day'));
            daysGrid.appendChild(dayElement);
        }

        // Compléter avec le mois suivant
        const totalCells = 42;
        const cellsUsed = firstDayOfWeek + daysInMonth;
        const remainingCells = totalCells - cellsUsed;

        const nextMonth = this.currentDate.add(1, 'month');
        for (let i = 1; i <= remainingCells; i++) {
            const date = nextMonth.date(i);
            const dayElement = this.createDayElement(date, true);
            daysGrid.appendChild(dayElement);
        }
    }

    createDayElement(date, isOtherMonth = false, isToday = false) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';

        if (isOtherMonth) dayElement.classList.add('other-month');
        if (isToday) dayElement.classList.add('today');

        const dayNumber = document.createElement('div');
        dayNumber.className = 'calendar-day-number';
        dayNumber.textContent = date.date();
        dayElement.appendChild(dayNumber);

        const eventsContainer = document.createElement('div');
        eventsContainer.className = 'calendar-events';
        dayElement.appendChild(eventsContainer);

        // Ajouter les événements de ce jour
        const dayEvents = this.getEventsForDate(date);
        dayEvents.forEach(event => {
            const eventElement = document.createElement('div');
            eventElement.className = `calendar-event status-${event.statut.toLowerCase().replace(' ', '-')}`;
            eventElement.textContent = `${event.client} (${event.heure})`;
            eventElement.setAttribute('data-id', event.id);
            eventElement.addEventListener('click', (e) => {
                e.stopPropagation();
                this.showEventDetails(event);
            });
            eventsContainer.appendChild(eventElement);
        });

        if (dayEvents.length > 0) dayElement.classList.add('has-events');

        // clic sur le jour pour afficher la liste des événements
        dayElement.addEventListener('click', () => {
            this.showDayEvents(date);
        });

        return dayElement;
    }

    // Affiche la liste des événements d'un jour
    showDayEvents(date) {
        const dayEvents = this.getEventsForDate(date);
        const modal = document.getElementById('day-modal');
        const title = document.getElementById('day-modal-title');
        const eventsContainer = document.getElementById('day-modal-events');

        title.textContent = `Rendez-vous du ${date.format('dddd D MMMM YYYY')}`;

        if (dayEvents.length === 0) {
            eventsContainer.innerHTML = '<p class="no-events-message">Aucun rendez-vous prévu pour cette journée</p>';
        } else {
            eventsContainer.innerHTML = '';
            dayEvents.forEach(event => {
                const eventItem = document.createElement('div');
                eventItem.className = 'day-modal-event-item';
                eventItem.innerHTML = `
                    <div class="event-item-content">
                        <div class="event-item-time">${event.heure}</div>
                        <div class="event-item-client">${event.client}</div>
                        <div class="event-item-status status-${event.statut.toLowerCase().replace(' ', '-')}">${event.statut}</div>
                    </div>
                `;
                eventItem.addEventListener('click', () => {
                    modal.style.display = 'none';
                    this.showEventDetails(event);
                });
                eventsContainer.appendChild(eventItem);
            });
        }

        modal.style.display = 'flex';
    }

    renderWeekView() {
        const viewElement = document.getElementById('calendar-view');
        viewElement.innerHTML = `
            <div class="calendar-week-view">
                <div class="calendar-week-grid" id="calendar-week-grid"></div>
            </div>
        `;

        const weekGrid = document.getElementById('calendar-week-grid');

        // En-têtes
        const hourHeader = document.createElement('div');
        hourHeader.className = 'calendar-hour-label';
        hourHeader.textContent = 'Heure';
        weekGrid.appendChild(hourHeader);

        const startOfWeek = this.currentDate.startOf('week');
        for (let i = 0; i < 7; i++) {
            const day = startOfWeek.add(i, 'day');
            const dayHeader = document.createElement('div');
            dayHeader.className = 'calendar-hour-label';
            dayHeader.textContent = day.format('ddd D');
            weekGrid.appendChild(dayHeader);
        }

        for (let hour = 8; hour < 20; hour++) {
            const hourLabel = document.createElement('div');
            hourLabel.className = 'calendar-hour-label';
            hourLabel.textContent = `${hour}h`;
            weekGrid.appendChild(hourLabel);

            for (let i = 0; i < 7; i++) {
                const day = startOfWeek.add(i, 'day');
                const hourSlot = document.createElement('div');
                hourSlot.className = 'calendar-hour-slot';
                hourSlot.setAttribute('data-date', day.format('YYYY-MM-DD'));
                hourSlot.setAttribute('data-hour', hour);
                weekGrid.appendChild(hourSlot);

                const slotEvents = this.getEventsForDateAndHour(day, hour);
                slotEvents.forEach(event => {
                    const eventElement = document.createElement('div');
                    eventElement.className = `calendar-week-event status-${event.statut.toLowerCase().replace(' ', '-')}`;
                    eventElement.textContent = event.client;
                    eventElement.style.top = `${(event.minutes / 60) * 60}px`;
                    eventElement.style.height = `${(event.duree / 60) * 60}px`;
                    eventElement.setAttribute('data-id', event.id);
                    eventElement.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.showEventDetails(event);
                    });
                    hourSlot.appendChild(eventElement);
                });
            }
        }
    }

    renderDayView() {
        const viewElement = document.getElementById('calendar-view');
        const dateStr = this.currentDate.format('dddd D MMMM YYYY');

        viewElement.innerHTML = `
            <div class="calendar-day-view">
                <h3 class="calendar-day-title">${dateStr}</h3>
                <div class="calendar-day-events" id="calendar-day-events"></div>
            </div>
        `;

        const eventsContainer = document.getElementById('calendar-day-events');
        const dayEvents = this.getEventsForDate(this.currentDate);

        if (dayEvents.length === 0) {
            eventsContainer.innerHTML = '<p class="calendar-no-events">Aucun rendez-vous prévu pour cette journée</p>';
        } else {
            dayEvents.forEach(event => {
                const eventElement = document.createElement('div');
                eventElement.className = `calendar-day-event status-${event.statut.toLowerCase().replace(' ', '-')}`;
                eventElement.innerHTML = `
                    <div class="calendar-day-event-time">${event.heure}</div>
                    <div class="calendar-day-event-details">
                        <div class="calendar-day-event-client">${event.client}</div>
                        <div class="calendar-day-event-motif">${event.motif}</div>
                    </div>
                `;
                eventElement.setAttribute('data-id', event.id);
                eventElement.addEventListener('click', () => this.showEventDetails(event));
                eventsContainer.appendChild(eventElement);
            });
        }
    }

    renderYearView() {
        const viewElement = document.getElementById('calendar-view');
        viewElement.innerHTML = `
            <div class="calendar-year-view">
                <div class="calendar-months-grid" id="calendar-months"></div>
            </div>
        `;

        const monthsGrid = document.getElementById('calendar-months');

        for (let month = 0; month < 12; month++) {
            const monthDate = dayjs().year(this.currentDate.year()).month(month);
            const monthElement = document.createElement('div');
            monthElement.className = 'calendar-month-item';

            const monthTitle = document.createElement('div');
            monthTitle.className = 'calendar-month-title';
            monthTitle.textContent = monthDate.format('MMMM');
            monthElement.appendChild(monthTitle);

            const weekdays = document.createElement('div');
            weekdays.className = 'calendar-mini-weekdays';
            ['L', 'M', 'M', 'J', 'V', 'S', 'D'].forEach(day => {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-mini-weekday';
                dayElement.textContent = day;
                weekdays.appendChild(dayElement);
            });
            monthElement.appendChild(weekdays);

            const daysGrid = document.createElement('div');
            daysGrid.className = 'calendar-mini-days';
            monthElement.appendChild(daysGrid);

            const firstDayOfMonth = monthDate.startOf('month');
            const daysInMonth = monthDate.daysInMonth();

            let firstDayOfWeek = firstDayOfMonth.day();
            if (firstDayOfWeek === 0) firstDayOfWeek = 7;
            firstDayOfWeek -= 1;

            const prevMonth = monthDate.subtract(1, 'month');
            const daysInPrevMonth = prevMonth.daysInMonth();
            for (let i = firstDayOfWeek - 1; i >= 0; i--) {
                const day = daysInPrevMonth - i;
                const date = prevMonth.date(day);
                const dayElement = this.createMiniDayElement(date, true);
                daysGrid.appendChild(dayElement);
            }

            const today = dayjs();
            for (let i = 1; i <= daysInMonth; i++) {
                const date = monthDate.date(i);
                const hasEvents = this.getEventsForDate(date).length > 0;
                const dayElement = this.createMiniDayElement(date, false, date.isSame(today, 'day'), hasEvents);
                daysGrid.appendChild(dayElement);
            }

            const totalCells = 42;
            const cellsUsed = firstDayOfWeek + daysInMonth;
            const remainingCells = totalCells - cellsUsed;

            const nextMonth = monthDate.add(1, 'month');
            for (let i = 1; i <= remainingCells; i++) {
                const date = nextMonth.date(i);
                const dayElement = this.createMiniDayElement(date, true);
                daysGrid.appendChild(dayElement);
            }

            monthsGrid.appendChild(monthElement);
        }
    }

    createMiniDayElement(date, isOtherMonth = false, isToday = false, hasEvents = false) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-mini-day';
        dayElement.textContent = date.date();

        if (isOtherMonth) dayElement.classList.add('other-month');
        if (isToday) dayElement.classList.add('today');
        if (hasEvents) dayElement.classList.add('has-events');

        dayElement.setAttribute('data-date', date.format('YYYY-MM-DD'));
        dayElement.addEventListener('click', () => {
            this.currentDate = date;
            this.view = 'month';
            this.render();
        });

        return dayElement;
    }

    bindEvents() {
        if (this._eventsBound) return;
        this._eventsBound = true;

        // Délégation d'événements sur le container principal
        this.container.addEventListener('click', (e) => {
            const prevBtn = e.target.closest('#calendar-prev');
            if (prevBtn) {
                this.navigate(-1);
                return;
            }
            const nextBtn = e.target.closest('#calendar-next');
            if (nextBtn) {
                this.navigate(1);
                return;
            }
            const viewBtn = e.target.closest('.calendar-view-button');
            if (viewBtn) {
                this.view = viewBtn.getAttribute('data-view');
                this.render();
                return;
            }
        });

        // Fermeture modals & boutons (délégation globale)
        document.addEventListener('click', (e) => {
            const dayClose = e.target.closest('#day-modal-close');
            if (dayClose) {
                const modal = document.getElementById('day-modal');
                if (modal) modal.style.display = 'none';
                return;
            }

            const eventClose = e.target.closest('#event-close');
            if (eventClose) {
                const modal = document.getElementById('event-modal');
                if (modal) modal.style.display = 'none';
                return;
            }

            // NOUVEAU: Fermer la modale de succès
            const successClose = e.target.closest('#success-close');
            if (successClose) {
                const modal = document.getElementById('success-modal');
                if (modal) modal.style.display = 'none';
                return;
            }

            // Cliquer à l'extérieur des modals pour fermer
            if (e.target && e.target.id === 'day-modal') {
                const m = document.getElementById('day-modal'); if (m) m.style.display = 'none';
            }
            if (e.target && e.target.id === 'event-modal') {
                const m = document.getElementById('event-modal'); if (m) m.style.display = 'none';
            }
            // NOUVEAU: Fermer la modale de succès en cliquant à l'extérieur
            if (e.target && e.target.id === 'success-modal') {
                const m = document.getElementById('success-modal'); if (m) m.style.display = 'none';
            }
        });
    }

    navigate(direction) {
        switch (this.view) {
            case 'month':
                this.currentDate = this.currentDate.add(direction, 'month');
                break;
            case 'week':
                this.currentDate = this.currentDate.add(direction, 'week');
                break;
            case 'day':
                this.currentDate = this.currentDate.add(direction, 'day');
                break;
            case 'year':
                this.currentDate = this.currentDate.add(direction, 'year');
                break;
        }
        this.render();
    }

    loadEvents() {
        // Charger les événements via AJAX
        fetch('../../api/get_events.php')
            .then(response => response.json())
            .then(data => {
                this.events = data;
                this.render();
            })
            .catch(error => {
                console.error('Erreur lors du chargement des événements:', error);
            });
    }

    getEventsForDate(date) {
        const dateStr = date.format('YYYY-MM-DD');
        return this.events.filter(event => {
            const eventDate = event.date_debut.split(' ')[0];
            return eventDate === dateStr;
        });
    }

    getEventsForDateAndHour(date, hour) {
        const dateStr = date.format('YYYY-MM-DD');
        return this.events.filter(event => {
            const parts = event.date_debut.split(' ');
            if (parts.length < 2) return false;
            const eventDate = parts[0];
            const eventHour = parseInt(parts[1].split(':')[0]);
            return eventDate === dateStr && eventHour === hour;
        });
    }

    showEventDetails(event) {
        const modal = document.getElementById('event-modal');
        const title = document.getElementById('event-title');
        const details = document.getElementById('event-details');
        const actions = document.getElementById('event-actions');

        title.textContent = `Rendez-vous avec ${event.client}`;

        details.innerHTML = `
            <div class="calendar-event-detail">
                <span class="calendar-event-detail-label">Date:</span>
                <span class="calendar-event-detail-value">${event.date_complete}</span>
            </div>
            <div class="calendar-event-detail">
                <span class="calendar-event-detail-label">Client:</span>
                <span class="calendar-event-detail-value">${event.client}</span>
            </div>
            <div class="calendar-event-detail">
                <span class="calendar-event-detail-label">Téléphone:</span>
                <span class="calendar-event-detail-value">${event.telephone}</span>
            </div>
            <div class="calendar-event-detail">
                <span class="calendar-event-detail-label">Commune:</span>
                <span class="calendar-event-detail-value">${event.commune}</span>
            </div>
            <div class="calendar-event-detail">
                <span class="calendar-event-detail-label">Statut:</span>
                <span class="calendar-event-detail-value">${event.statut}</span>
            </div>
            <div class="calendar-event-detail">
                <span class="calendar-event-detail-label">Motif:</span>
                <span class="calendar-event-detail-value">${event.motif}</span>
            </div>
        `;

        actions.innerHTML = '';

        if (typeof userRole !== 'undefined') {
            if (userRole === 'planificateur' || userRole === 'super_admin') {
                actions.innerHTML += `
                    <button class="btn btn-primary" onclick="location.href='../planificateur/add_rdv.php?edit=${event.id}'">Modifier</button>
                `;
            }

            if (userRole === 'agent') {
                if (event.statut === 'En attente') {
                    actions.innerHTML += `
                        <button class="btn btn-success" onclick="updateEventStatus(${event.id}, 'Effectué')">Marquer comme effectué</button>
                        <button class="btn btn-danger" onclick="updateEventStatus(${event.id}, 'Annulé')">Annuler</button>
                    `;
                }
            }
        }

        modal.style.display = 'flex';
    }

    // NOUVELLE FONCTION pour afficher la modale de succès
    showSuccessModal(message) {
        const modal = document.getElementById('success-modal');
        const messageContainer = document.getElementById('success-message');

        messageContainer.textContent = message;
        modal.style.display = 'flex';
    }

    
    showMessage(message, type = 'success') {
        const messageContainer = document.getElementById('success-message-container');
        if (messageContainer) {
            messageContainer.textContent = message;
            messageContainer.className = `success-message-container ${type}`;
            messageContainer.style.display = 'block';

            // Masquer le message après 3 secondes
            setTimeout(() => {
                messageContainer.style.display = 'none';
            }, 3000);
        }
    }
}

// Variables globales
let calendar;

// Initialisation du calendrier + notifications
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation du calendrier
    calendar = new Calendar('calendar');

    // Charger notifications immédiatement
    fetchNotifications();
    // Et périodiquement toutes les 30s
    setInterval(fetchNotifications, 30000);
});


// Dans le fichier calendar.js, en dehors de la classe Calendar
function updateEventStatus(eventId, status) {
    fetch('../../api/update_event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: eventId,
            statut: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Afficher le message de succès dans le conteneur du calendrier
            if (calendar) {
                calendar.showMessage(data.message, 'success');
                calendar.loadEvents();
            }
            // Fermer la modale des détails de l'événement
            const eventModal = document.getElementById('event-modal');
            if (eventModal) eventModal.style.display = 'none';
        } else {
            // En cas d'erreur, afficher le message d'erreur de la même manière
            if (calendar) {
                calendar.showMessage('Erreur: ' + data.message, 'error');
            } else {
                alert('Erreur: ' + data.message);
            }
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        if (calendar) {
            calendar.showMessage('Erreur lors de la mise à jour', 'error');
        } else {
            alert('Erreur lors de la mise à jour');
        }
    });
}

// Fonction pour récupérer les notifications
function fetchNotifications() {
    fetch('../../modules/ajax/get_notifications.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.count);
            }
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des notifications:', error);
        });
}

// Fonction pour mettre à jour le badge de notifications
function updateNotificationBadge(count) {
    const badge = document.getElementById('notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
}