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
    }
    spl_autoload_register('chargerClasse');
}

// Session (après autoloader pour que les classes soient chargées lors de unserialize)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- RESET ---
if (isset($_POST['abandon_multi'])) {
    session_unset();
    session_destroy();
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
        $multiCombat = MultiCombat::create($matchData['player1']['hero'], $matchData['player2']['hero']);
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

<link rel="stylesheet" href="./style.css">

<div class="game-container">
    <div class="arena">
        <div class="turn-indicator" id="turnIndicator">Tour <?php echo $gameState['turn']; ?></div>
        
        <!-- STATS -->
        <div class="stats-row">
            <div class="stats hero-stats">
                <strong id="myName"><?php echo $gameState['me']['name']; ?></strong>
                <span class="type-badge" id="myType"><?php echo $gameState['me']['type']; ?></span>
                <div class="stat-bar">
                    <div class="pv-bar" id="myPvBar" style="width: 100%;"></div>
                </div>
                <span class="stat-numbers" id="myStats"><?php echo round($gameState['me']['pv']); ?> / <?php echo $gameState['me']['max_pv']; ?> | ATK: <?php echo $gameState['me']['atk']; ?> | DEF: <?php echo $gameState['me']['def']; ?></span>
            </div>
            
            <div class="stats enemy-stats">
                <strong id="oppName"><?php echo $gameState['opponent']['name']; ?></strong>
                <span class="type-badge" id="oppType"><?php echo $gameState['opponent']['type']; ?></span>
                <div class="stat-bar">
                    <div class="pv-bar enemy-pv" id="oppPvBar" style="width: 100%;"></div>
                </div>
                <span class="stat-numbers" id="oppStats"><?php echo round($gameState['opponent']['pv']); ?> / <?php echo $gameState['opponent']['max_pv']; ?> | ATK: <?php echo $gameState['opponent']['atk']; ?> | DEF: <?php echo $gameState['opponent']['def']; ?></span>
            </div>
        </div>
        
        <!-- ZONE DE COMBAT -->
        <div class="fighters-area">
            <div class="fighter hero" id="heroFighter">
                <img src="<?php echo $gameState['me']['img']; ?>" alt="Hero">
                <div id="heroEmojiContainer"></div>
                <div class="effects-container hero-effects" id="myEffects"></div>
            </div>

            <div class="vs-indicator">VS</div>

            <div class="fighter enemy" id="enemyFighter">
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
            <div id="actionButtons" class="action-list">
                <!-- Actions générées dynamiquement par JS -->
            </div>
            <div id="waitingMessage" class="waiting-text" style="display:none; margin-top: 15px;">
                En attente de l'adversaire...
            </div>
            <div id="gameOverMessage" style="display:none; text-align:center; margin-top: 30px;">
                <h3 id="gameOverText"></h3>
                <button class="action-btn new-game" onclick="location.href='index.php'">Menu Principal</button>
            </div>
            
            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="abandon_multi" class="action-btn abandon">Abandonner</button>
            </form>
        </div>
    </div>
</div>

<script>
const MATCH_ID = '<?php echo addslashes($matchId); ?>';
const INITIAL_STATE = <?php echo json_encode($gameState); ?>;

let pollInterval = null;
let lastLogCount = <?php echo count($gameState['logs']); ?>;
let currentGameState = INITIAL_STATE;

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
                console.error('API error:', data.message);
                showErrorMessage("Erreur API: " + data.message);
                return;
            }

            currentGameState = data;

            // Update UI
            document.getElementById('turnIndicator').innerText = "Tour " + data.turn;
            
            // Player stats
            document.getElementById('myName').innerText = data.me.name;
            document.getElementById('myType').innerText = data.me.type;
            document.getElementById('myStats').innerText = Math.round(data.me.pv) + " / " + data.me.max_pv + " | ATK: " + data.me.atk + " | DEF: " + data.me.def;
            document.getElementById('myPvBar').style.width = (data.me.pv / data.me.max_pv * 100) + "%";
            
            // Opponent stats
            document.getElementById('oppName').innerText = data.opponent.name;
            document.getElementById('oppType').innerText = data.opponent.type;
            document.getElementById('oppStats').innerText = Math.round(data.opponent.pv) + " / " + data.opponent.max_pv + " | ATK: " + data.opponent.atk + " | DEF: " + data.opponent.def;
            document.getElementById('oppPvBar').style.width = (data.opponent.pv / data.opponent.max_pv * 100) + "%";
            
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
                    button.title = action.description || '';
                    button.disabled = !action.canUse;
                    button.onclick = () => sendAction(key);
                    
                    // Structure comme en single player
                    let buttonHTML = `<span class="action-emoji-icon">${action.emoji || '⚔️'}</span>`;
                    buttonHTML += `<span class="action-label">${action.label}</span>`;
                    if (action.ppText) {
                        buttonHTML += `<span class="action-pp">${action.ppText}</span>`;
                    }
                    
                    button.innerHTML = buttonHTML;
                    btnContainer.appendChild(button);
                }
            }
            
            if (data.isOver) {
                clearInterval(pollInterval);
                btnContainer.style.display = 'none';
                waitMsg.style.display = 'none';
                gameOverMsg.style.display = 'block';
                
                const gameOverText = document.getElementById('gameOverText');
                if (data.winner === 'you') {
                    gameOverText.innerText = '🎉 VICTOIRE ! 🎉';
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
    errorMsg.innerHTML = message + '<br><button onclick="location.href=\'index.php\'" style="margin-top: 10px; padding: 8px 16px; background: white; color: red; border: none; border-radius: 4px; cursor: pointer;">Retour Menu</button>';
    errorMsg.style.display = 'block';
}

function sendAction(action) {
    const btnContainer = document.getElementById('actionButtons');
    const waitMsg = document.getElementById('waitingMessage');
    
    btnContainer.style.display = 'none';
    waitMsg.style.display = 'block';
    waitMsg.innerText = 'Envoi de l\'action...';
    
    fetch('api.php?action=submit_move', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'match_id=' + MATCH_ID + '&move=' + action
    })
        .then(r => r.json())
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
            showErrorMessage('Erreur: Impossible d\'envoyer l\'action');
            btnContainer.style.display = 'flex';
            waitMsg.style.display = 'none';
        });
}

// Initial load and start polling
updateCombatState();
pollInterval = setInterval(updateCombatState, 2000);
</script>
