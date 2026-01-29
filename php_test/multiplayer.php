<?php
/**
 * MULTIPLAYER COMBAT PAGE
 * Interface complète de combat multiplayer avec polling en temps réel
 * Basée sur la structure de single_player.php
 */

// Autoloader (AVANT session_start pour la désérialisation)
if (!function_exists('chargerClasse')) {
    function chargerClasse($classe) {
        // Chercher dans classes/
        if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
            require __DIR__ . '/classes/' . $classe . '.php';
            return;
        }
        // Chercher dans classes/effects/
        if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
            require __DIR__ . '/classes/effects/' . $classe . '.php';
            return;
        }
        // Chercher dans classes/blessings/
        if (file_exists(__DIR__ . '/classes/blessings/' . $classe . '.php')) {
            require __DIR__ . '/classes/blessings/' . $classe . '.php';
            return;
        }
        // Chercher dans classes/heroes/
        if (file_exists(__DIR__ . '/classes/heroes/' . $classe . '.php')) {
            require __DIR__ . '/classes/heroes/' . $classe . '.php';
            return;
        }
    }
    spl_autoload_register('chargerClasse');
}

// Session (après autoloader pour que les classes soient chargées lors de unserialize)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- ABANDON ---
if (isset($_POST['abandon_multi'])) {
    $matchId = $_GET['match_id'] ?? $_SESSION['matchId'] ?? null;
    
    if ($matchId) {
        $matchFile = __DIR__ . '/data/matches/' . $matchId . '.json';
        if (file_exists($matchFile)) {
            $fp = fopen($matchFile, 'r+');
            if (flock($fp, LOCK_EX)) {
                $metaData = json_decode(stream_get_contents($fp), true);
                if ($metaData) {
                    $sessionId = session_id();
                    $isP1 = ($metaData['player1']['session'] === $sessionId);
                    $winnerKey = $isP1 ? 'player2' : 'player1';
                    $loserName = $isP1 ? ($metaData['player1']['display_name'] ?? 'Joueur 1') : ($metaData['player2']['display_name'] ?? 'Joueur 2');
                    
                    $metaData['status'] = 'finished';
                    $metaData['winner'] = $winnerKey;
                    $metaData['logs'][] = "🏳️ " . $loserName . " a abandonné !";
                    
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($metaData, JSON_PRETTY_PRINT));
                }
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
    
    // Préserver les données de connexion
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;
    
    // Nettoyer les données de combat/match
    unset($_SESSION['combat']);
    unset($_SESSION['matchId']);
    unset($_SESSION['queueHeroId']);
    unset($_SESSION['queueStartTime']);
    unset($_SESSION['queueHeroData']);
    unset($_SESSION['queueDisplayName']);
    
    // Restaurer les données de connexion
    if ($userId !== null) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
    }
    
    header("Location: index.php");
    exit;
}

// Récupérer le matchId depuis l'URL
$matchId = $_GET['match_id'] ?? $_SESSION['matchId'] ?? null;

if (!$matchId) {
    header("Location: index.php");
    exit;
}

$_SESSION['matchId'] = $matchId;

// Charger l'état initial du match directement depuis le fichier
// (Plus fiable que file_get_contents avec URL relative)
$matchFile = __DIR__ . '/data/matches/' . $matchId . '.json';

if (!file_exists($matchFile)) {
    die("Erreur: Le match '$matchId' n'existe pas.");
}

$matchData = json_decode(file_get_contents($matchFile), true);

if (!$matchData) {
    die("Erreur: Impossible de lire les données du match.");
}

// Charger l'état du combat via MultiCombat
require_once __DIR__ . '/classes/MultiCombat.php';

$stateFile = __DIR__ . '/data/matches/' . $matchId . '.state';
$multiCombat = MultiCombat::load($stateFile);

if (!$multiCombat) {
    // Initialiser le combat si pas encore créé
    try {
        $multiCombat = MultiCombat::create($matchData['player1'], $matchData['player2']);
        if (!$multiCombat->save($stateFile)) {
            die("Erreur: Impossible de sauvegarder l'état initial du combat.");
        }
    } catch (Exception $e) {
        die("Erreur lors de l'initialisation du combat: " . $e->getMessage());
    }
}

// Déterminer le rôle du joueur actuel
$sessionId = session_id();
$isP1 = ($matchData['player1']['session'] === $sessionId);
$myRole = $isP1 ? 'p1' : 'p2';

// Construire l'état du jeu pour l'affichage
try {
    $gameState = $multiCombat->getStateForUser($sessionId, $matchData);
    $gameState['status'] = 'active';
    $gameState['turn'] = $matchData['turn'] ?? 1;
    $gameState['isOver'] = $multiCombat->isOver();
    
    // Déterminer qui doit jouer
    $actions = $matchData['current_turn_actions'] ?? [];
    $oppRole = $isP1 ? 'p2' : 'p1';
    $gameState['waiting_for_me'] = !isset($actions[$myRole]);
    $gameState['waiting_for_opponent'] = isset($actions[$myRole]) && !isset($actions[$oppRole]) && ($matchData['mode'] ?? '') !== 'bot';
    
} catch (Exception $e) {
    die("Erreur lors de la construction de l'état du jeu: " . $e->getMessage());
}

?>

<link rel="icon" href="./media/website/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="./style.css">
<link rel="stylesheet" href="./css/combat.css">

<h1 class="arena-title">Horus Battle Arena</h1>

<div class="game-container">
    <div class="arena">
        <div class="turn-indicator" id="turnIndicator">Tour <?php echo $gameState['turn']; ?></div>
        
        <!-- STATS -->
        <div class="stats-row">
            <div class="stats hero-stats">
                <strong id="myName"><?php echo htmlspecialchars($gameState['me']['name']); ?></strong>
                <span class="type-badge" id="myType"><?php echo $gameState['me']['type']; ?></span>
                <div class="stat-bar">
                    <div class="pv-bar" id="myPvBar" style="width: 100%;"></div>
                </div>
                <span class="stat-numbers" id="myStats"><?php echo round($gameState['me']['pv']); ?> / <?php echo $gameState['me']['max_pv']; ?> | ATK: <?php echo $gameState['me']['atk']; ?> | DEF: <?php echo $gameState['me']['def']; ?></span>
            </div>
            
            <div class="stats enemy-stats">
                <strong id="oppName"><?php echo htmlspecialchars($gameState['opponent']['name']); ?></strong>
                <span class="type-badge" id="oppType"><?php echo $gameState['opponent']['type']; ?></span>
                <div class="stat-bar">
                    <div class="pv-bar enemy-pv" id="oppPvBar" style="width: 100%;"></div>
                </div>
                <span class="stat-numbers" id="oppStats"><?php echo round($gameState['opponent']['pv']); ?> / <?php echo $gameState['opponent']['max_pv']; ?> | ATK: <?php echo $gameState['opponent']['atk']; ?> | DEF: <?php echo $gameState['opponent']['def']; ?></span>
            </div>
        </div>
        
        <!-- ZONE DE COMBAT -->
        <div class="fighters-area">
            <div class="fighter hero" id="myFighter">
                <img src="<?php echo $gameState['me']['img']; ?>" alt="Hero">
                <div id="myEmojiContainer"></div>
                <div class="effects-container hero-effects" id="myEffects"></div>
            </div>

            <div class="vs-indicator">VS</div>

            <div class="fighter enemy" id="oppFighter">
                <img src="<?php echo $gameState['opponent']['img']; ?>" alt="Opponent" class="enemy-img">
                <div id="oppEmojiContainer"></div>
                <div class="effects-container enemy-effects" id="oppEffects"></div>
            </div>
        </div>

        <!-- BATTLE LOG -->
        <div class="battle-log" id="battleLog">
            <?php foreach ($gameState['logs'] as $log): ?>
                <div class="log-line"><?php echo htmlspecialchars($log); ?></div>
            <?php endforeach; ?>
        </div>
        
        <!-- CONTROLS -->
        <div class="controls">
            <div class="controls-row">
                <!-- LEFT: Action list -->
                <div id="actionButtons" class="action-list">
                    <!-- Actions générées dynamiquement par JS -->
                </div>
                
                <!-- RIGHT: Info panel (timer + description) -->
                <div id="infoPanel" class="info-panel info-panel-hidden">
                    <div id="actionTimer" class="action-timer-circle">
                        <!-- SVG Ring -->
                        <svg class="timer-svg" viewBox="0 0 70 70">
                            <circle class="timer-circle-bg" cx="35" cy="35" r="32"></circle>
                            <circle id="timerProgress" class="timer-circle-progress" cx="35" cy="35" r="32"></circle>
                        </svg>
                        <span id="timerValue">60</span>
                    </div>
                    <div id="actionDescription" class="action-description">
                        Survolez une action pour voir sa description
                    </div>
                </div>
            </div>
            
            <div id="waitingMessage" class="waiting-text waiting-message-hidden">
                En attente de l'adversaire...
            </div>
            <div id="gameOverMessage" class="game-over-section">
                <h3 id="gameOverText"></h3>
                <br>
                <button class="action-btn new-game" onclick="location.href='index.php'">Menu Principal</button>
            </div>
            
            <form method="POST" class="abandon-form">
                <button type="submit" name="abandon_multi" class="action-btn abandon">Abandonner</button>
            </form>
        </div>
    </div>
</div>

<script src="js/combat-animations.js"></script>
<script>
const MATCH_ID = '<?php echo addslashes($matchId); ?>';
const INITIAL_STATE = <?php echo json_encode($gameState); ?>;
const IS_P1 = <?php echo $isP1 ? 'true' : 'false'; ?>; // True if I am player1 in the match

let pollInterval = null;
let lastLogCount = <?php echo count($gameState['logs']); ?>;
let currentGameState = INITIAL_STATE;
let lastTurnProcessed = <?php echo $gameState['turn'] ?? 1; ?>;
let isPlayingAnimations = false;

// Timer system
let actionTimer = null;
let timeRemaining = 60;
let isTimerRunning = false;
const RANDOM_ACTION_THRESHOLD = 0; // Trigger random action when time reaches 0
const FORFEIT_THRESHOLD = -65; // Trigger forfeit 65s after timeout (125s total)

function startActionTimer() {
    if (isTimerRunning) return; // Don't restart if already running
    isTimerRunning = true;
    timeRemaining = 60;
    updateTimerDisplay();
    document.getElementById('infoPanel').style.display = 'flex'; // Use flex for info panel
    
    actionTimer = setInterval(() => {
        timeRemaining--;
        updateTimerDisplay();
        
        // Thresholds
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
    
    // Display logic: show 0 if negative
    const displayTime = Math.max(0, timeRemaining);
    timerValue.textContent = displayTime;
    
    // SVG Progress Animation
    // Circumference ~200 (2 * PI * 32)
    const circumference = 200; 
    const offset = circumference - (timeRemaining / 60) * circumference;
    
    // Clamp offset between 0 and 200
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

// Logic to play a random action
function playRandomAction() {
    const availableButtons = document.querySelectorAll('.action-btn:not(.disabled)');
    if (availableButtons.length > 0) {
        const randomIndex = Math.floor(Math.random() * availableButtons.length);
        availableButtons[randomIndex].click();
    }
}

// Logic to trigger forfeit
function triggerForfeit() {
    stopActionTimer();
    // Create and submit a form to abandon
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

// Hit animation on hero
function triggerHitAnimation(fighterElement) {
    if (!fighterElement) return;
    fighterElement.classList.add('fighter-hit');
    setTimeout(() => fighterElement.classList.remove('fighter-hit'), 400);
}

// Description handling for action buttons
function setupActionTooltips() {
    const descBox = document.getElementById('actionDescription');
    const defaultText = "Survolez une action pour voir sa description";
    const defaultColor = "#e0e0e0"; // Theme light text color
    const highlightColor = "#ffd700"; // Theme gold accent
    
    // Reset style initially
    descBox.style.color = defaultColor;
    
    document.querySelectorAll('.action-btn').forEach(btn => {
        btn.addEventListener('mouseenter', (e) => {
            const desc = btn.getAttribute('data-description');
            if (desc) {
                descBox.textContent = desc;
                descBox.style.color = highlightColor;
            }
        });
        btn.addEventListener('mouseleave', () => {
            descBox.textContent = defaultText;
            descBox.style.color = defaultColor;
        });
    });
}

// ============ ANIMATION SYSTEM ============

// Configuration pour le système d'animations partagé
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
    // In Combat.php: 'player' = P1, 'enemy' = P2
    // For P1: player -> my stats, enemy -> opponent stats
    // For P2: player -> opponent stats, enemy -> my stats
    
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

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes emojiPop {
        0% { transform: scale(0.5); opacity: 1; }
        50% { transform: scale(1.3); }
        100% { transform: scale(1); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Update effect indicators (active effects displayed as emojis)
function updateEffectIndicators(effects, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    // Clear existing indicators
    container.innerHTML = '';
    
    // If no effects, nothing to show
    if (!effects || typeof effects !== 'object') return;
    
    // Add effect indicators
    for (const [name, effect] of Object.entries(effects)) {
        const indicator = document.createElement('div');
        indicator.className = 'effect-indicator';
        indicator.title = name;
        indicator.textContent = effect.emoji || '✨';
        container.appendChild(indicator);
    }
}


function updateCombatState() {
    fetch('api.php?action=poll_status&match_id=' + MATCH_ID, {
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
                console.error('Response was:', text.substring(0, 500));
                throw new Error("Response is not valid JSON: " + text.substring(0, 200));
            }
        })
        .then(data => {
            // Check for API errors
            if (!data) {
                console.error('Empty response from server');
                showErrorMessage('Erreur: réponse vide du serveur');
                return;
            }
            
            if (data.status === 'error') {
                showErrorMessage("Erreur API: " + data.message);
                return;
            }

            currentGameState = data;

            // Check if new turn - play animations
            const hasNewAnimations = data.turn > lastTurnProcessed && data.turnActions && data.turnActions.length > 0;
            if (hasNewAnimations) {
                lastTurnProcessed = data.turn;
                // Don't update stats here - animations will handle it
                playTurnAnimations(data.turnActions);
            }

            // Update UI
            document.getElementById('turnIndicator').innerText = "Tour " + data.turn;
            
            // Player stats - only update directly if no animations playing
            // IMPORTANT: Check both hasNewAnimations AND isPlayingAnimations to prevent
            // polling from overwriting stats while animations are in progress
            if (!hasNewAnimations && !isPlayingAnimations) {
                document.getElementById('myName').innerText = data.me.name;
                document.getElementById('myType').innerText = data.me.type;
                document.getElementById('myStats').innerText = Math.round(data.me.pv) + " / " + data.me.max_pv + " | ATK: " + data.me.atk + " | DEF: " + data.me.def;
                document.getElementById('myPvBar').style.width = (data.me.pv / data.me.max_pv * 100) + "%";
                
                // Opponent stats
                document.getElementById('oppName').innerText = data.opponent.name;
                document.getElementById('oppType').innerText = data.opponent.type;
                document.getElementById('oppStats').innerText = Math.round(data.opponent.pv) + " / " + data.opponent.max_pv + " | ATK: " + data.opponent.atk + " | DEF: " + data.opponent.def;
                document.getElementById('oppPvBar').style.width = (data.opponent.pv / data.opponent.max_pv * 100) + "%";
                
                // Update effect indicators
                updateEffectIndicators(data.me.activeEffects, 'myEffects');
                updateEffectIndicators(data.opponent.activeEffects, 'oppEffects');
            }            
            // Logs
            const logBox = document.getElementById('battleLog');
            if (data.logs && data.logs.length > lastLogCount) {
                for (let i = lastLogCount; i < data.logs.length; i++) {
                    let div = document.createElement('div');
                    div.className = 'log-line';
                    div.innerText = data.logs[i];
                    logBox.appendChild(div);
                }
                lastLogCount = data.logs.length;
                logBox.scrollTop = logBox.scrollHeight;
            }
            
            // Build action buttons dynamically
            const btnContainer = document.getElementById('actionButtons');
            const waitMsg = document.getElementById('waitingMessage');
            const gameOverMsg = document.getElementById('gameOverMessage');
            const errorMsg = document.getElementById('errorMessage');
            
            // Hide error message if we successfully got data
            if (errorMsg) {
                errorMsg.style.display = 'none';
            }
            
            // Generate buttons from available actions
            if (data.actions && data.waiting_for_me && !data.isOver) {
                btnContainer.innerHTML = '';
                for (const [key, action] of Object.entries(data.actions)) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = `action-btn ${key}${action.canUse ? '' : ' disabled'}`;
                    button.setAttribute('data-description', action.description || '');
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
                setupActionTooltips();
                startActionTimer();
            }
            
            if (data.isOver) {
                clearInterval(pollInterval);
                stopActionTimer();
                btnContainer.style.display = 'none';
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
                    btnContainer.style.display = 'flex';
                    waitMsg.style.display = 'none';
                } else {
                    btnContainer.style.display = 'none';
                    waitMsg.style.display = 'block';
                    waitMsg.innerText = "En attente de l'adversaire...";
                }
            }
        })
        .catch(err => {
            console.error('Poll error:', err);
            showErrorMessage('Erreur: ' + err.message);
        });
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
    errorMsg.innerHTML = message + '<br><button onclick="location.href=\'index.php\'" class="error-return-btn">Retour Menu</button>';
    errorMsg.style.display = 'block';
}

function sendAction(action) {
    const btnContainer = document.getElementById('actionButtons');
    const waitMsg = document.getElementById('waitingMessage');
    
    stopActionTimer();
    btnContainer.style.display = 'none';
    waitMsg.style.display = 'block';
    waitMsg.innerText = 'Envoi de l\'action...';
    
    fetch('api.php?action=submit_move', {
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
            console.error('Action error:', err);
            showErrorMessage('Erreur: ' + err.message);
            btnContainer.style.display = 'flex';
            waitMsg.style.display = 'none';
        });
}

// Initial load and start polling
updateCombatState();
pollInterval = setInterval(updateCombatState, 2000);
</script>

