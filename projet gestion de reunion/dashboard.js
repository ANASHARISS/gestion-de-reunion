// Navigation entre les sections du dashboard
document.querySelectorAll('.sidebar-menu a').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        // Retirer la classe active de tous les liens
        document.querySelectorAll('.sidebar-menu a').forEach(l => l.classList.remove('active'));
        // Ajouter la classe active au lien cliqué
        this.classList.add('active');
        // Masquer toutes les sections
        document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active'));
        // Afficher la section correspondante
        const sectionId = this.getAttribute('data-section');
        const section = document.getElementById(sectionId);
        if(section) {
            section.classList.add('active');
            if(sectionId === 'aide') {
                initialiserAide();
            }
        }
    });
});


// --- Modal création de réunion ---
function openCreateModal() {
    document.getElementById('modal-create').style.display = 'flex';
}
function closeCreateModal() {
    document.getElementById('modal-create').style.display = 'none';
}

document.getElementById('open-create-modal').onclick = openCreateModal;
var btnAccueil = document.getElementById('open-create-modal-accueil');
if(btnAccueil) btnAccueil.onclick = openCreateModal;
var btnPlanif = document.getElementById('open-create-modal-planif');
if(btnPlanif) btnPlanif.onclick = openCreateModal;




// --- Tableau de bord admin ---
function mettreAJourTableauAdmin() {
    const tableauAdminSection = document.getElementById('tableau-admin');
    if(!tableauAdminSection) return;
    
    // Calculer les vraies statistiques
    const stats = calculerStatistiquesAdmin();
    
    // Mettre à jour les cartes de statistiques
    const statsCards = tableauAdminSection.querySelectorAll('.welcome-card > div:nth-child(2) > div');
    if(statsCards.length >= 3) {
        // Utilisateurs inscrits
        statsCards[0].querySelector('div:nth-child(2)').textContent = stats.utilisateursInscrits;
        
        // Réunions créées
        statsCards[1].querySelector('div:nth-child(2)').textContent = stats.reunionsCreees;
        
        // Taux de participation
        statsCards[2].querySelector('div:nth-child(2)').textContent = stats.tauxParticipation + '%';
    }
    
    // Mettre à jour les dernières connexions
    const activiteDiv = tableauAdminSection.querySelector('.welcome-card > div:nth-child(3) > div');
    if(activiteDiv) {
        activiteDiv.innerHTML = `
            <div style="font-size:1.1em; color:#64748b;">Dernières activités :</div>
            <ul style="margin:0; padding-left:18px;">
                ${stats.dernieresActivites.map(activite => `<li>${activite}</li>`).join('')}
            </ul>
        `;
    }
    
    // Mettre à jour les statistiques détaillées
    mettreAJourStatistiques();
}

function calculerStatistiquesAdmin() {
    const maintenant = new Date();
    const debutMois = new Date(maintenant.getFullYear(), maintenant.getMonth(), 1);
    
    // Utilisateurs inscrits (simulé pour l'exemple)
    const utilisateursInscrits = 7;
    
    // Réunions créées ce mois (actives seulement)
    const reunionsCeMois = reunions.filter(r => new Date(r.dateCreation) >= debutMois && r.statut === 'active').length;
    
    // Taux de participation (calculé sur les réunions passées)
    const reunionsPassees = reunions.filter(r => {
        const dateReunion = new Date(r.date + 'T' + r.heure);
        return dateReunion < maintenant && r.statut === 'active';
    });
    
    let tauxParticipation = 85; // Par défaut
    if(reunionsPassees.length > 0) {
        // Simulation basée sur le nombre de réunions
        tauxParticipation = Math.min(95, Math.max(70, 85 + (reunionsPassees.length * 2)));
    }
    
    // Dernières activités
    const dernieresActivites = [];
    const reunionsActives = reunions.filter(r => r.statut === 'active');
    if(reunionsActives.length > 0) {
        const derniereReunion = reunionsActives[reunionsActives.length - 1];
        const dateCreation = new Date(derniereReunion.dateCreation).toLocaleDateString('fr-FR');
        dernieresActivites.push(`Réunion "${derniereReunion.titre}" créée le ${dateCreation}`);
    }
    
    if(reunionsActives.length > 1) {
        const avantDerniereReunion = reunionsActives[reunionsActives.length - 2];
        const dateCreation = new Date(avantDerniereReunion.dateCreation).toLocaleDateString('fr-FR');
        dernieresActivites.push(`Réunion "${avantDerniereReunion.titre}" créée le ${dateCreation}`);
    }
    
    dernieresActivites.push(`Utilisateur connecté le ${maintenant.toLocaleDateString('fr-FR')} à ${maintenant.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'})}`);
    
    return {
        utilisateursInscrits,
        reunionsCreees: reunionsCeMois,
        tauxParticipation: Math.round(tauxParticipation),
        dernieresActivites
    };
}

// --- Gestion des thèmes ---
function initialiserTheme() {
    const theme = localStorage.getItem('theme') || 'light';
    appliquerTheme(theme, false);
}

function appliquerTheme(theme, save = true) {
    document.documentElement.setAttribute('data-theme', theme);
    if(save) localStorage.setItem('theme', theme);
    // Mettre à jour le sélecteur de thème dans les paramètres
    const themeSelect = document.querySelector('#parametres select');
    if(themeSelect) {
        themeSelect.value = theme === 'dark' ? 'Sombre' : 'Clair';
    }
}

function basculerTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    appliquerTheme(newTheme);
}

// Désactiver la sauvegarde automatique lors du changement de sélection
// document.addEventListener('change', ... ) <-- à supprimer ou désactiver

// Ajout de l'écouteur sur le bouton Sauvegarder de l'apparence
window.addEventListener('DOMContentLoaded', function() {
    // ... déjà présent pour le profil ...
    const btns = document.querySelectorAll('#parametres .param-section .btn-create');
    if (btns && btns.length > 2) {
        // Le troisième bouton est pour l'apparence
        btns[2].addEventListener('click', function(e) {
            e.preventDefault();
            const themeSelect = btns[2].closest('.param-section').querySelector('select');
            if(themeSelect) {
                let theme = 'light';
                if(themeSelect.value === 'Sombre') theme = 'dark';
                else if(themeSelect.value === 'Clair') theme = 'light';
                appliquerTheme(theme, true);
            }
            alert('Apparence sauvegardée !');
        });
    }
});

document.getElementById('go-settings').onclick = function() {
    // Retirer la classe active de tous les liens
    document.querySelectorAll('.sidebar-menu a').forEach(l => l.classList.remove('active'));
    // Masquer toutes les sections
    document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active'));
    // Afficher la section 'parametres'
    const section = document.getElementById('parametres');
    if(section) section.classList.add('active');
};

// --- Système de traduction ---
const translations = {
    fr: {
        // Navigation
        accueil: "Accueil",
        planification: "Planification",
        reunions: "Réunions",
        centreAdmin: "Centre admin",
        tableauAdmin: "Tableau de bord admin",
        statistiques: "Statistiques",
        aide: "Aide",
        
        // Boutons
        creer: "Créer",
        annuler: "Annuler",
        sauvegarder: "Sauvegarder",
        modifier: "Modifier",
        supprimer: "Supprimer",
        voir: "Voir",
        
        // Titres
        bienvenue: "Bienvenue sur MeetingPlanner !",
        planifierReunion: "Planifier une réunion",
        voirPlanning: "Voir le planning",
        voirCalendrier: "Voir le calendrier",
        voirHistorique: "Voir l'historique",
        voirStatistiques: "Voir les statistiques",
        
        // Statistiques
        reunionsCreees: "Réunions créées",
        aVenir: "À venir",
        participants: "Participants",
        ceMois: "Ce mois",
        cetteSemaine: "Cette semaine",
        total: "Total",
        derniereActivite: "Dernière activité",
        aucuneActivite: "Aucune activité récente",
        
        // Formulaire réunion
        titreReunion: "Titre de la réunion",
        date: "Date",
        heure: "Heure",
        typeReunion: "Type de réunion",
        description: "Description",
        descriptionPlaceholder: "Description de la réunion...",
        selectionner: "Sélectionner",
        enLigne: "En ligne",
        presentiel: "Présentiel",
        mixte: "Mixte",
        
        // Paramètres
        parametres: "Paramètres",
        profilUtilisateur: "Profil utilisateur",
        nomComplet: "Nom complet",
        email: "Email",
        fuseauHoraire: "Fuseau horaire",
        notifications: "Notifications",
        notificationsEmail: "Notifications par email",
        rappelsReunion: "Rappels avant réunion",
        notificationsPush: "Notifications push",
        rappelMinutes: "Rappel (minutes avant)",
        apparence: "Apparence",
        theme: "Thème",
        langue: "Langue",
        clair: "Clair",
        sombre: "Sombre",
        auto: "Auto",
        securite: "Sécurité",
        motDePasseActuel: "Mot de passe actuel",
        nouveauMotDePasse: "Nouveau mot de passe",
        confirmerMotDePasse: "Confirmer le mot de passe",
        changerMotDePasse: "Changer le mot de passe",
        
        // Aide
        centreAide: "Centre d'aide",
        trouverReponses: "Trouvez rapidement les réponses à vos questions",
        rechercherAide: "Rechercher dans l'aide...",
        questionsFrequentes: "Questions fréquentes",
        besoinAide: "Besoin d'aide supplémentaire ?",
        equipeSupport: "Notre équipe support est là pour vous aider",
        contacterSupport: "Contacter le support",
        
        // Déconnexion
        deconnexion: "Déconnexion",
        confirmationDeconnexion: "Êtes-vous sûr de vouloir vous déconnecter ?",
        deconnexionEnCours: "Déconnexion en cours...",
        redirectionConnexion: "Vous allez être redirigé vers la page principale."
    },
    
    en: {
        // Navigation
        accueil: "Home",
        planification: "Planning",
        reunions: "Meetings",
        centreAdmin: "Admin Center",
        tableauAdmin: "Admin Dashboard",
        statistiques: "Statistics",
        aide: "Help",
        
        // Boutons
        creer: "Create",
        annuler: "Cancel",
        sauvegarder: "Save",
        modifier: "Edit",
        supprimer: "Delete",
        voir: "View",
        
        // Titres
        bienvenue: "Welcome to MeetingPlanner!",
        planifierReunion: "Schedule a meeting",
        voirPlanning: "View planning",
        voirCalendrier: "View calendar",
        voirHistorique: "View history",
        voirStatistiques: "View statistics",
        
        // Statistiques
        reunionsCreees: "Meetings created",
        aVenir: "Upcoming",
        participants: "Participants",
        ceMois: "This month",
        cetteSemaine: "This week",
        total: "Total",
        derniereActivite: "Last activity",
        aucuneActivite: "No recent activity",
        
        // Formulaire réunion
        titreReunion: "Meeting title",
        date: "Date",
        heure: "Time",
        typeReunion: "Meeting type",
        description: "Description",
        descriptionPlaceholder: "Meeting description...",
        selectionner: "Select",
        enLigne: "Online",
        presentiel: "In-person",
        mixte: "Hybrid",
        
        // Paramètres
        parametres: "Settings",
        profilUtilisateur: "User profile",
        nomComplet: "Full name",
        email: "Email",
        fuseauHoraire: "Time zone",
        notifications: "Notifications",
        notificationsEmail: "Email notifications",
        rappelsReunion: "Meeting reminders",
        notificationsPush: "Push notifications",
        rappelMinutes: "Reminder (minutes before)",
        apparence: "Appearance",
        theme: "Theme",
        langue: "Language",
        clair: "Light",
        sombre: "Dark",
        auto: "Auto",
        securite: "Security",
        motDePasseActuel: "Current password",
        nouveauMotDePasse: "New password",
        confirmerMotDePasse: "Confirm password",
        changerMotDePasse: "Change password",
        
        // Aide
        centreAide: "Help Center",
        trouverReponses: "Find answers to your questions quickly",
        rechercherAide: "Search help...",
        questionsFrequentes: "Frequently asked questions",
        besoinAide: "Need additional help?",
        equipeSupport: "Our support team is here to help",
        contacterSupport: "Contact support",
        
        // Déconnexion
        deconnexion: "Logout",
        confirmationDeconnexion: "Are you sure you want to logout?",
        deconnexionEnCours: "Logging out...",
        redirectionConnexion: "You will be redirected to the main page."
    },
    
    es: {
        // Navigation
        accueil: "Inicio",
        planification: "Planificación",
        reunions: "Reuniones",
        centreAdmin: "Centro admin",
        tableauAdmin: "Panel de administración",
        statistiques: "Estadísticas",
        aide: "Ayuda",
        
        // Boutons
        creer: "Crear",
        annuler: "Cancelar",
        sauvegarder: "Guardar",
        modifier: "Editar",
        supprimer: "Eliminar",
        voir: "Ver",
        
        // Titres
        bienvenue: "¡Bienvenido a MeetingPlanner!",
        planifierReunion: "Programar una reunión",
        voirPlanning: "Ver planificación",
        voirCalendrier: "Ver calendario",
        voirHistorique: "Ver historial",
        voirStatistiques: "Ver estadísticas",
        
        // Statistiques
        reunionsCreees: "Reuniones creadas",
        aVenir: "Próximas",
        participants: "Participantes",
        ceMois: "Este mes",
        cetteSemaine: "Esta semana",
        total: "Total",
        derniereActivite: "Última actividad",
        aucuneActivite: "Sin actividad reciente",
        
        // Formulaire réunion
        titreReunion: "Título de la reunión",
        date: "Fecha",
        heure: "Hora",
        typeReunion: "Tipo de reunión",
        description: "Descripción",
        descriptionPlaceholder: "Descripción de la reunión...",
        selectionner: "Seleccionar",
        enLigne: "En línea",
        presentiel: "Presencial",
        mixte: "Híbrida",
        
        // Paramètres
        parametres: "Configuración",
        profilUtilisateur: "Perfil de usuario",
        nomComplet: "Nombre completo",
        email: "Correo electrónico",
        fuseauHoraire: "Zona horaria",
        notifications: "Notificaciones",
        notificationsEmail: "Notificaciones por correo",
        rappelsReunion: "Recordatorios de reunión",
        notificationsPush: "Notificaciones push",
        rappelMinutes: "Recordatorio (minutos antes)",
        apparence: "Apariencia",
        theme: "Tema",
        langue: "Idioma",
        clair: "Claro",
        sombre: "Oscuro",
        auto: "Automático",
        securite: "Seguridad",
        motDePasseActuel: "Contraseña actual",
        nouveauMotDePasse: "Nueva contraseña",
        confirmerMotDePasse: "Confirmar contraseña",
        changerMotDePasse: "Cambiar contraseña",
        
        // Aide
        centreAide: "Centro de ayuda",
        trouverReponses: "Encuentra rápidamente las respuestas a tus preguntas",
        rechercherAide: "Buscar en la ayuda...",
        questionsFrequentes: "Preguntas frecuentes",
        besoinAide: "¿Necesitas ayuda adicional?",
        equipeSupport: "Nuestro equipo de soporte está aquí para ayudarte",
        contacterSupport: "Contactar soporte",
        
        // Déconnexion
        deconnexion: "Cerrar sesión",
        confirmationDeconnexion: "¿Estás seguro de que quieres cerrar sesión?",
        deconnexionEnCours: "Cerrando sesión...",
        redirectionConnexion: "Serás redirigido a la página principal."
    }
};

let currentLanguage = localStorage.getItem('language') || 'fr';

function changeLanguage(lang) {
    currentLanguage = lang;
    localStorage.setItem('language', lang);
    applyTranslations();
}

function applyTranslations() {
    const t = translations[currentLanguage];
    
    // Navigation
    document.querySelectorAll('[data-section="accueil"]').forEach(el => el.innerHTML = `<i class="fa fa-home"></i> ${t.accueil}`);
    document.querySelectorAll('[data-section="planification"]').forEach(el => el.innerHTML = `<i class="fa fa-calendar-check"></i> ${t.planification}`);
    document.querySelectorAll('[data-section="reunions"]').forEach(el => el.innerHTML = `<i class="fa fa-handshake"></i> ${t.reunions}`);
    document.querySelectorAll('[data-section="centre-admin"]').forEach(el => el.innerHTML = `<i class="fa fa-user-shield"></i> ${t.centreAdmin}`);
    document.querySelectorAll('[data-section="tableau-admin"]').forEach(el => el.innerHTML = `<i class="fa fa-users"></i> ${t.tableauAdmin}`);
    document.querySelectorAll('[data-section="statistiques"]').forEach(el => el.innerHTML = `<i class="fa fa-chart-bar"></i> ${t.statistiques}`);
    document.querySelectorAll('[data-section="aide"]').forEach(el => el.innerHTML = `<i class="fa fa-question-circle"></i> ${t.aide}`);
    
    // Déconnexion
    const deconnexionBtn = document.querySelector('#deconnexion-btn');
    if (deconnexionBtn) {
        deconnexionBtn.innerHTML = `<i class="fa fa-sign-out-alt"></i> ${t.deconnexion}`;
    }
    
    // Boutons
    document.querySelectorAll('.btn-create').forEach(btn => {
        if (btn.textContent.includes('Créer') || btn.textContent.includes('Create') || btn.textContent.includes('Crear')) {
            btn.innerHTML = `<i class="fa fa-plus"></i> ${t.creer}`;
        }
    });
    
    // Boutons Annuler
    document.querySelectorAll('.btn-annuler').forEach(btn => {
        if (btn.textContent.includes('Annuler') || btn.textContent.includes('Cancel') || btn.textContent.includes('Cancelar')) {
            btn.textContent = t.annuler;
        }
    });
    
    // Titres principaux
    const welcomeTitle = document.querySelector('#accueil h1');
    if (welcomeTitle) welcomeTitle.textContent = t.bienvenue;
    
    // Formulaire de création
    const titreLabel = document.querySelector('label[for="titre"]');
    if (titreLabel) titreLabel.textContent = t.titreReunion;
    
    const dateLabel = document.querySelector('label[for="date"]');
    if (dateLabel) dateLabel.textContent = t.date;
    
    const heureLabel = document.querySelector('label[for="heure"]');
    if (heureLabel) heureLabel.textContent = t.heure;
    
    const typeLabel = document.querySelector('label[for="type"]');
    if (typeLabel) typeLabel.textContent = t.typeReunion;
    
    const descLabel = document.querySelector('label[for="description"]');
    if (descLabel) descLabel.textContent = t.description;
    
    const descPlaceholder = document.querySelector('#description');
    if (descPlaceholder) descPlaceholder.placeholder = t.descriptionPlaceholder;
    
    // Options du select type
    const typeSelect = document.querySelector('#type');
    if (typeSelect) {
        typeSelect.innerHTML = `
            <option value="">${t.selectionner}</option>
            <option value="En ligne">${t.enLigne}</option>
            <option value="Présentiel">${t.presentiel}</option>
            <option value="Mixte">${t.mixte}</option>
        `;
    }
    
    // Paramètres
    const parametresTitle = document.querySelector('#parametres h2');
    if (parametresTitle) parametresTitle.textContent = t.parametres;
    
    // Labels des paramètres
    const nomCompletLabel = document.querySelector('#parametres label:contains("Nom complet")');
    if (nomCompletLabel) nomCompletLabel.textContent = t.nomComplet;
    
    const emailLabel = document.querySelector('#parametres label:contains("Email")');
    if (emailLabel) emailLabel.textContent = t.email;
    
    const fuseauLabel = document.querySelector('#parametres label:contains("Fuseau horaire")');
    if (fuseauLabel) fuseauLabel.textContent = t.fuseauHoraire;
    
    const langueLabel = document.querySelector('#parametres label:contains("Langue")');
    if (langueLabel) langueLabel.textContent = t.langue;
    
    // Titres des sections paramètres
    const profilTitle = document.querySelector('#parametres h3:contains("Profil utilisateur")');
    if (profilTitle) profilTitle.innerHTML = `<i class="fa fa-user"></i> ${t.profilUtilisateur}`;
    
    const notifTitle = document.querySelector('#parametres h3:contains("Notifications")');
    if (notifTitle) notifTitle.innerHTML = `<i class="fa fa-bell"></i> ${t.notifications}`;
    
    const apparenceTitle = document.querySelector('#parametres h3:contains("Apparence")');
    if (apparenceTitle) apparenceTitle.innerHTML = `<i class="fa fa-palette"></i> ${t.apparence}`;
    
    const securiteTitle = document.querySelector('#parametres h3:contains("Sécurité")');
    if (securiteTitle) securiteTitle.innerHTML = `<i class="fa fa-shield-alt"></i> ${t.securite}`;
    
    // Boutons de sauvegarde
    document.querySelectorAll('#parametres .btn-create').forEach(btn => {
        if (btn.textContent.includes('Sauvegarder') || btn.textContent.includes('Save') || btn.textContent.includes('Guardar')) {
            btn.innerHTML = `<i class="fa fa-save"></i> ${t.sauvegarder}`;
        }
    });
    
    // Mettre à jour le sélecteur de langue
    const langSelect = document.querySelector('#language-select');
    if (langSelect) {
        langSelect.value = currentLanguage;
    }
    
    // Section Aide
    const aideTitle = document.querySelector('#aide h2');
    if (aideTitle) aideTitle.innerHTML = `<i class="fa fa-question-circle"></i> ${t.centreAide}`;
    
    const aideSubtitle = document.querySelector('.aide-subtitle');
    if (aideSubtitle) aideSubtitle.textContent = t.trouverReponses;
    
    const searchInput = document.querySelector('#aide-search-input');
    if (searchInput) searchInput.placeholder = t.rechercherAide;
    
    const faqTitle = document.querySelector('.faq-section h3');
    if (faqTitle) faqTitle.innerHTML = `<i class="fa fa-lightbulb"></i> ${t.questionsFrequentes}`;
    
    const supportTitle = document.querySelector('.support-content h4');
    if (supportTitle) supportTitle.textContent = t.besoinAide;
    
    const supportText = document.querySelector('.support-content p');
    if (supportText) supportText.textContent = t.equipeSupport;
    
    const supportBtn = document.querySelector('.btn-support');
    if (supportBtn) supportBtn.innerHTML = `<i class="fa fa-comments"></i> ${t.contacterSupport}`;
    
    // Labels des notifications
    const notifEmailLabel = Array.from(document.querySelectorAll('#parametres label span')).find(span => span.textContent.trim().match(/Notifications par email|Email notifications|Notificaciones por correo/));
    if (notifEmailLabel) notifEmailLabel.textContent = t.notificationsEmail;
    const notifRappelLabel = Array.from(document.querySelectorAll('#parametres label span')).find(span => span.textContent.trim().match(/Rappels avant réunion|Meeting reminders|Recordatorios de reunión/));
    if (notifRappelLabel) notifRappelLabel.textContent = t.rappelsReunion;
    const notifPushLabel = Array.from(document.querySelectorAll('#parametres label span')).find(span => span.textContent.trim().match(/Notifications push|Push notifications|Notificaciones push/));
    if (notifPushLabel) notifPushLabel.textContent = t.notificationsPush;
}

// Initialiser la langue au chargement
function initialiserLangue() {
    const langSelect = document.querySelector('#language-select');
    if (langSelect) {
        langSelect.value = currentLanguage;
        langSelect.addEventListener('change', function() {
            changeLanguage(this.value);
        });
    }
    applyTranslations();
}

// --- Fonctionnalité de déconnexion ---
function initialiserDeconnexion() {
    const deconnexionBtn = document.getElementById('deconnexion-btn');
    if (deconnexionBtn) {
        deconnexionBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = 'main.html';
        });
    }
}

function showDeconnexionMessage() {
    // Créer un overlay de déconnexion
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(5px);
    `;
    
    const messageBox = document.createElement('div');
    messageBox.style.cssText = `
        background: white;
        padding: 40px;
        border-radius: 18px;
        text-align: center;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        max-width: 400px;
        animation: fadeInUp 0.5s ease;
    `;
    
    messageBox.innerHTML = `
        <div style="font-size: 3em; color: #ef4444; margin-bottom: 20px;">
            <i class="fa fa-sign-out-alt"></i>
        </div>
        <h2 style="color: #374151; margin-bottom: 12px;">${translations[currentLanguage].deconnexionEnCours}</h2>
        <p style="color: #64748b; margin-bottom: 20px;">${translations[currentLanguage].redirectionConnexion}</p>
        <div style="display: flex; justify-content: center;">
            <div style="width: 40px; height: 40px; border: 3px solid #f3f4f6; border-top: 3px solid #ef4444; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        </div>
    `;
    
    // Ajouter les styles CSS pour les animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
    
    overlay.appendChild(messageBox);
    document.body.appendChild(overlay);
}

// --- Fonctionnalités de la section Aide ---
function initialiserAide() {
    // Suppression de l'ouverture automatique de toutes les FAQ
    document.querySelectorAll('.faq-item').forEach(item => item.classList.remove('active'));
    // Gestion des FAQ accordéon
    const faqQuestions = document.querySelectorAll('.faq-question');
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const faqItem = this.parentElement;
            const isActive = faqItem.classList.contains('active');
            // Fermer toutes les autres FAQ
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
            });
            // Ouvrir/fermer la FAQ cliquée
            if (!isActive) {
                faqItem.classList.add('active');
            }
        });
    });
    
    // Gestion de la recherche
    const searchInput = document.getElementById('aide-search-input');
    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question span').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer p').textContent.toLowerCase();
                
                if(question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                    // Mettre en surbrillance le terme recherché
                    if(searchTerm.length > 2) {
                        highlightText(item, searchTerm);
                    }
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    
    // Gestion du bouton support
    const btnSupport = document.querySelector('.btn-support');
    if(btnSupport) {
        btnSupport.addEventListener('click', function() {
            // Ouvrir une nouvelle fenêtre avec les informations de contact
            const contactInfo = `
                Email: support@meetingplanner.com
                Téléphone: +33 1 23 45 67 89
                Horaires: Lundi-Vendredi 9h-18h
            `;
            alert('Informations de contact :\n\n' + contactInfo);
        });
    }
}

function highlightText(element, searchTerm) {
    const question = element.querySelector('.faq-question span');
    const answer = element.querySelector('.faq-answer p');
    
    // Supprimer les anciennes surbrillances
    question.innerHTML = question.innerHTML.replace(/<mark[^>]*>(.*?)<\/mark>/g, '$1');
    answer.innerHTML = answer.innerHTML.replace(/<mark[^>]*>(.*?)<\/mark>/g, '$1');
    
    // Ajouter les nouvelles surbrillances
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    question.innerHTML = question.innerHTML.replace(regex, '<mark style="background: #fbbf24; color: white; padding: 2px 4px; border-radius: 4px;">$1</mark>');
    answer.innerHTML = answer.innerHTML.replace(regex, '<mark style="background: #fbbf24; color: white; padding: 2px 4px; border-radius: 4px;">$1</mark>');
}

// --- Fonctions pour les statistiques détaillées ---
function mettreAJourStatistiques() {
    // Statistiques générales
    const totalReunions = reunions.length;
    const reunionsActives = reunions.filter(r => r.statut === 'active').length;
    const reunionsAnnulees = reunions.filter(r => r.statut === 'annulée').length;
    const tauxAnnulation = totalReunions > 0 ? Math.round((reunionsAnnulees / totalReunions) * 100) : 0;
    const totalUtilisateurs = utilisateurs.length;
    
    // Mettre à jour les compteurs
    const statTotalReunions = document.getElementById('stat-total-reunions');
    const statReunionsActives = document.getElementById('stat-reunions-actives');
    const statTauxAnnulation = document.getElementById('stat-taux-annulation');
    const statTotalUtilisateurs = document.getElementById('stat-total-utilisateurs');
    
    if(statTotalReunions) statTotalReunions.textContent = totalReunions;
    if(statReunionsActives) statReunionsActives.textContent = reunionsActives;
    if(statTauxAnnulation) statTauxAnnulation.textContent = tauxAnnulation + '%';
    if(statTotalUtilisateurs) statTotalUtilisateurs.textContent = totalUtilisateurs;
    
    // Générer les graphiques
    genererGraphiqueMois();
    genererGraphiqueTypes();
    genererGraphiqueJours();
    genererListeRecentes();
}

function genererGraphiqueMois() {
    const graphiqueDiv = document.getElementById('graphique-mois');
    if(!graphiqueDiv) return;
    
    // Obtenir les 6 derniers mois
    const mois = [];
    const maintenant = new Date();
    for(let i = 5; i >= 0; i--) {
        const date = new Date(maintenant.getFullYear(), maintenant.getMonth() - i, 1);
        mois.push({
            nom: date.toLocaleDateString('fr-FR', { month: 'short' }),
            annee: date.getFullYear(),
            mois: date.getMonth()
        });
    }
    
    // Compter les réunions par mois
    const donnees = mois.map(m => {
        const count = reunions.filter(r => {
            const dateReunion = new Date(r.dateCreation);
            return dateReunion.getFullYear() === m.annee && dateReunion.getMonth() === m.mois;
        }).length;
        return { nom: m.nom, count };
    });
    
    // Trouver la valeur maximale pour l'échelle
    const maxCount = Math.max(...donnees.map(d => d.count), 1);
    
    // Générer les barres
    graphiqueDiv.innerHTML = donnees.map(donnee => {
        const hauteur = (donnee.count / maxCount) * 80;
        return `
            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                <div style="width:100%; background:linear-gradient(to top, #dc2626, #f87171); border-radius:4px 4px 0 0; min-height:4px; height:${hauteur}px;"></div>
                <div style="font-size:0.8em; color:#64748b; margin-top:8px; text-align:center;">${donnee.nom}</div>
                <div style="font-size:0.7em; color:#dc2626; font-weight:bold;">${donnee.count}</div>
            </div>
        `;
    }).join('');
}

function genererGraphiqueTypes() {
    const graphiqueDiv = document.getElementById('graphique-types');
    if(!graphiqueDiv) return;
    
    // Compter les types de réunions
    const types = {};
    reunions.forEach(reunion => {
        types[reunion.type] = (types[reunion.type] || 0) + 1;
    });
    
    const donnees = Object.entries(types).map(([type, count]) => ({ type, count }));
    
    if(donnees.length === 0) {
        graphiqueDiv.innerHTML = '<div style="color:#64748b; font-style:italic; text-align:center; width:100%;">Aucune donnée</div>';
        return;
    }
    
    // Trouver la valeur maximale pour l'échelle
    const maxCount = Math.max(...donnees.map(d => d.count));
    
    // Couleurs pour les types
    const couleurs = ['#fbbf24', '#f59e42', '#eab308', '#d97706'];
    
    // Générer les barres
    graphiqueDiv.innerHTML = donnees.map((donnee, index) => {
        const hauteur = (donnee.count / maxCount) * 80;
        const couleur = couleurs[index % couleurs.length];
        return `
            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                <div style="width:100%; background:${couleur}; border-radius:4px 4px 0 0; min-height:4px; height:${hauteur}px;"></div>
                <div style="font-size:0.8em; color:#64748b; margin-top:8px; text-align:center;">${donnee.type}</div>
                <div style="font-size:0.7em; color:#f59e42; font-weight:bold;">${donnee.count}</div>
            </div>
        `;
    }).join('');
}

function genererGraphiqueJours() {
    const graphiqueDiv = document.getElementById('graphique-jours');
    if(!graphiqueDiv) return;
    
    const jours = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    const donnees = jours.map(jour => {
        const count = reunions.filter(r => {
            const dateReunion = new Date(r.date + 'T' + r.heure);
            const jourSemaine = dateReunion.getDay();
            const jourIndex = jourSemaine === 0 ? 6 : jourSemaine - 1; // Lundi = 0
            return jours[jourIndex] === jour;
        }).length;
        return { jour, count };
    });
    
    // Trouver la valeur maximale pour l'échelle
    const maxCount = Math.max(...donnees.map(d => d.count), 1);
    
    // Générer les barres
    graphiqueDiv.innerHTML = donnees.map(donnee => {
        const hauteur = (donnee.count / maxCount) * 60;
        return `
            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                <div style="width:100%; background:linear-gradient(to top, #dc2626, #f87171); border-radius:4px 4px 0 0; min-height:4px; height:${hauteur}px;"></div>
                <div style="font-size:0.8em; color:#64748b; margin-top:8px; text-align:center;">${donnee.jour}</div>
                <div style="font-size:0.7em; color:#dc2626; font-weight:bold;">${donnee.count}</div>
            </div>
        `;
    }).join('');
}

function genererListeRecentes() {
    const listeDiv = document.getElementById('liste-recentes');
    if(!listeDiv) return;
    
    // Récupérer les 5 réunions les plus récentes
    const recentes = reunions
        .sort((a, b) => new Date(b.dateCreation) - new Date(a.dateCreation))
        .slice(0, 5);
    
    if(recentes.length === 0) {
        listeDiv.innerHTML = '<div style="color:#64748b; font-style:italic;">Aucune réunion récente</div>';
        return;
    }
    
    const listeHTML = recentes.map(reunion => {
        const dateCreation = new Date(reunion.dateCreation).toLocaleDateString('fr-FR');
        const dateReunion = new Date(reunion.date + 'T' + reunion.heure).toLocaleDateString('fr-FR');
        const statutColor = reunion.statut === 'active' ? '#22c55e' : '#ef4444';
        const statutText = reunion.statut === 'active' ? 'Active' : 'Annulée';
        
        return `
            <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #f1f5f9;">
                <div>
                    <div style="font-weight:bold; color:#374151;">${reunion.titre}</div>
                    <div style="font-size:0.9em; color:#64748b;">Créée le ${dateCreation} • Programmée le ${dateReunion}</div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="color:${statutColor}; font-weight:bold; font-size:0.9em;">${statutText}</span>
                    <span style="color:#f59e42; font-size:0.9em;">${reunion.type}</span>
                </div>
            </div>
        `;
    }).join('');
    
    listeDiv.innerHTML = listeHTML;
} 

// === Gestion des paramètres utilisateur (profil) ===
function getProfilInputs() {
    const section = document.querySelector('#parametres .param-section');
    if (!section) return null;
    const inputs = section.querySelectorAll('input[type="text"], input[type="email"], select');
    return inputs;
}

function chargerProfil() {
    const data = JSON.parse(localStorage.getItem('profilUtilisateur'));
    const inputs = getProfilInputs();
    if (inputs && data) {
        let i = 0;
        inputs.forEach(input => {
            if (input.type === 'text') input.value = data.nom || '';
            if (input.type === 'email') input.value = data.email || '';
            if (input.tagName === 'SELECT') input.value = data.fuseau || '';
            i++;
        });
    }
}

function sauvegarderProfil() {
    const inputs = getProfilInputs();
    if (!inputs) return;
    let data = {};
    inputs.forEach(input => {
        if (input.type === 'text') data.nom = input.value;
        if (input.type === 'email') data.email = input.value;
        if (input.tagName === 'SELECT') data.fuseau = input.value;
    });
    localStorage.setItem('profilUtilisateur', JSON.stringify(data));
    alert('Profil sauvegardé !');
}

// Ajout de l'écouteur sur le bouton Sauvegarder du profil
window.addEventListener('DOMContentLoaded', function() {
    chargerProfil();
    const btns = document.querySelectorAll('#parametres .param-section .btn-create');
    if (btns && btns.length > 0) {
        // Le premier bouton est pour le profil utilisateur
        btns[0].addEventListener('click', function(e) {
            e.preventDefault();
            sauvegarderProfil();
        });
    }
    // Scroll et ouverture automatique sur les catégories d'aide
    document.querySelectorAll('.aide-categories .category-card').forEach(card => {
        card.addEventListener('click', function() {
            const target = this.getAttribute('data-target');
            // Trouver la catégorie à partir du target (ex: #faq-planification => planification)
            let categorie = null;
            if(target && target.startsWith('#faq-')) {
                categorie = target.replace('#faq-', '');
            }
            if(categorie) {
                // Fermer toutes les FAQ
                document.querySelectorAll('.faq-item').forEach(item => item.classList.remove('active'));
                // Ouvrir toutes les FAQ de la catégorie
                document.querySelectorAll('.faq-item[data-categorie="'+categorie+'"]').forEach(item => item.classList.add('active'));
                // Scroll vers la première FAQ de la catégorie
                const el = document.querySelector(target);
                if(el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    });
}); 

// Vérifie que toutes les réunions sont toujours enregistrées dans localStorage
// (déjà fait via sauvegarderReunions dans ajouterReunions et annulerReunions)
// Ajoute une fonction pour forcer la sauvegarde à chaque chargement
document.getElementById('btn-ajouter-user').onclick = function() {
    var formContainer = document.getElementById('form-ajout-user-container');
    formContainer.style.display = (formContainer.style.display === 'none' || formContainer.style.display === '') ? 'block' : 'none';
};

function showMeetingDetails(data) {
    // Remplir le contenu de la modale
    document.getElementById('modal-details-body').innerHTML = `
        <p><strong>Titre :</strong> ${data.titre}</p>
        <p><strong>Date :</strong> ${data.date}</p>
        <p><strong>Heure :</strong> ${data.heure}</p>
        <p><strong>Type :</strong> ${data.type}</p>
        <p><strong>Description :</strong><br>${data.description ? data.description : '<em>Aucune</em>'}</p>
    `;
    document.getElementById('modal-details').style.display = 'flex';
}

// Fermer la modale
document.getElementById('close-modal-details').onclick = function() {
    document.getElementById('modal-details').style.display = 'none';
};
// Fermer en cliquant sur le fond
document.getElementById('modal-details').onclick = function(e) {
    if (e.target === this) this.style.display = 'none';
};