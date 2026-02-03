/**
 * ACCOUNT PAGE
 * Gestion des onglets et fonctionnalités du compte utilisateur
 */

/**
 * Changer d'onglet (appelé depuis un événement onclick)
 * @param {string} tabName - Nom de l'onglet
 */
function switchTab(tabName) {
    switchTabDirect(tabName, event.target);
}

/**
 * Changer d'onglet directement avec un élément bouton
 * @param {string} tabName - Nom de l'onglet
 * @param {HTMLElement} buttonElement - Élément bouton cliqué
 */
function switchTabDirect(tabName, buttonElement) {
    // Masquer tous les tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Afficher le tab sélectionné
    const tabElement = document.getElementById(tabName + '-tab');
    if (tabElement) {
        tabElement.classList.add('active');
    }
    if (buttonElement) {
        buttonElement.classList.add('active');
    }
}

/**
 * Confirmer la suppression d'une équipe
 * @param {string} teamName - Nom de l'équipe à supprimer
 * @returns {boolean} - True si confirmé
 */
function confirmDelete(teamName) {
    return confirm(`Êtes-vous sûr de vouloir supprimer l'équipe "${teamName}" ?`);
}

/**
 * Initialiser la page de compte
 * @param {Object} config - Configuration depuis PHP
 * @param {string} config.postTabToSelect - Onglet à sélectionner après une action POST
 */
function initAccountPage(config = {}) {
    // Initialiser le système de tooltip
    if (typeof initializeTooltipSystem === 'function') {
        initializeTooltipSystem();
    }
    
    // Priorité 1: Paramètre ?tab= dans l'URL
    const urlTab = new URLSearchParams(window.location.search).get('tab');
    // Priorité 2: Action POST (ajouter un héros -> teams)
    const postTabToSelect = config.postTabToSelect || '';
    
    const tabToSelect = urlTab || postTabToSelect || 'stats';
    const validTabs = ['stats', 'stats5v5', 'teams'];
    
    if (validTabs.includes(tabToSelect)) {
        // Trouver le bon bouton
        const buttons = document.querySelectorAll('.tab-button');
        const tabIndex = validTabs.indexOf(tabToSelect);
        if (buttons[tabIndex]) {
            switchTabDirect(tabToSelect, buttons[tabIndex]);
        }
    }
}

// Exporter pour utilisation globale
window.switchTab = switchTab;
window.switchTabDirect = switchTabDirect;
window.confirmDelete = confirmDelete;
window.initAccountPage = initAccountPage;
