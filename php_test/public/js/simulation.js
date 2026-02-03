/**
 * SIMULATION PAGE
 * Gestion des onglets et formulaires de simulation
 */

// Configuration globale (sera initialisée par initSimulation)
let NB_PERSONNAGES = 0;
let NB_BLESSINGS = 0;
let NB_MATCHUPS_CLASSIC = 0;
let COMBATS_PER_SECOND_CLASSIC = 3600 / 3; // 3600 combats en 3 secondes (classique)
let COMBATS_PER_SECOND_BLESSING = 3600 / 5; // 3600 combats en 5 secondes (bénédictions)
let activateBlessingTab = false;

// Loading screen pour les formulaires
const loadingMessages = [
    "Préparation des combats...",
    "Les guerriers s'échauffent...",
    "Analyse des stratégies...",
    "Calcul des statistiques...",
    "Simulation en cours...",
    "Les bénédictions s'activent...",
    "Combat épique en cours..."
];

let messageIndex = 0;
let loadingInterval;

/**
 * Formater le temps en secondes/minutes
 */
function formatTime(seconds) {
    if (seconds < 60) {
        return `~${Math.ceil(seconds)}s`;
    } else {
        const mins = Math.floor(seconds / 60);
        const secs = Math.ceil(seconds % 60);
        return `~${mins}min ${secs}s`;
    }
}

/**
 * Calculer le nombre de combats pour le mode bénédictions
 */
function calculateBlessingCombats() {
    const heroSelect = document.getElementById('hero_select').value;
    const blessingSelect = document.getElementById('blessing_select').value;
    const opponentMode = document.getElementById('opponent_blessing_mode').value;
    const combatsPerMatchup = parseInt(document.getElementById('combats_count_blessing').value) || 10;
    
    // Nombre de héros testés
    const nbHeroes = (heroSelect === 'all') ? NB_PERSONNAGES : 1;
    
    // Nombre de bénédictions testées pour le héros
    const nbHeroBlessings = (blessingSelect === 'all') ? NB_BLESSINGS : 1;
    
    // Nombre d'adversaires (tous sauf le héros lui-même)
    const nbOpponents = NB_PERSONNAGES - 1;
    
    // Nombre de bénédictions adverses
    const nbOpponentBlessings = (opponentMode === 'all') ? NB_BLESSINGS : 1;
    
    // Total = héros * bénédictions_héros * adversaires * bénédictions_adverses * combats_par_matchup
    return nbHeroes * nbHeroBlessings * nbOpponents * nbOpponentBlessings * combatsPerMatchup;
}

/**
 * Mise à jour du total de combats en temps réel (classique)
 */
function updateClassicEstimate() {
    const combatsInput = document.getElementById('combats_count');
    if (!combatsInput) return;
    
    const combats = parseInt(combatsInput.value) || 0;
    const totalCombats = combats * NB_MATCHUPS_CLASSIC;
    
    const totalEl = document.getElementById('totalCombats');
    if (totalEl) totalEl.textContent = totalCombats.toLocaleString('fr-FR');
    
    const estimatedSeconds = totalCombats / COMBATS_PER_SECOND_CLASSIC;
    const timeEl = document.getElementById('timeEstimateClassic');
    if (timeEl) timeEl.textContent = formatTime(estimatedSeconds);
}

/**
 * Mise à jour du total de combats en temps réel (bénédictions)
 */
function updateBlessingEstimate() {
    const totalCombats = calculateBlessingCombats();
    
    const totalEl = document.getElementById('totalCombatsBlessing');
    if (totalEl) totalEl.textContent = totalCombats.toLocaleString('fr-FR');
    
    const estimatedSeconds = totalCombats / COMBATS_PER_SECOND_BLESSING;
    const timeEl = document.getElementById('timeEstimateBlessing');
    if (timeEl) timeEl.textContent = formatTime(estimatedSeconds);
}

/**
 * Afficher l'écran de chargement
 */
function showLoading(totalCombats, isBlessing = false) {
    const overlay = document.getElementById('loadingOverlay');
    const subtext = document.getElementById('loadingSubtext');
    const combatCountEl = document.getElementById('loadingCombatCount');
    const timeEstimateEl = document.getElementById('loadingTimeEstimate');
    
    if (!overlay) return;
    
    // Afficher le nombre de combats
    if (combatCountEl) combatCountEl.textContent = totalCombats.toLocaleString('fr-FR') + ' combats';
    
    // Calculer et afficher le temps estimé
    const combatsPerSecond = isBlessing ? COMBATS_PER_SECOND_BLESSING : COMBATS_PER_SECOND_CLASSIC;
    const estimatedSeconds = totalCombats / combatsPerSecond;
    if (timeEstimateEl) timeEstimateEl.textContent = 'Temps estimé : ' + formatTime(estimatedSeconds);
    
    overlay.classList.add('active');
    
    // Changer le message toutes les 2 secondes
    loadingInterval = setInterval(() => {
        messageIndex = (messageIndex + 1) % loadingMessages.length;
        if (subtext) subtext.textContent = loadingMessages[messageIndex];
    }, 2000);
}

/**
 * Configuration des onglets
 */
function setupTabs() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Désactiver tous les onglets
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Activer l'onglet cliqué
            this.classList.add('active');
            const tabId = 'tab-' + this.dataset.tab;
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Activer automatiquement l'onglet bénédiction si résultats présents
    if (activateBlessingTab) {
        const blessingBtn = document.querySelector('[data-tab="blessing"]');
        if (blessingBtn) blessingBtn.click();
    }
}

/**
 * Configuration des formulaires
 */
function setupForms() {
    // Événement input sur le formulaire classique
    const combatsInput = document.getElementById('combats_count');
    if (combatsInput) {
        combatsInput.addEventListener('input', updateClassicEstimate);
    }
    
    // Attacher l'événement au formulaire classique
    const classicForm = document.querySelector('#tab-classic .simulation-form');
    if (classicForm) {
        classicForm.addEventListener('submit', function(e) {
            const combatsPerMatchup = parseInt(document.getElementById('combats_count').value) || 10;
            const totalCombats = NB_MATCHUPS_CLASSIC * combatsPerMatchup;
            showLoading(totalCombats, false);
        });
    }
    
    // Attacher l'événement au formulaire bénédictions
    const blessingForm = document.querySelector('#tab-blessing .simulation-form');
    if (blessingForm) {
        blessingForm.addEventListener('submit', function(e) {
            const totalCombats = calculateBlessingCombats();
            showLoading(totalCombats, true);
        });
    }
    
    // Événements pour mise à jour en temps réel du formulaire bénédictions
    const heroSelect = document.getElementById('hero_select');
    const blessingSelect = document.getElementById('blessing_select');
    const opponentMode = document.getElementById('opponent_blessing_mode');
    const combatsBlessingInput = document.getElementById('combats_count_blessing');
    
    if (heroSelect) heroSelect.addEventListener('change', updateBlessingEstimate);
    if (blessingSelect) blessingSelect.addEventListener('change', updateBlessingEstimate);
    if (opponentMode) opponentMode.addEventListener('change', updateBlessingEstimate);
    if (combatsBlessingInput) combatsBlessingInput.addEventListener('input', updateBlessingEstimate);
}

/**
 * Initialiser la page de simulation
 * @param {Object} config - Configuration depuis PHP
 * @param {number} config.nbPersonnages - Nombre de personnages
 * @param {number} config.nbBlessings - Nombre de bénédictions
 * @param {number} config.nbMatchups - Nombre de matchups classiques
 * @param {boolean} config.hasBlessingResults - Si des résultats de bénédiction existent
 */
function initSimulation(config) {
    NB_PERSONNAGES = config.nbPersonnages || 0;
    NB_BLESSINGS = config.nbBlessings || 0;
    NB_MATCHUPS_CLASSIC = config.nbMatchups || (NB_PERSONNAGES * (NB_PERSONNAGES - 1)) / 2;
    activateBlessingTab = config.hasBlessingResults || false;
    
    // Initialiser les composants
    setupTabs();
    setupForms();
    
    // Initialiser les estimations
    updateClassicEstimate();
    updateBlessingEstimate();
}

// Exporter pour utilisation globale
window.initSimulation = initSimulation;
