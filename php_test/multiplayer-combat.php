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
$multiCombat = null;

// Essayer de charger l'état existant
if (file_exists($stateFile)) {
    $multiCombat = MultiCombat::load($stateFile);
}

// Si pas d'état, créer un état initial (pour les tests UI)
if (!$multiCombat && isset($matchData['player1']['hero'])) {
    // Initialiser le combat si pas encore créé
    try {
        $multiCombat = MultiCombat::create($matchData['player1'], $matchData['player2']);
        if ($multiCombat && !$multiCombat->save($stateFile)) {
            error_log("Erreur: Impossible de sauvegarder l'état initial du combat.");
        }
    } catch (Exception $e) {
        error_log("Erreur lors de l'initialisation du combat: " . $e->getMessage());
        // Continuer sans état - pour les tests UI
    }
}

// Déterminer le rôle du joueur actuel
$sessionId = session_id();
$isP1 = ($matchData['player1']['session'] === $sessionId);
$myRole = $isP1 ? 'p1' : 'p2';

// DÉTECTION MODE 5v5
$is5v5 = $matchData['mode'] === '5v5' || ($multiCombat instanceof TeamCombat);
$teamSidebars = ['p1' => [], 'p2' => []];
$isTestUI = isset($matchData['player1']['heroes']) && isset($matchData['player2']['heroes']);

if ($is5v5 && $isTestUI) {
    $teamSidebars['p1'] = $matchData['player1']['heroes'] ?? [];
    $teamSidebars['p2'] = $matchData['player2']['heroes'] ?? [];
}

// Construire l'état du jeu pour l'affichage
try {
    if ($multiCombat) {
        $gameState = $multiCombat->getStateForUser($sessionId, $matchData);
        $gameState['status'] = 'active';
        $gameState['turn'] = $matchData['turn'] ?? 1;
        $gameState['isOver'] = $multiCombat->isOver();
    } else {
        // Mode test UI - créer un état par défaut
        $myPlayer = $isP1 ? $matchData['player1'] : $matchData['player2'];
        $oppPlayer = $isP1 ? $matchData['player2'] : $matchData['player1'];
        $myHero = $myPlayer['hero'] ?? $myPlayer['heroes'][0] ?? null;
        $oppHero = $oppPlayer['hero'] ?? $oppPlayer['heroes'][0] ?? null;
        
        $gameState = [
            'status' => 'active',
            'turn' => 1,
            'isOver' => false,
            'waiting_for_me' => true,
            'waiting_for_opponent' => false,
            'logs' => $matchData['logs'] ?? ["Test UI 5v5"],
            'turnActions' => [],
            'me' => [
                'name' => $myHero['name'] ?? 'Héros 1',
                'type' => $myHero['type'] ?? 'Unknown',
                'pv' => $myHero['pv'] ?? 100,
                'max_pv' => $myHero['pv'] ?? 100,
                'atk' => $myHero['atk'] ?? 20,
                'def' => $myHero['def'] ?? 5,
                'speed' => $myHero['speed'] ?? 10,
                'img' => $myHero['images']['p1'] ?? 'media/heroes/default.png',
                'activeEffects' => []
            ],
            'opponent' => [
                'name' => $oppHero['name'] ?? 'Héros 2',
                'type' => $oppHero['type'] ?? 'Unknown',
                'pv' => $oppHero['pv'] ?? 100,
                'max_pv' => $oppHero['pv'] ?? 100,
                'atk' => $oppHero['atk'] ?? 20,
                'def' => $oppHero['def'] ?? 5,
                'speed' => $oppHero['speed'] ?? 10,
                'img' => $oppHero['images']['p1'] ?? 'media/heroes/default.png',
                'activeEffects' => []
            ],
            'actions' => [
                'attack' => ['label' => 'Attaque', 'emoji' => '⚔️', 'description' => 'Attaque normale', 'canUse' => true, 'ppText' => ''],
                'spell' => ['label' => 'Sort', 'emoji' => '✨', 'description' => 'Utilise un sort', 'canUse' => true, 'ppText' => ''],
                'defend' => ['label' => 'Défense', 'emoji' => '🛡️', 'description' => 'Se défendre', 'canUse' => true, 'ppText' => '']
            ]
        ];
    }
    
    // Déterminer qui doit jouer
    $actions = $matchData['current_turn_actions'] ?? [];
    $oppRole = $isP1 ? 'p2' : 'p1';
    if (!isset($gameState['waiting_for_me'])) {
        $gameState['waiting_for_me'] = !isset($actions[$myRole]);
    }
    if (!isset($gameState['waiting_for_opponent'])) {
        $gameState['waiting_for_opponent'] = isset($actions[$myRole]) && !isset($actions[$oppRole]) && ($matchData['mode'] ?? '') !== 'bot';
    }
    
} catch (Exception $e) {
    die("Erreur lors de la construction de l'état du jeu: " . $e->getMessage());
}

?>

<link rel="icon" href="./media/website/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="./style.css">
<link rel="stylesheet" href="./css/shared-selection.css">
<link rel="stylesheet" href="./css/combat.css">

<h1 class="arena-title">Horus Battle Arena</h1>

<div class="game-container <?php echo $is5v5 ? 'mode-5v5' : ''; ?>">
    <?php if ($is5v5): ?>
    <!-- TEAM 1 SIDEBAR (caché sur petit écran) -->
    <aside class="team-sidebar team-1" id="teamSidebar1">
        <div class="sidebar-header">
            <h3>MON ÉQUIPE</h3>
            <button class="sidebar-close" onclick="closeTeamDrawer(1)">✕</button>
        </div>
        <div class="team-heroes-list" id="team1HeroesList"></div>
    </aside>
    
    <!-- DRAWER BUTTONS pour mobile -->
    <button class="drawer-toggle team-1-toggle" id="drawerToggle1" onclick="toggleTeamDrawer(1)" title="Équipe 1">◄</button>
    <?php endif; ?>
    
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
            
            <?php if ($is5v5): ?>
            <!-- BOUTON DE SWITCH POUR 5v5 -->
            <div class="switch-button-container">
                <button type="button" class="action-btn switch-btn" id="switchBtn" onclick="showSwitchMenu()">
                    <span class="action-label">SWITCH</span>
                </button>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="abandon-form">
                <button type="submit" name="abandon_multi" class="action-btn abandon">Abandonner</button>
            </form>
        </div>
    </div>
    
    <?php if ($is5v5): ?>
    <!-- TEAM 2 SIDEBAR (caché sur petit écran) -->
    <aside class="team-sidebar team-2" id="teamSidebar2">
        <div class="sidebar-header">
            <h3>ADVERSAIRE</h3>
            <button class="sidebar-close" onclick="closeTeamDrawer(2)">✕</button>
        </div>
        <div class="team-heroes-list" id="team2HeroesList"></div>
    </aside>
    
    <!-- DRAWER BUTTON pour mobile (droite) -->
    <button class="drawer-toggle team-2-toggle" id="drawerToggle2" onclick="toggleTeamDrawer(2)" title="Équipe 2">►</button>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="js/combat-animations.js"></script>
<style>
/* ===== 5v5 TEAM SIDEBARS ===== */
.game-container.mode-5v5 {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    justify-content: center;
    position: relative;
}

.game-container.mode-5v5 .arena {
    flex: 0 1 auto;
}

.team-sidebar {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    padding: 1rem;
    background: rgba(10, 10, 10, 0.85);
    border: 1px solid var(--gold-accent);
    border-radius: 8px;
    min-width: 200px;
    max-height: 90vh;
    overflow-y: auto;
    scrollbar-color: rgba(184, 134, 11, 0.7) rgba(20, 20, 20, 0.8);
    scrollbar-width: thin;
}

.team-sidebar .sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid rgba(184, 134, 11, 0.5);
}

.team-sidebar h3 {
    margin: 0;
    color: var(--parchment-text);
    font-size: 1rem;
}

.sidebar-close {
    background: none;
    border: none;
    color: var(--parchment-text);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    display: none;
}

.team-heroes-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    overflow-y: auto;
    padding-right: 10px;
    scrollbar-color: rgba(184, 134, 11, 0.7) rgba(20, 20, 20, 0.8);
    scrollbar-width: thin;
}

.hero-card {
    background: rgba(20, 20, 20, 0.9);
    border: 1px solid #3a3a3a;
    border-radius: 6px;
    padding: 0.8rem;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.hero-card:hover {
    border-color: var(--gold-accent);
    background: rgba(28, 28, 28, 1);
}

.hero-card.active {
    border-color: var(--gold-accent);
    background: rgba(40, 35, 20, 0.9);
    box-shadow: 0 0 10px rgba(184, 134, 11, 0.25);
}

.hero-card.dead {
    opacity: 0.5;
    filter: grayscale(100%);
}

.hero-card-name {
    font-weight: bold;
    color: var(--text-light);
    margin-bottom: 0.3rem;
}

.hero-card-position {
    font-size: 0.8rem;
    color: #999;
    margin-bottom: 0.3rem;
}

.hero-card-hp {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-bottom: 0.3rem;
}

.hero-card-hp-bar {
    flex: 1;
    height: 6px;
    background: #333;
    border-radius: 3px;
    overflow: hidden;
}

.hero-card-hp-bar .fill {
    height: 100%;
    background: linear-gradient(90deg, #ff4444, #ffaa00);
    border-radius: 3px;
}

.hero-card-stats {
    display: flex;
    gap: 0.3rem;
    font-size: 0.8rem;
    color: #ccc;
}

.hero-card-stats span {
    flex: 1;
}

/* DRAWER MODE (Mobile/Small screens) */
@media (max-width: 1399px) {
    .game-container.mode-5v5 {
        flex-direction: column;
    }
    
    .team-sidebar {
        position: fixed;
        top: 0;
        width: 220px;
        height: 100vh;
        background: rgba(8, 8, 8, 0.98);
        border-right: 1px solid rgba(184, 134, 11, 0.6);
        border-radius: 0;
        padding: 1rem;
        z-index: 100;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        max-height: 100vh;
    }
    
    .team-sidebar.team-1 {
        left: 0;
    }
    
    .team-sidebar.team-2 {
        right: 0;
        transform: translateX(100%);
        border-right: none;
        border-left: 1px solid rgba(184, 134, 11, 0.6);
    }
    
    .team-sidebar.open {
        transform: translateX(0);
    }
    
    .sidebar-close {
        display: block;
    }
    
    .drawer-toggle {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--stone-gray);
        border: 1px solid var(--gold-accent);
        color: var(--text-light);
        font-size: 1.5rem;
        cursor: pointer;
        z-index: 99;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .drawer-toggle:hover {
        background: var(--gold-accent);
        color: var(--dark-bg);
    }
    
    .drawer-toggle.team-1-toggle {
        left: 10px;
    }
    
    .drawer-toggle.team-2-toggle {
        right: 10px;
    }
}

/* GRAND ÉCRAN */
@media (min-width: 1400px) {
    .drawer-toggle {
        display: none !important;
    }
    
    .sidebar-close {
        display: none !important;
    }
    
    .team-sidebar {
        display: flex !important;
        transform: none !important;
    }
}

/* SWITCH BUTTON CONTAINER */
.switch-button-container {
    display: flex;
    justify-content: center;
    margin-top: 0.8rem;
    margin-bottom: 0.5rem;
}

.switch-btn {
    min-width: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    background: linear-gradient(180deg, var(--stone-gray) 0%, var(--dungeon-gray) 100%);
    border: 1px solid var(--gold-accent) !important;
    color: var(--text-light);
    letter-spacing: 1px;
}

.switch-btn:hover:not(.disabled) {
    box-shadow: 0 0 12px rgba(184, 134, 11, 0.35);
    transform: translateY(-1px);
}

.switch-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.switch-btn .action-label {
    width: 100%;
    text-align: center;
}

/* SWITCH MODE - HERO SELECTION */

.hero-card.selectable {
    outline: 2px solid var(--gold-accent);
    outline-offset: 2px;
    background-color: rgba(184, 134, 11, 0.12) !important;
    cursor: pointer;
    transition: all 0.2s ease;
}

.hero-card.selectable:hover {
    outline-width: 3px;
    outline-offset: 3px;
    background-color: rgba(184, 134, 11, 0.2) !important;
    transform: scale(1.02);
}

/* Désactiver les autres actions en mode switch */
.game-container.switch-mode .action-btn {
    opacity: 0.4;
    pointer-events: none;
}

.game-container.switch-mode .switch-btn {
    opacity: 1;
    pointer-events: auto;
    background: linear-gradient(180deg, var(--gold-accent) 0%, #8b6914 100%) !important;
    color: var(--dark-bg);
}

.game-container.switch-mode .switch-btn:hover {
    background: linear-gradient(180deg, #d4a10d 0%, var(--gold-accent) 100%) !important;
}

/* Scrollbar styling (WebKit) */
.team-sidebar::-webkit-scrollbar,
.team-heroes-list::-webkit-scrollbar {
    width: 8px;
}

.team-sidebar::-webkit-scrollbar-track,
.team-heroes-list::-webkit-scrollbar-track {
    background: rgba(20, 20, 20, 0.8);
    border-radius: 6px;
}

.team-sidebar::-webkit-scrollbar-thumb,
.team-heroes-list::-webkit-scrollbar-thumb {
    background: rgba(184, 134, 11, 0.7);
    border-radius: 6px;
    border: 1px solid rgba(0, 0, 0, 0.6);
}

.team-sidebar::-webkit-scrollbar-thumb:hover,
.team-heroes-list::-webkit-scrollbar-thumb:hover {
    background: rgba(184, 134, 11, 0.9);
}

</style>
<script>
const MATCH_ID = '<?php echo addslashes($matchId); ?>';
const INITIAL_STATE = <?php echo json_encode($gameState); ?>;
const IS_P1 = <?php echo $isP1 ? 'true' : 'false'; ?>; // True if I am player1 in the match
const IS_5V5 = <?php echo $is5v5 ? 'true' : 'false'; ?>;
const IS_TEST_UI = <?php echo $isTestUI ? 'true' : 'false'; ?>;
const TEAM_DATA_P1 = <?php echo json_encode($teamSidebars['p1']); ?>;
const TEAM_DATA_P2 = <?php echo json_encode($teamSidebars['p2']); ?>;

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
    // Mode test UI - pas de polling
    if (IS_TEST_UI) {
        console.log('TEST UI MODE - Pas de polling');
        return;
    }

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
if (!IS_TEST_UI) {
    updateCombatState();
    pollInterval = setInterval(updateCombatState, 2000);
} else {
    console.log('Test UI mode - polling désactivé');
}

// ===== 5v5 TEAM MANAGEMENT =====

/**
 * Toggle team drawer (mobile view)
 */
function toggleTeamDrawer(teamNum) {
    const sidebar = document.getElementById('teamSidebar' + teamNum);
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
}

/**
 * Close team drawer
 */
function closeTeamDrawer(teamNum) {
    const sidebar = document.getElementById('teamSidebar' + teamNum);
    if (sidebar) {
        sidebar.classList.remove('open');
    }
}

/**
 * Initialiser les sidebars pour le 5v5 (peuplés par JS une fois les données reçues)
 */
function initializeTeamSidebars() {
    if (!IS_5V5) return;
    
    console.log('Initializing team sidebars');
    
    // Déterminer quelle équipe afficher de quel côté
    const myTeamData = IS_P1 ? TEAM_DATA_P1 : TEAM_DATA_P2;
    const oppTeamData = IS_P1 ? TEAM_DATA_P2 : TEAM_DATA_P1;
    
    // Sidebar 1 (gauche) = toujours mon équipe
    // Sidebar 2 (droite) = toujours l'adversaire
    populateTeamSidebar(1, myTeamData, true);
    populateTeamSidebar(2, oppTeamData, false);
}

/**
 * Remplir une sidebar avec les héros de l'équipe
 */
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

/**
 * Créer une carte héro pour la sidebar
 */
function createHeroCard(heroData, index, isMyTeam) {
    const card = document.createElement('div');
    card.className = 'hero-card';
    
    // Marquer le héros actif (le premier combat)
    if (index === 0) {
        card.classList.add('active');
    }
    
    // Marquer comme mort si HP <= 0
    if (heroData.pv <= 0) {
        card.classList.add('dead');
    }
    
    const hpPercent = Math.max(0, (heroData.pv / heroData.pv) * 100);
    
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
            ${heroData.pv} / ${heroData.pv} PV
        </div>
    `;
    
    // Ajouter event pour switch (plus tard)
    card.addEventListener('click', () => {
        if (!card.classList.contains('dead') && !card.classList.contains('active')) {
            performSwitch(index);
        }
    });
    
    return card;
}

/**
 * Échapper le HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Afficher le menu de switch (sélection du héros à switcher)
 */
function showSwitchMenu() {
    if (!IS_5V5) return;
    
    // Marquer qu'on est en mode switch
    const container = document.querySelector('.game-container');
    if (container) {
        container.classList.add('switch-mode');
    }
    
    // Ouvrir les drawers si mobile (largeur < 1400px)
    const isMobileView = window.innerWidth < 1400;
    let drawersOpened = [];
    
    if (isMobileView) {
        const sidebar1 = document.getElementById('teamSidebar1');
        const sidebar2 = document.getElementById('teamSidebar2');
        
        if (sidebar1 && !sidebar1.classList.contains('open')) {
            sidebar1.classList.add('open');
            drawersOpened.push(1);
        }
        if (sidebar2 && !sidebar2.classList.contains('open')) {
            sidebar2.classList.add('open');
            drawersOpened.push(2);
        }
    }
    
    // Ajouter le marqueur des drawers automatiquement ouverts
    container.dataset.switchDrawersOpened = JSON.stringify(drawersOpened);
    
    // Déterminer l'équipe du joueur
    const myTeamData = IS_P1 ? TEAM_DATA_P1 : TEAM_DATA_P2;
    
    // Mettre les héros vivants en surbrillance et les rendre cliquables
    // Les héros cliquables sont dans la première sidebar (équipe du joueur)
    const heroCards = document.querySelectorAll('#teamSidebar1 .hero-card');
    heroCards.forEach((card, index) => {
        const heroData = myTeamData[index];
        
        if (heroData && !heroData.isDead && !card.classList.contains('active')) {
            // C'est un héros vivant et non actif de notre équipe
            card.classList.add('selectable');
            card.style.cursor = 'pointer';
            
            // Event listener pour cliquer et switcher (une seule fois)
            card.addEventListener('click', function switchHeroHandler() {
                performSwitch(index);
            }, { once: true });
        }
    });
}

/**
 * Effectuer un switch vers un autre héros
 */
function performSwitch(heroIndex) {
    if (!IS_5V5) return;
    
    // Envoyer l'action de switch au serveur
    stopActionTimer();
    const btnContainer = document.getElementById('actionButtons');
    const waitMsg = document.getElementById('waitingMessage');
    const container = document.querySelector('.game-container');
    
    btnContainer.style.display = 'none';
    waitMsg.style.display = 'block';
    waitMsg.innerText = 'Changement de héros...';
    
    // Récupérer la liste des drawers qui ont été auto-ouverts
    const switchDrawersOpened = JSON.parse(container.dataset.switchDrawersOpened || '[]');

    // Mode test UI - switch local sans API
    if (IS_TEST_UI) {
        const myTeamData = IS_P1 ? TEAM_DATA_P1 : TEAM_DATA_P2;
        const heroData = myTeamData[heroIndex];
        if (heroData) {
            setActiveHeroCard(heroIndex);
            updateMyHeroDisplay(heroData);
        }
        cleanupSwitchMode();
        switchDrawersOpened.forEach(teamNum => closeTeamDrawer(teamNum));
        waitMsg.style.display = 'none';
        btnContainer.style.display = 'flex';
        return;
    }
    
    fetch('api.php?action=submit_move', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'match_id=' + MATCH_ID + '&move=switch:' + heroIndex
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
                cleanupSwitchMode();
                return;
            }
            
            if (data.status === 'error') {
                showErrorMessage("Erreur: " + (data.message || "Erreur inconnue"));
                btnContainer.style.display = 'flex';
                waitMsg.style.display = 'none';
            } else if (data.status === 'ok') {
                waitMsg.innerText = "En attente de l'adversaire...";
            }
            
            // Nettoyage du mode switch
            cleanupSwitchMode();
            
            // Fermer les drawers qui ont été auto-ouverts
            switchDrawersOpened.forEach(teamNum => {
                closeTeamDrawer(teamNum);
            });
        })
        .catch(err => {
            console.error('Switch error:', err);
            showErrorMessage('Erreur: ' + err.message);
            btnContainer.style.display = 'flex';
            waitMsg.style.display = 'none';
            cleanupSwitchMode();
        });
}

/**
 * Mettre à jour l'affichage du héros actif (mode test UI)
 */
function updateMyHeroDisplay(heroData) {
    const nameEl = document.getElementById('myName');
    const typeEl = document.getElementById('myType');
    const statsEl = document.getElementById('myStats');
    const pvBar = document.getElementById('myPvBar');
    const fighter = document.getElementById('myFighter');
    const imgEl = fighter ? fighter.querySelector('img') : null;
    
    if (nameEl) nameEl.innerText = heroData.name || 'Héros';
    if (typeEl) typeEl.innerText = heroData.type || 'Unknown';
    if (statsEl) statsEl.innerText = Math.round(heroData.pv) + " / " + heroData.pv + " | ATK: " + heroData.atk + " | DEF: " + heroData.def;
    if (pvBar) pvBar.style.width = '100%';
    if (imgEl && heroData.images && heroData.images.p1) {
        imgEl.src = heroData.images.p1;
    }
}

/**
 * Mettre à jour la surbrillance du héros actif dans la sidebar
 */
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

/**
 * Nettoyer le mode switch (retirer surbrillance et classes)
 */
function cleanupSwitchMode() {
    const container = document.querySelector('.game-container');
    if (container) {
        container.classList.remove('switch-mode');
        container.dataset.switchDrawersOpened = '[]';
    }
    
    // Retirer la surbrillance des héros
    const heroCards = document.querySelectorAll('.hero-card.selectable');
    heroCards.forEach(card => {
        card.classList.remove('selectable');
        card.style.cursor = '';
    });
}

// Initialiser les sidebars si 5v5
if (IS_5V5) {
    initializeTeamSidebars();
}
</script>

