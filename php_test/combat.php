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

session_start();

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
$sessionId = session_id();

// Déterminer si c'est le joueur 1 ou 2
$isP1 = ($matchData['player1']['session'] === $sessionId);
if (!$isP1 && $matchData['player2']['session'] !== $sessionId) {
    die("Non autorisé");
}

// Images des héros
$myHero = $isP1 ? $matchData['player1']['hero'] : $matchData['player2']['hero'];
$oppHero = $isP1 ? $matchData['player2']['hero'] : $matchData['player1']['hero'];
$myImg = $myHero['images']['p1'];
$oppImg = $oppHero['images']['p1'];
$isBotMatch = ($matchData['mode'] ?? '') === 'bot';
?>

<link rel="stylesheet" href="./style.css">

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
                <button class="action-btn attack" onclick="sendAction('attack')">⚔️ Attaque</button>
                <button class="action-btn heal" onclick="sendAction('heal')">💖 Soin</button>
            </div>
            <div id="waitingMessage" class="waiting-text" style="display:none; margin-top: 15px;">
                En attente de l'adversaire...
            </div>
            <div id="gameOverMessage" style="display:none; text-align:center; margin-top: 30px;">
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
        .then(r => r.json())
        .then(data => {
            if (data.status === 'error') {
                alert("Erreur: " + data.message);
                location.href = 'index.php';
                return;
            }

            // Update UI
            document.getElementById('turnIndicator').innerText = "Tour " + data.turn;
            
            // Player stats
            document.getElementById('myName').innerText = data.me.name;
            document.getElementById('myType').innerText = data.me.type;
            document.getElementById('myStats').innerText = Math.round(data.me.pv) + " / " + data.me.max_pv;
            document.getElementById('myPvBar').style.width = (data.me.pv / data.me.max_pv * 100) + "%";
            
            // Opponent stats
            document.getElementById('oppName').innerText = data.opponent.name;
            document.getElementById('oppType').innerText = data.opponent.type;
            document.getElementById('oppStats').innerText = Math.round(data.opponent.pv) + " / " + data.opponent.max_pv;
            document.getElementById('oppPvBar').style.width = (data.opponent.pv / data.opponent.max_pv * 100) + "%";
            
            // Logs
            const logBox = document.getElementById('battleLog');
            if (data.logs.length > lastLogCount) {
                for (let i = lastLogCount; i < data.logs.length; i++) {
                    let div = document.createElement('div');
                    div.className = 'log-line';
                    div.innerText = data.logs[i];
                    logBox.appendChild(div);
                }
                lastLogCount = data.logs.length;
                logBox.scrollTop = logBox.scrollHeight;
            }
            
            // Controls
            const btnContainer = document.getElementById('actionButtons');
            const waitMsg = document.getElementById('waitingMessage');
            const gameOverMsg = document.getElementById('gameOverMessage');
            
            if (data.isOver) {
                clearInterval(pollInterval);
                btnContainer.style.display = 'none';
                waitMsg.style.display = 'none';
                gameOverMsg.style.display = 'block';
                
                const gameOverText = document.getElementById('gameOverText');
                if (data.winner === 'me') {
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
        });
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
            if (data.status !== 'ok') {
                alert("Erreur: " + data.message);
                btnContainer.style.display = 'flex';
                waitMsg.style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Action error:', err);
            btnContainer.style.display = 'flex';
            waitMsg.style.display = 'none';
        });
}

// Initial load and start polling
updateCombatState();
pollInterval = setInterval(updateCombatState, 2000);
</script>
