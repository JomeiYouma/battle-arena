/**
 * SINGLE PLAYER COMBAT
 * Gestion du combat solo contre l'IA
 */

// Track previous stats for animation
let previousStats = { player: null, enemy: null };

/**
 * Create a stat span with animation class if value changed
 */
function createStatSpan(label, value, prevValue, instant) {
    let animClass = '';
    if (!instant && prevValue !== null && prevValue !== undefined) {
        if (value > prevValue) animClass = 'stat-up';
        else if (value < prevValue) animClass = 'stat-down';
    }
    return `<span class="stat-value ${animClass}">${value} ${label}</span>`;
}

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
        const prev = previousStats.player;
        heroStats.innerHTML = 
            createStatSpan('PV', `${states.player.pv}/${states.player.basePv}`, prev ? `${prev.pv}/${prev.basePv}` : null, instant) + ' | ' +
            createStatSpan('ATK', states.player.atk, prev?.atk, instant) + ' | ' +
            createStatSpan('DEF', states.player.def, prev?.def, instant) + ' | ' +
            createStatSpan('SPE', states.player.speed, prev?.speed, instant);
        previousStats.player = { ...states.player };
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
        const prev = previousStats.enemy;
        enemyStats.innerHTML = 
            createStatSpan('PV', `${states.enemy.pv}/${states.enemy.basePv}`, prev ? `${prev.pv}/${prev.basePv}` : null, instant) + ' | ' +
            createStatSpan('ATK', states.enemy.atk, prev?.atk, instant) + ' | ' +
            createStatSpan('DEF', states.enemy.def, prev?.def, instant) + ' | ' +
            createStatSpan('SPE', states.enemy.speed, prev?.speed, instant);
        previousStats.enemy = { ...states.enemy };
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
