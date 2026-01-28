<?php
/**
 * MULTIPLAYER COMBAT PAGE
 * Miroir de single_player.php mais en multiplayer
 * Polling des updates de combat via api.php
 */

// Autoloader
if (!function_exists('chargerClasse')) {
    function chargerClasse($classe) {
        if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
            require __DIR__ . '/classes/' . $classe . '.php';
            return;
        }
        if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
            require __DIR__ . '/classes/effects/' . $classe . '.php';
            return;
        }
    }
    spl_autoload_register('chargerClasse');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupérer le matchId depuis l'URL ou la session
$matchId = $_GET['match_id'] ?? $_SESSION['matchId'] ?? null;

if (!$matchId) {
    header("Location: index.php");
    exit;
}

$_SESSION['matchId'] = $matchId;

// Récupérer l'état initial du combat
$matchFile = __DIR__ . '/data/matches/' . $matchId . '.json';
if (!file_exists($matchFile)) {
    die("Match non trouvé");
}

$matchData = json_decode(file_get_contents($matchFile), true);
if (!$matchData) {
    die("Impossible de décoder le match JSON");
}

$sessionId = session_id();

// Déterminer si c'est le joueur 1 ou 2
$isP1 = ($matchData['player1']['session'] === $sessionId);
$isP2 = ($matchData['player2']['session'] === $sessionId);

if (!$isP1 && !$isP2) {
    // Debug: afficher les infos
    die("Non autorisé\n\nDebug:\nVotre session: $sessionId\nP1 session: " . $matchData['player1']['session'] . "\nP2 session: " . $matchData['player2']['session']);
}

// Images des héros
$myHero = $isP1 ? $matchData['player1']['hero'] : $matchData['player2']['hero'];
$oppHero = $isP1 ? $matchData['player2']['hero'] : $matchData['player1']['hero'];
$myImg = $myHero['images']['p1'];
$oppImg = $oppHero['images']['p1'];
$isBotMatch = ($matchData['mode'] ?? '') === 'bot';
?>

<link rel="stylesheet" href="./style.css">
<link rel="stylesheet" href="./css/combat.css">

<div class="game-container">
    <div class="arena">
        <div class="turn-indicator" id="turnIndicator">Tour --</div>
        
        <!-- STATS -->
        <div class="stats-row">
            <div class="stats hero-stats">
                <strong id="myName"><?php echo $myHero['name']; ?></strong>
                <span class="type-badge" id="myType"><?php echo ucfirst($myHero['type']); ?></span>
                <div class="stat-bar">
                    <div class="pv-bar" id="myPvBar" style="width: 100%;"></div>
                </div>
                <span class="stat-numbers" id="myStats">-- / --</span>
            </div>
            
            <div class="stats enemy-stats">
                <strong id="oppName"><?php echo $oppHero['name']; ?></strong>
                <span class="type-badge" id="oppType"><?php echo ucfirst($oppHero['type']); ?></span>
                <div class="stat-bar">
                    <div class="pv-bar enemy-pv" id="oppPvBar" style="width: 100%;"></div>
                </div>
                <span class="stat-numbers" id="oppStats">-- / --</span>
            </div>
        </div>
        
        <!-- FIGHTERS -->
        <div class="fighters-area">
            <div class="fighter hero" id="myFighter">
                <img src="<?php echo $myImg; ?>" alt="<?php echo $myHero['name']; ?>">
                <div id="myEmoji"></div>
            </div>
            
            <div class="vs-indicator">VS</div>
            
            <div class="fighter enemy" id="oppFighter">
                <img src="<?php echo $oppImg; ?>" alt="<?php echo $oppHero['name']; ?>" class="enemy-img">
                <div id="oppEmoji"></div>
            </div>
        </div>
        
        <!-- BATTLE LOG -->
        <div class="battle-log" id="battleLog"></div>
        
        <!-- CONTROLS -->
        <div class="controls">
            <div id="actionButtons" class="action-form">
                <!-- Actions générées dynamiquement par JS -->
            </div>
            <div id="waitingMessage" class="waiting-text waiting-message-hidden">
                En attente de l'adversaire...
            </div>
            <div id="gameOverMessage" class="game-over-section">
                <h3 id="gameOverText"></h3>
                <button class="action-btn new-game" onclick="location.href='index.php'">Menu Principal</button>
            </div>
        </div>
    </div>
</div>

<script>
const MATCH_ID = '<?php echo $matchId; ?>';
const IS_P1 = <?php echo $isP1 ? 'true' : 'false'; ?>;
const IS_BOT_MATCH = <?php echo $isBotMatch ? 'true' : 'false'; ?>;

let pollInterval = null;
let lastLogCount = 0;

function updateCombatState() {
    fetch('api.php?action=poll_status&match_id=' + MATCH_ID)
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
            if (data.actions && data.waiting_for_me) {
                btnContainer.innerHTML = '';
                for (const [key, action] of Object.entries(data.actions)) {
                    const button = document.createElement('button');
                    button.className = `action-btn ${key}${action.canUse ? '' : ' disabled'}`;
                    button.title = action.description || '';
                    button.disabled = !action.canUse || data.isOver;
                    button.onclick = () => sendAction(key);
                    
                    let buttonText = (action.emoji || '') + ' ' + action.label;
                    if (action.ppText) {
                        buttonText += ' ' + action.ppText;
                    }
                    button.innerHTML = buttonText;
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
                    if (IS_BOT_MATCH) {
                        waitMsg.innerText = "Le bot joue...";
                    } else {
                        waitMsg.innerText = "En attente de l'adversaire...";
                    }
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
    
    btnContainer.style.display = 'none';
    waitMsg.style.display = 'block';
    waitMsg.innerText = 'Envoi de l\'action...';
    
    fetch('api.php?action=submit_move', {
        method: 'POST',
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
