/**
 * MULTIPLAYER COMBAT - Main JavaScript
 * Gestion du combat multijoueur (1v1 et 5v5)
 */

// ============ CONFIGURATION ============
// Ces variables sont définies dans le HTML par PHP
let MATCH_ID = '';
let INITIAL_STATE = {};
let IS_P1 = true;
let IS_5V5 = false;
let IS_TEST_UI = false;
let TEAM_DATA_P1 = [];
let TEAM_DATA_P2 = [];
let ASSET_BASE_PATH = '';

let pollInterval = null;
let lastLogCount = 0;
let currentGameState = null;
let lastTurnProcessed = 1;
let isPlayingAnimations = false;
let isSwitchModalManuallyOpen = false;

// Timer system
let actionTimer = null;
let timeRemaining = 60;
let isTimerRunning = false;
const RANDOM_ACTION_THRESHOLD = 0;
const FORFEIT_THRESHOLD = -65;

/**
 * Initialiser le module avec les paramètres PHP
 */
function initMultiplayerCombat(config) {
    MATCH_ID = config.matchId;
    INITIAL_STATE = config.initialState;
    IS_P1 = config.isP1;
    IS_5V5 = config.is5v5;
    IS_TEST_UI = config.isTestUI;
    TEAM_DATA_P1 = config.teamDataP1;
    TEAM_DATA_P2 = config.teamDataP2;
    ASSET_BASE_PATH = config.assetBasePath;
    
    currentGameState = INITIAL_STATE;
    lastLogCount = INITIAL_STATE.logs ? INITIAL_STATE.logs.length : 0;
    lastTurnProcessed = INITIAL_STATE.turn || 1;
    
    // Initialiser les sidebars si 5v5
    if (IS_5V5) {
        initializeTeamSidebars();
    }
    
    // Démarrer le polling si pas en mode test
    if (!IS_TEST_UI) {
        updateCombatState();
        pollInterval = setInterval(updateCombatState, 2000);
    } else {
        console.log('Test UI mode - polling désactivé');
    }
    
    // Ajouter le CSS dynamique
    addDynamicStyles();
}

// ============ TIMER SYSTEM ============

function startActionTimer() {
    if (isTimerRunning) return;
    isTimerRunning = true;
    timeRemaining = 60;
    updateTimerDisplay();
    document.getElementById('infoPanel').style.display = 'flex';
    
    actionTimer = setInterval(() => {
        timeRemaining--;
        updateTimerDisplay();
        
        if (timeRemaining === RANDOM_ACTION_THRESHOLD) {
            playRandomAction();
        }
        if (timeRemaining <= FORFEIT_THRESHOLD) {
            triggerForfeit();
        }
    }, 1000);
}

function stopActionTimer() {
    if (actionTimer) {
        clearInterval(actionTimer);
        actionTimer = null;
    }
    isTimerRunning = false;
    document.getElementById('infoPanel').style.display = 'none';
}

function updateTimerDisplay() {
    const timerValue = document.getElementById('timerValue');
    const timerContainer = document.getElementById('actionTimer');
    const timerProgress = document.getElementById('timerProgress');
    
    const displayTime = Math.max(0, timeRemaining);
    timerValue.textContent = displayTime;
    
    const circumference = 200;
    const offset = circumference - (timeRemaining / 60) * circumference;
    const clampedOffset = Math.max(0, Math.min(circumference, offset));
    
    if (timerProgress) {
        timerProgress.style.strokeDashoffset = clampedOffset;
    }
    
    if (timeRemaining <= 10) {
        timerContainer.classList.add('timer-critical');
    } else {
        timerContainer.classList.remove('timer-critical');
    }
}

function playRandomAction() {
    const availableButtons = document.querySelectorAll('.action-btn:not(.disabled)');
    if (availableButtons.length > 0) {
        const randomIndex = Math.floor(Math.random() * availableButtons.length);
        availableButtons[randomIndex].click();
    }
}

function triggerForfeit() {
    stopActionTimer();
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    const input = document.createElement('input');
    input.name = 'abandon_multi';
    input.value = '1';
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// ============ ANIMATIONS ============

function triggerHitAnimation(fighterElement) {
    if (!fighterElement) return;
    fighterElement.classList.add('fighter-hit');
    setTimeout(() => fighterElement.classList.remove('fighter-hit'), 400);
}

function triggerSwitchAnimation(fighterElement, newImgSrc) {
    if (!fighterElement) return;
    
    const img = fighterElement.querySelector('img');
    if (!img) return;
    
    const isEnemy = img.classList.contains('enemy-img') || fighterElement.id === 'oppFighter';
    
    fighterElement.classList.add('switch-out');
    
    const switchEmoji = document.createElement('div');
    switchEmoji.className = 'switch-emoji-animation';
    switchEmoji.textContent = '🔄';
    fighterElement.appendChild(switchEmoji);
    
    setTimeout(() => {
        img.src = newImgSrc;
        if (isEnemy) {
            img.classList.add('enemy-img');
        }
        fighterElement.classList.remove('switch-out');
        fighterElement.classList.add('switch-in');
        
        setTimeout(() => {
            fighterElement.classList.remove('switch-in');
            if (switchEmoji.parentElement) {
                switchEmoji.remove();
            }
        }, 500);
    }, 400);
}

function setupActionTooltips() {
    document.querySelectorAll('.action-btn[data-description]').forEach(btn => {
        const desc = btn.getAttribute('data-description');
        if (desc && !btn.hasAttribute('data-tooltip')) {
            btn.setAttribute('data-tooltip', desc);
        }
    });
}

// ============ COMBAT ANIMATION CONFIG ============

const combatAnimConfig = {
    isMyAction: (actor) => {
        const actorIsP1 = actor === 'player';
        return (actorIsP1 === IS_P1);
    },
    getActorContainer: (isMe) => {
        return isMe 
            ? document.getElementById('myEmojiContainer') 
            : document.getElementById('oppEmojiContainer');
    },
    getTargetContainer: (isMe) => {
        return isMe 
            ? document.getElementById('oppEmojiContainer') 
            : document.getElementById('myEmojiContainer');
    },
    getActorFighter: (isMe) => {
        return isMe 
            ? document.getElementById('myFighter') 
            : document.getElementById('oppFighter');
    },
    getTargetFighter: (isMe) => {
        return isMe 
            ? document.getElementById('oppFighter') 
            : document.getElementById('myFighter');
    },
    updateStats: (states) => updateStatsFromAction(states),
    triggerHitAnimation: (fighter) => {
        if (!fighter) return;
        fighter.classList.add('fighter-hit');
        setTimeout(() => fighter.classList.remove('fighter-hit'), 400);
    }
};

function updateStatsFromAction(states) {
    const myKey = IS_P1 ? 'player' : 'enemy';
    const oppKey = IS_P1 ? 'enemy' : 'player';
    
    if (states[myKey]) {
        const bar = document.getElementById('myPvBar');
        const stats = document.getElementById('myStats');
        if (bar) bar.style.width = (states[myKey].pv / states[myKey].basePv * 100) + '%';
        if (stats) stats.innerText = Math.round(states[myKey].pv) + " / " + states[myKey].basePv + " | ATK: " + states[myKey].atk + " | DEF: " + states[myKey].def;
    }
    if (states[oppKey]) {
        const bar = document.getElementById('oppPvBar');
        const stats = document.getElementById('oppStats');
        if (bar) bar.style.width = (states[oppKey].pv / states[oppKey].basePv * 100) + '%';
        if (stats) stats.innerText = Math.round(states[oppKey].pv) + " / " + states[oppKey].basePv + " | ATK: " + states[oppKey].atk + " | DEF: " + states[oppKey].def;
    }
}

function addDynamicStyles() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes emojiPop {
            0% { transform: scale(0.5); opacity: 1; }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

// ============ EFFECT INDICATORS ============

function updateEffectIndicators(effects, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (!effects || typeof effects !== 'object') return;
    
    for (const [name, effect] of Object.entries(effects)) {
        const indicator = document.createElement('div');
        indicator.className = 'effect-indicator' + (effect.isPending ? ' pending' : '');
        
        const emoji = document.createElement('span');
        emoji.className = 'effect-emoji';
        emoji.textContent = effect.emoji || '✨';
        indicator.appendChild(emoji);
        
        const duration = effect.duration || effect.turnsDelay || 0;
        if (duration > 0) {
            const badge = document.createElement('span');
            badge.className = 'effect-duration-badge';
            badge.textContent = duration;
            indicator.appendChild(badge);
        }
        
        const tooltip = document.createElement('div');
        tooltip.className = 'effect-tooltip';
        tooltip.innerHTML = `
            <div class="effect-tooltip-title">${effect.emoji || '✨'} ${name}</div>
            <div class="effect-tooltip-desc">${effect.description || name}</div>
            <div class="effect-tooltip-duration">${effect.isPending ? '⏳ Actif dans' : '⌛ Durée restante'}: ${duration} tour(s)</div>
        `;
        indicator.appendChild(tooltip);
        
        container.appendChild(indicator);
    }
}

// ============ COMBAT STATE POLLING ============

function updateCombatState() {
    if (IS_TEST_UI) {
        console.log('TEST UI MODE - Pas de polling');
        return;
    }

    fetch('../../api.php?action=poll_status&match_id=' + MATCH_ID, {
        credentials: 'same-origin'
    })
        .then(r => {
            if (!r.ok) {
                throw new Error(`HTTP ${r.status}: ${r.statusText}`);
            }
            return r.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error("Response is not valid JSON: " + text.substring(0, 200));
            }
        })
        .then(data => {
            if (!data) {
                showErrorMessage('Erreur: réponse vide du serveur');
                return;
            }
            
            if (data.status === 'error') {
                showErrorMessage("Erreur API: " + data.message);
                return;
            }

            currentGameState = data;

            const hasNewAnimations = data.turn > lastTurnProcessed && data.turnActions && data.turnActions.length > 0;
            if (hasNewAnimations) {
                lastTurnProcessed = data.turn;
                playTurnAnimations(data.turnActions);
            }

            document.getElementById('turnIndicator').innerText = "Tour " + data.turn;
            
            if (!hasNewAnimations && !isPlayingAnimations) {
                updatePlayerStats(data);
                updateOpponentStats(data);
                updateEffectIndicators(data.me.activeEffects, 'myEffects');
                updateEffectIndicators(data.opponent.activeEffects, 'oppEffects');
                updateHeroImages(data);
                
                if (IS_5V5 && data.myTeam && data.oppTeam) {
                    updateTeamSidebarsWithData(data.myTeam, data.oppTeam, data.myActiveIndex, data.oppActiveIndex);
                }
            }
            
            updateLogs(data);
            updateActionButtons(data);
            handleGameState(data);
        })
        .catch(err => {
            console.error('Poll error:', err);
            showErrorMessage('Erreur: ' + err.message);
        });
}

function updatePlayerStats(data) {
    document.getElementById('myName').innerText = data.me.name;
    document.getElementById('myType').innerText = data.me.type;
    document.getElementById('myStats').innerText = Math.round(data.me.pv) + " / " + data.me.max_pv + " | ATK: " + data.me.atk + " | DEF: " + data.me.def;
    document.getElementById('myPvBar').style.width = (data.me.pv / data.me.max_pv * 100) + "%";
}

function updateOpponentStats(data) {
    document.getElementById('oppName').innerText = data.opponent.name;
    document.getElementById('oppType').innerText = data.opponent.type;
    document.getElementById('oppStats').innerText = Math.round(data.opponent.pv) + " / " + data.opponent.max_pv + " | ATK: " + data.opponent.atk + " | DEF: " + data.opponent.def;
    document.getElementById('oppPvBar').style.width = (data.opponent.pv / data.opponent.max_pv * 100) + "%";
}

function updateHeroImages(data) {
    if (data.me.img) {
        const myFighterImg = document.querySelector('#myFighter img');
        if (myFighterImg && !myFighterImg.src.endsWith(data.me.img)) {
            triggerSwitchAnimation(document.getElementById('myFighter'), data.me.img);
        }
    }
    if (data.opponent.img) {
        const oppFighterImg = document.querySelector('#oppFighter img');
        if (oppFighterImg && !oppFighterImg.src.endsWith(data.opponent.img)) {
            triggerSwitchAnimation(document.getElementById('oppFighter'), data.opponent.img);
        }
    }
}

function updateLogs(data) {
    const logBox = document.getElementById('battleLog');
    if (data.logs && data.logs.length > lastLogCount) {
        for (let i = lastLogCount; i < data.logs.length; i++) {
            let div = document.createElement('div');
            div.className = 'log-line';
            div.innerText = data.logs[i];
            logBox.appendChild(div);
            
            if (IS_5V5 && data.logs[i].includes('🔄')) {
                updateSwitchedHeroCard(data.logs[i]);
            }
        }
        lastLogCount = data.logs.length;
        logBox.scrollTop = logBox.scrollHeight;
    }
}

function updateActionButtons(data) {
    const btnContainer = document.getElementById('actionButtons');
    const errorMsg = document.getElementById('errorMessage');
    
    if (errorMsg) {
        errorMsg.style.display = 'none';
    }
    
    if (data.actions && data.waiting_for_me && !data.isOver) {
        btnContainer.innerHTML = '';
        for (const [key, action] of Object.entries(data.actions)) {
            if (key === 'switch') continue;
            
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `action-btn ${key}${action.canUse ? '' : ' disabled'}`;
            if (action.description) {
                button.setAttribute('data-tooltip', action.description);
            }
            button.disabled = !action.canUse;
            button.onclick = () => sendAction(key);
            
            let buttonHTML = `<span class="action-emoji-icon">${action.emoji || '⚔️'}</span>`;
            buttonHTML += `<span class="action-label">${action.label}</span>`;
            if (action.ppText) {
                buttonHTML += `<span class="action-pp">${action.ppText}</span>`;
            }
            
            button.innerHTML = buttonHTML;
            btnContainer.appendChild(button);
        }
        
        const switchBtn = document.getElementById('switchBtn');
        if (switchBtn && data.actions.switch) {
            switchBtn.disabled = !data.actions.switch.canUse;
            switchBtn.classList.toggle('disabled', !data.actions.switch.canUse);
        }
        
        setupActionTooltips();
        startActionTimer();
    }
    
    if (IS_5V5 && data.needsForcedSwitch) {
        btnContainer.innerHTML = '';
        btnContainer.style.display = 'none';
        showSwitchMenu(true);
    } else {
        closeSwitchModal();
    }
}

function handleGameState(data) {
    const btnContainer = document.getElementById('actionButtons');
    const waitMsg = document.getElementById('waitingMessage');
    const gameOverMsg = document.getElementById('gameOverMessage');
    const actionContainer = document.getElementById('actionContainer');
    
    if (data.isOver) {
        clearInterval(pollInterval);
        stopActionTimer();
        closeSwitchModal(true);
        btnContainer.style.display = 'none';
        
        const switchBtn = document.getElementById('switchBtn');
        if (switchBtn) {
            switchBtn.style.display = 'none';
        }
        
        waitMsg.style.display = 'none';
        gameOverMsg.style.display = 'block';
        
        const gameOverText = document.getElementById('gameOverText');
        if (data.winner === 'you') {
            if (data.forfeit) {
                gameOverText.innerText = ' VICTOIRE PAR FORFAIT ! ';
            } else {
                gameOverText.innerText = '🎉 VICTOIRE ! 🎉';
            }
            gameOverText.className = 'victory-text';
        } else {
            gameOverText.innerText = '💀 DÉFAITE... 💀';
            gameOverText.className = 'defeat-text';
        }
    } else {
        gameOverMsg.style.display = 'none';
        
        if (data.waiting_for_me) {
            if (actionContainer) actionContainer.style.display = 'flex';
            if (btnContainer) btnContainer.style.display = '';
            waitMsg.style.display = 'none';
        } else {
            if (actionContainer) actionContainer.style.display = 'none';
            waitMsg.style.display = 'block';
            waitMsg.innerText = "En attente de l'adversaire...";
        }
    }
}

function showErrorMessage(message) {
    let errorMsg = document.getElementById('errorMessage');
    if (!errorMsg) {
        errorMsg = document.createElement('div');
        errorMsg.id = 'errorMessage';
        errorMsg.style.cssText = 'background: #ff4444; color: white; padding: 15px; margin: 10px 0; border-radius: 4px; text-align: center;';
        const controls = document.querySelector('.controls');
        controls.parentNode.insertBefore(errorMsg, controls);
    }
    errorMsg.innerHTML = message + '<br><button onclick="location.href=\'../../index.php\'" class="error-return-btn">Retour Menu</button>';
    errorMsg.style.display = 'block';
}

function sendAction(action) {
    const actionContainer = document.getElementById('actionContainer');
    const waitMsg = document.getElementById('waitingMessage');
    
    stopActionTimer();
    if (actionContainer) actionContainer.style.display = 'none';
    waitMsg.style.display = 'block';
    waitMsg.innerText = 'Envoi de l\'action...';
    
    fetch('../../api.php?action=submit_move', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'match_id=' + MATCH_ID + '&move=' + action
    })
        .then(r => r.text())
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Réponse invalide: ' + text.substring(0, 100));
            }
        })
        .then(data => {
            if (!data) {
                showErrorMessage('Erreur: réponse vide du serveur');
                if (actionContainer) actionContainer.style.display = 'flex';
                waitMsg.style.display = 'none';
                return;
            }
            
            if (data.status === 'error') {
                showErrorMessage("Erreur: " + (data.message || "Erreur inconnue"));
                if (actionContainer) actionContainer.style.display = 'flex';
                waitMsg.style.display = 'none';
            } else if (data.status === 'ok') {
                waitMsg.innerText = "En attente de l'adversaire...";
            }
        })
        .catch(err => {
            console.error('Action error:', err);
            showErrorMessage('Erreur: ' + err.message);
            if (actionContainer) actionContainer.style.display = 'flex';
            waitMsg.style.display = 'none';
        });
}

// ============ 5v5 TEAM MANAGEMENT ============

function toggleTeamDrawer(teamNum) {
    const sidebar = document.getElementById('teamSidebar' + teamNum);
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

function closeTeamDrawer(teamNum) {
    const sidebar = document.getElementById('teamSidebar' + teamNum);
    if (sidebar) {
        sidebar.classList.remove('open');
    }
}

function initializeTeamSidebars() {
    if (!IS_5V5) return;
    
    console.log('Initializing team sidebars');
    
    const myTeamData = IS_P1 ? TEAM_DATA_P1 : TEAM_DATA_P2;
    const oppTeamData = IS_P1 ? TEAM_DATA_P2 : TEAM_DATA_P1;
    
    populateTeamSidebar(1, myTeamData, true);
    populateTeamSidebar(2, oppTeamData, false);
}

function populateTeamSidebar(teamNum, heroesData, isMyTeam) {
    const containerId = 'team' + teamNum + 'HeroesList';
    const container = document.getElementById(containerId);
    
    if (!container || !heroesData || heroesData.length === 0) {
        console.warn('Cannot populate team sidebar', teamNum);
        return;
    }
    
    container.innerHTML = '';
    
    heroesData.forEach((heroData, index) => {
        const heroCard = createHeroCard(heroData, index, isMyTeam);
        container.appendChild(heroCard);
    });
}

function createHeroCard(heroData, index, isMyTeam) {
    const card = document.createElement('div');
    card.className = 'hero-card';
    
    if (index === 0) {
        card.classList.add('active');
    }
    
    if (heroData.pv <= 0) {
        card.classList.add('dead');
    }
    
    const maxPv = heroData.max_pv || heroData.pv;
    const hpPercent = Math.max(0, (heroData.pv / maxPv) * 100);
    
    card.innerHTML = `
        <div class="hero-card-name">${escapeHtml(heroData.name)}</div>
        <div class="hero-card-position">Position ${index + 1}</div>
        <div class="hero-card-hp">
            <div class="hero-card-hp-bar">
                <div class="fill" style="width: ${hpPercent}%"></div>
            </div>
            <span style="min-width: 30px; text-align: right;">${Math.round(hpPercent)}%</span>
        </div>
        <div class="hero-card-stats">
            <span>⚔️ ${heroData.atk}</span>
            <span>🛡️ ${heroData.def}</span>
            <span>⚡ ${heroData.speed}</span>
        </div>
        <div class="hero-card-hp" style="font-size: 0.85rem; color: #999;">
            ${Math.round(heroData.pv)} / ${maxPv} PV
        </div>
    `;
    
    return card;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateTeamSidebarsWithData(myTeamData, oppTeamData, myActiveIndex, oppActiveIndex) {
    if (!IS_5V5) return;
    
    updateSidebarWithTeamData('team1HeroesList', myTeamData, myActiveIndex, true);
    updateSidebarWithTeamData('team2HeroesList', oppTeamData, oppActiveIndex, false);
    
    // Update local data for switch modal
    if (IS_P1) {
        myTeamData.forEach((hero, i) => {
            if (TEAM_DATA_P1[i]) {
                TEAM_DATA_P1[i].pv = hero.pv;
                TEAM_DATA_P1[i].max_pv = hero.max_pv;
                TEAM_DATA_P1[i].isDead = hero.isDead;
            }
        });
        oppTeamData.forEach((hero, i) => {
            if (TEAM_DATA_P2[i]) {
                TEAM_DATA_P2[i].pv = hero.pv;
                TEAM_DATA_P2[i].max_pv = hero.max_pv;
                TEAM_DATA_P2[i].isDead = hero.isDead;
            }
        });
    } else {
        myTeamData.forEach((hero, i) => {
            if (TEAM_DATA_P2[i]) {
                TEAM_DATA_P2[i].pv = hero.pv;
                TEAM_DATA_P2[i].max_pv = hero.max_pv;
                TEAM_DATA_P2[i].isDead = hero.isDead;
            }
        });
        oppTeamData.forEach((hero, i) => {
            if (TEAM_DATA_P1[i]) {
                TEAM_DATA_P1[i].pv = hero.pv;
                TEAM_DATA_P1[i].max_pv = hero.max_pv;
                TEAM_DATA_P1[i].isDead = hero.isDead;
            }
        });
    }
}

function updateSidebarWithTeamData(containerId, teamData, activeIndex, isMyTeam) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const heroCards = container.querySelectorAll('.hero-card');
    
    teamData.forEach((heroData, index) => {
        const card = heroCards[index];
        if (!card) return;
        
        if (index === activeIndex) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
        
        if (heroData.isDead || heroData.pv <= 0) {
            card.classList.add('dead');
        } else {
            card.classList.remove('dead');
        }
        
        const maxPv = heroData.max_pv || heroData.pv;
        const hpPercent = Math.max(0, Math.min(100, (heroData.pv / maxPv) * 100));
        
        const hpBar = card.querySelector('.hero-card-hp-bar .fill');
        if (hpBar) {
            hpBar.style.width = hpPercent + '%';
            if (hpPercent <= 25) {
                hpBar.style.background = '#d32f2f';
            } else if (hpPercent <= 50) {
                hpBar.style.background = '#ff9800';
            } else {
                hpBar.style.background = 'var(--gold-accent)';
            }
        }
        
        const hpText = card.querySelectorAll('.hero-card-hp');
        if (hpText.length >= 2) {
            hpText[1].innerHTML = `${Math.round(heroData.pv)} / ${maxPv} PV`;
        }
        
        const hpPercentEl = card.querySelector('.hero-card-hp span');
        if (hpPercentEl) {
            hpPercentEl.textContent = Math.round(hpPercent) + '%';
        }
    });
}

// ============ SWITCH MODAL ============

function showSwitchMenu(isForcedSwitch = false) {
    if (!IS_5V5) return;
    
    const modal = document.getElementById('switchModal');
    const heroesGrid = document.getElementById('switchHeroesGrid');
    const modalTitle = document.getElementById('switchModalTitle');
    
    if (!modal || !heroesGrid) return;
    
    if (isForcedSwitch) {
        modal.classList.add('forced-switch');
        modalTitle.innerHTML = '💀 HÉROS MORT - Choisissez un remplaçant!';
        modal.querySelector('.switch-modal-cancel').style.display = 'none';
    } else {
        modal.classList.remove('forced-switch');
        modalTitle.innerHTML = '🔄 Changer de Héros';
        modal.querySelector('.switch-modal-cancel').style.display = 'block';
    }
    
    const myTeamData = IS_P1 ? TEAM_DATA_P1 : TEAM_DATA_P2;
    
    heroesGrid.innerHTML = '';
    
    myTeamData.forEach((heroData, index) => {
        const card = document.createElement('div');
        card.className = 'switch-hero-card';
        
        const isDead = heroData.isDead || heroData.pv <= 0;
        const isActive = index === (currentGameState?.me?.activeIndex ?? 0);
        
        if (isDead) {
            card.classList.add('unavailable');
        } else if (isActive) {
            card.classList.add('active');
        }
        
        const hpPercent = Math.max(0, Math.round((heroData.pv / (heroData.max_pv || heroData.pv)) * 100));
        let hpClass = '';
        if (isDead) hpClass = 'dead';
        else if (hpPercent < 30) hpClass = 'low';
        
        card.innerHTML = `
            <img src="${heroData.images?.p1 ? ASSET_BASE_PATH + heroData.images.p1 : ASSET_BASE_PATH + 'media/heroes/default.png'}" alt="${heroData.name}">
            <div class="switch-hero-name">${escapeHtml(heroData.name)}</div>
            <div class="switch-hero-hp ${hpClass}">${isDead ? '💀 MORT' : hpPercent + '% PV'}</div>
        `;
        
        if (!isDead && !isActive) {
            card.addEventListener('click', () => {
                closeSwitchModal(true);
                performSwitch(index, isForcedSwitch);
            });
        }
        
        heroesGrid.appendChild(card);
    });
    
    modal.style.display = 'flex';
    if (!isForcedSwitch) {
        isSwitchModalManuallyOpen = true;
    }
}

function closeSwitchModal(force = false) {
    if (isSwitchModalManuallyOpen && !force) {
        return;
    }
    
    const modal = document.getElementById('switchModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('forced-switch');
    }
    isSwitchModalManuallyOpen = false;
}

function performSwitch(heroIndex, isForcedSwitch = false) {
    if (!IS_5V5) return;
    
    stopActionTimer();
    const btnContainer = document.getElementById('actionButtons');
    const waitMsg = document.getElementById('waitingMessage');
    
    btnContainer.style.display = 'none';
    waitMsg.style.display = 'block';
    
    if (isForcedSwitch) {
        waitMsg.innerText = 'Remplacement du héros mort...';
    } else {
        waitMsg.innerText = 'Changement de héros...';
    }

    if (IS_TEST_UI) {
        const myTeamData = IS_P1 ? TEAM_DATA_P1 : TEAM_DATA_P2;
        const heroData = myTeamData[heroIndex];
        if (heroData) {
            setActiveHeroCard(heroIndex);
            updateMyHeroDisplay(heroData);
        }
        waitMsg.style.display = 'none';
        btnContainer.style.display = 'flex';
        return;
    }
    
    const fetchPromise = isForcedSwitch 
        ? fetch('../../api.php?action=submit_forced_switch', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'match_id=' + MATCH_ID + '&target_index=' + heroIndex
        })
        : fetch('../../api.php?action=submit_move', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'match_id=' + MATCH_ID + '&move=switch:' + heroIndex
        });
    
    fetchPromise
        .then(r => r.text())
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Réponse invalide: ' + text.substring(0, 100));
            }
        })
        .then(data => {
            if (!data) {
                showErrorMessage('Erreur: réponse vide du serveur');
                btnContainer.style.display = 'flex';
                waitMsg.style.display = 'none';
                return;
            }
            
            if (data.status === 'error') {
                showErrorMessage("Erreur: " + (data.message || "Erreur inconnue"));
                btnContainer.style.display = 'flex';
                waitMsg.style.display = 'none';
            } else if (data.status === 'ok') {
                waitMsg.innerText = "En attente de l'adversaire...";
            }
        })
        .catch(err => {
            console.error('Switch error:', err);
            showErrorMessage('Erreur: ' + err.message);
            btnContainer.style.display = 'flex';
            waitMsg.style.display = 'none';
        });
}

function updateSwitchedHeroCard(logLine) {
    const match = logLine.match(/🔄\s+(.+?)\s+entre en combat/);
    if (!match) return;
    
    const switchedHeroName = match[1];
    
    const heroCards = document.querySelectorAll('#teamSidebar1 .hero-card');
    let foundIndex = -1;
    
    heroCards.forEach((card, index) => {
        const nameEl = card.querySelector('.hero-card-name');
        if (nameEl && nameEl.innerText === switchedHeroName) {
            foundIndex = index;
        }
    });
    
    if (foundIndex !== -1) {
        setActiveHeroCard(foundIndex);
    }
}

function updateMyHeroDisplay(heroData) {
    const nameEl = document.getElementById('myName');
    const typeEl = document.getElementById('myType');
    const statsEl = document.getElementById('myStats');
    const pvBar = document.getElementById('myPvBar');
    const fighter = document.getElementById('myFighter');
    const imgEl = fighter ? fighter.querySelector('img') : null;
    
    // Calculer les PV max (utiliser base_pv ou pv si base non disponible)
    const maxPv = heroData.base_pv || heroData.max_pv || heroData.pv || 100;
    const currentPv = heroData.pv || maxPv;
    const pvPct = (maxPv > 0) ? (currentPv / maxPv * 100) : 100;
    
    if (nameEl) nameEl.innerText = heroData.name || 'Héros';
    if (typeEl) typeEl.innerText = heroData.type || 'Unknown';
    if (statsEl) statsEl.innerText = Math.round(currentPv) + " / " + maxPv + " | ATK: " + heroData.atk + " | DEF: " + heroData.def;
    if (pvBar) pvBar.style.width = pvPct + '%';
    if (imgEl && heroData.images && heroData.images.p1) {
        imgEl.src = ASSET_BASE_PATH + heroData.images.p1;
    }
}

function setActiveHeroCard(heroIndex) {
    const heroCards = document.querySelectorAll('#teamSidebar1 .hero-card');
    heroCards.forEach((card, index) => {
        if (index === heroIndex) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
    });
}
