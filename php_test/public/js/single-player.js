/**
 * SINGLE PLAYER COMBAT
 * Gestion du combat solo contre l'IA
 */

// Fonctions de mise à jour de l'UI
function updateStats(states, instant = false) {
    if (!states) return;
    
    // Mise à jour Hero
    const heroPvBar = document.getElementById('heroPvBar');
    const heroStats = document.getElementById('heroStats');
    if (heroPvBar && states.player) {
        if (instant) heroPvBar.style.transition = 'none';
        const pct = (states.player.pv / states.player.basePv) * 100;
        heroPvBar.style.width = pct + '%';
        if (instant) heroPvBar.offsetHeight; // Force reflow
        if (instant) heroPvBar.style.transition = '';
    }
    if (heroStats && states.player) {
        heroStats.textContent = `${states.player.pv}/${states.player.basePv} PV | ${states.player.atk} ATK | ${states.player.def} DEF | ${states.player.speed} SPE`;
    }
    
    // Mise à jour Enemy
    const enemyPvBar = document.getElementById('enemyPvBar');
    const enemyStats = document.getElementById('enemyStats');
    if (enemyPvBar && states.enemy) {
        if (instant) enemyPvBar.style.transition = 'none';
        const pct = (states.enemy.pv / states.enemy.basePv) * 100;
        enemyPvBar.style.width = pct + '%';
        if (instant) enemyPvBar.offsetHeight; // Force reflow
        if (instant) enemyPvBar.style.transition = '';
    }
    if (enemyStats && states.enemy) {
        enemyStats.textContent = `${states.enemy.pv}/${states.enemy.basePv} PV | ${states.enemy.atk} ATK | ${states.enemy.def} DEF | ${states.enemy.speed} SPE`;
    }
}

// Configuration pour le système d'animations partagé
let isPlayingAnimations = false;
const combatAnimConfig = {
    isMyAction: (actor) => actor === 'player',
    getActorContainer: (isMe) => document.getElementById(isMe ? 'heroEmojiContainer' : 'enemyEmojiContainer'),
    getTargetContainer: (isMe) => document.getElementById(isMe ? 'enemyEmojiContainer' : 'heroEmojiContainer'),
    getActorFighter: (isMe) => document.getElementById(isMe ? 'heroFighter' : 'enemyFighter'),
    getTargetFighter: (isMe) => document.getElementById(isMe ? 'enemyFighter' : 'heroFighter'),
    updateStats: (states) => updateStats(states),
    triggerHitAnimation: (fighter) => {
        if (!fighter) return;
        fighter.classList.add('fighter-hit');
        setTimeout(() => fighter.classList.remove('fighter-hit'), 400);
    }
};

/**
 * Afficher la section game over
 */
function showGameOver() {
    const gameOver = document.getElementById('gameOverSection');
    const actionForm = document.getElementById('actionForm');
    if (gameOver) {
        if (actionForm) {
            actionForm.style.display = 'none';
        }
        gameOver.style.display = 'block';
    }
}

/**
 * Scroller les logs vers le bas
 */
function scrollLogsToBottom() {
    const logBox = document.getElementById('logBox');
    if (logBox) {
        logBox.scrollTop = logBox.scrollHeight;
    }
}

/**
 * Initialiser le combat solo avec les données du serveur
 * @param {Object} config - Configuration du combat
 * @param {Array} config.turnActions - Actions du tour à animer
 * @param {Object} config.initialStates - États initiaux des combattants
 * @param {string} config.heroName - Nom du héros
 * @param {string} config.enemyName - Nom de l'ennemi
 */
function initSinglePlayerCombat(config) {
    const { turnActions, initialStates, heroName, enemyName } = config;
    
    // Wrapper pour intégrer avec le reste du code single_player
    async function playTurnAnimationsWrapper() {
        if (!turnActions || turnActions.length === 0) {
            showGameOver();
            return;
        }
        await playTurnAnimations(turnActions);
        showGameOver();
    }

    // Appliquer les états initiaux IMMÉDIATEMENT sans transition
    if (initialStates && Object.keys(initialStates).length > 0) {
        updateStats(initialStates, true);
    }

    // Scroller immédiatement (avant les animations)
    scrollLogsToBottom();

    // Lancer les animations au chargement puis scroller les logs
    window.addEventListener('load', async () => {
        await playTurnAnimationsWrapper();
        scrollLogsToBottom();
    });
}
