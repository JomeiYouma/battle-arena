<?php
/**
 * MULTIPLAYER COMBAT PAGE
 * Interface complète de combat multiplayer avec polling en temps réel
 * Basée sur la structure de single_player.php
 */

// Autoloader centralisé (démarre la session automatiquement)
require_once __DIR__ . '/../../includes/autoload.php';

// --- ABANDON ---
if (isset($_POST['abandon_multi'])) {
    $matchId = $_GET['match_id'] ?? $_SESSION['matchId'] ?? null;
    
    if ($matchId) {
        $matchFile = DATA_PATH . '/matches/' . $matchId . '.json';
        if (file_exists($matchFile)) {
            $fp = fopen($matchFile, 'r+');
            if (flock($fp, LOCK_EX)) {
                $metaData = json_decode(stream_get_contents($fp), true);
                if ($metaData) {
                    $sessionId = session_id();
                    $isP1 = ($metaData['player1']['session'] === $sessionId);
                    $winnerKey = $isP1 ? 'player2' : 'player1';
                    $loserKey = $isP1 ? 'player1' : 'player2';
                    $loserName = $isP1 ? ($metaData['player1']['display_name'] ?? 'Joueur 1') : ($metaData['player2']['display_name'] ?? 'Joueur 2');
                    
                    $metaData['status'] = 'finished';
                    $metaData['winner'] = $winnerKey;
                    $metaData['logs'][] = "🏳️ " . $loserName . " a abandonné !";
                    
                    // === ENREGISTRER LES STATS LORS DE L'ABANDON ===
                    $mode = $metaData['mode'] ?? '';
                    $isVsBotMatch = $mode === 'bot' || !empty($metaData['player2']['is_bot']);
                    
                    if (!$isVsBotMatch && ($mode === 'pvp' || $mode === '5v5')) {
                        $p1UserId = $metaData['player1']['user_id'] ?? null;
                        $p2UserId = $metaData['player2']['user_id'] ?? null;
                        
                        $userModel = new User();
                        $matchUuid = $matchId; // Utiliser le matchId comme UUID unique
                        
                        if ($mode === '5v5') {
                            // Mode 5v5 : enregistrer tous les héros de chaque équipe
                            $p1Heroes = array_column($metaData['player1']['heroes'] ?? [], 'id');
                            $p2Heroes = array_column($metaData['player2']['heroes'] ?? [], 'id');
                            $p1TeamName = $metaData['player1']['team_name'] ?? 'Équipe 1';
                            $p2TeamName = $metaData['player2']['team_name'] ?? 'Équipe 2';
                            $p1DisplayName = $metaData['player1']['display_name'] ?? 'Joueur 1';
                            $p2DisplayName = $metaData['player2']['display_name'] ?? 'Joueur 2';
                            
                            // Enregistrer pour P1 (tous les héros de son équipe)
                            if ($p1UserId && !empty($p1Heroes)) {
                                $userModel->recordTeamCombat(
                                    $p1UserId,
                                    $p1Heroes,
                                    $winnerKey === 'player1',
                                    $p1TeamName,
                                    $p2DisplayName,
                                    $matchUuid
                                );
                            }
                            
                            // Enregistrer pour P2 (tous les héros de son équipe)
                            if ($p2UserId && !empty($p2Heroes)) {
                                $userModel->recordTeamCombat(
                                    $p2UserId,
                                    $p2Heroes,
                                    $winnerKey === 'player2',
                                    $p2TeamName,
                                    $p1DisplayName,
                                    $matchUuid
                                );
                            }
                        } else {
                            // Mode 1v1 : ancien comportement
                            $p1HeroId = $metaData['player1']['hero']['id'] ?? null;
                            $p2HeroId = $metaData['player2']['hero']['id'] ?? null;
                            
                            if ($p1UserId && $p1HeroId) {
                                $userModel->recordCombat(
                                    $p1UserId,
                                    $p1HeroId,
                                    $winnerKey === 'player1',
                                    $p2HeroId,
                                    'multi'
                                );
                            }
                            
                            if ($p2UserId && $p2HeroId) {
                                $userModel->recordCombat(
                                    $p2UserId,
                                    $p2HeroId,
                                    $winnerKey === 'player2',
                                    $p1HeroId,
                                    'multi'
                                );
                            }
                        }
                    }
                    // === FIN ENREGISTREMENT STATS ===
                    
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
    
    header("Location: ../../index.php");
    exit;
}

// Récupérer le matchId depuis l'URL
$matchId = $_GET['match_id'] ?? $_SESSION['matchId'] ?? null;

if (!$matchId) {
    header("Location: ../../index.php");
    exit;
}

$_SESSION['matchId'] = $matchId;

// Charger l'état initial du match directement depuis le fichier
// (Plus fiable que file_get_contents avec URL relative)
$matchFile = DATA_PATH . '/matches/' . $matchId . '.json';

if (!file_exists($matchFile)) {
    // Match inexistant - nettoyer la session et rediriger
    unset($_SESSION['matchId']);
    unset($_SESSION['queue5v5Status']);
    unset($_SESSION['queue5v5Team']);
    unset($_SESSION['queue5v5DisplayName']);
    header("Location: multiplayer.php?error=match_not_found");
    exit;
}

$matchData = json_decode(file_get_contents($matchFile), true);

if (!$matchData) {
    die("Erreur: Impossible de lire les données du match.");
}

// Charger l'état du combat via MultiCombat (ou TeamCombat pour 5v5)
// L'autoloader se charge des classes MultiCombat et TeamCombat

$stateFile = DATA_PATH . '/matches/' . $matchId . '.state';
$multiCombat = null;

// Essayer de charger l'état existant
if (file_exists($stateFile)) {
    $multiCombat = MultiCombat::load($stateFile);
}

// Si pas d'état, créer un état initial (pour les tests UI ou nouveau combat)
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

// isTestUI = vrai seulement si pas de fichier de combat (mode visualisation UI sans vrai combat)
$isTestUI = !$multiCombat && isset($matchData['player1']['heroes']) && isset($matchData['player2']['heroes']);

// Noms d'équipes pour l'UI
$myTeamName = $isP1 ? ($matchData['player1']['display_name'] ?? 'Mon Équipe') : ($matchData['player2']['display_name'] ?? 'Mon Équipe');
$oppTeamName = $isP1 ? ($matchData['player2']['display_name'] ?? 'Adversaire') : ($matchData['player1']['display_name'] ?? 'Adversaire');

if ($is5v5) {
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
        
        // Corriger les chemins d'images pour l'affichage
        if (!empty($gameState['me']['img'])) {
            $gameState['me']['img'] = asset_url($gameState['me']['img']);
        } else {
            $gameState['me']['img'] = asset_url('media/heroes/default.png');
        }
        if (!empty($gameState['opponent']['img'])) {
            $gameState['opponent']['img'] = asset_url($gameState['opponent']['img']);
        } else {
            $gameState['opponent']['img'] = asset_url('media/heroes/default.png');
        }
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
                'img' => asset_url($myHero['images'][$isP1 ? 'p1' : 'p2'] ?? 'media/heroes/default.png'),
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
                'img' => asset_url($oppHero['images'][$isP1 ? 'p2' : 'p1'] ?? 'media/heroes/default.png'),
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

<link rel="icon" href="../../public/media/website/favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="../../public/css/style.css">
<link rel="stylesheet" href="../../public/css/shared-selection.css">
<link rel="stylesheet" href="../../public/css/combat.css">

<h1 class="arena-title">Horus Battle Arena</h1>

<div class="game-container <?php echo $is5v5 ? 'mode-5v5' : ''; ?>">
    <?php if ($is5v5): ?>
    <!-- TEAM 1 SIDEBAR (caché sur petit écran) -->
    <aside class="team-sidebar team-1" id="teamSidebar1">
        <div class="sidebar-header">
            <h3 id="myTeamName"><?php echo htmlspecialchars($myTeamName); ?></h3>
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
                <!-- LEFT: Action list + Switch -->
                <div class="action-container-5v5" id="actionContainer">
                    <?php if ($is5v5): ?>
                    <!-- BOUTON DE SWITCH EN PREMIER pour 5v5 -->
                    <button type="button" class="action-btn switch-btn" id="switchBtn" onclick="showSwitchMenu()" data-tooltip="Changer de héros actif">
                        <span class="action-emoji-icon">🔄</span>
                        <span class="action-label">SWITCH</span>
                    </button>
                    <?php endif; ?>
                    
                    <div id="actionButtons" class="action-list">
                        <!-- Actions générées dynamiquement par JS -->
                    </div>
                </div>
                
                <!-- RIGHT: Timer + Abandonner -->
                <div id="infoPanel" class="info-panel">
                    <div id="actionTimer" class="action-timer-circle">
                        <!-- SVG Ring -->
                        <svg class="timer-svg" viewBox="0 0 70 70">
                            <circle class="timer-circle-bg" cx="35" cy="35" r="32"></circle>
                            <circle id="timerProgress" class="timer-circle-progress" cx="35" cy="35" r="32"></circle>
                        </svg>
                        <span id="timerValue">60</span>
                    </div>
                    
                    <form method="POST" class="abandon-form-inline">
                        <button type="submit" name="abandon_multi" class="action-btn abandon">Abandonner</button>
                    </form>
                </div>
            </div>
            
            <div id="waitingMessage" class="waiting-text waiting-message-hidden">
                En attente de l'adversaire...
            </div>
            <div id="gameOverMessage" class="game-over-section">
                <h3 id="gameOverText"></h3>
                <br>
                <button class="action-btn new-game" onclick="location.href='multiplayer.php'">Rejouer</button>
                <button class="action-btn" onclick="location.href='../../index.php'" style="margin-left: 10px;">Menu Principal</button>
            </div>
        </div>
    </div>
    
    <?php if ($is5v5): ?>
    <!-- TEAM 2 SIDEBAR (caché sur petit écran) -->
    <aside class="team-sidebar team-2" id="teamSidebar2">
        <div class="sidebar-header">
            <h3 id="oppTeamName"><?php echo htmlspecialchars($oppTeamName); ?></h3>
            <button class="sidebar-close" onclick="closeTeamDrawer(2)">✕</button>
        </div>
        <div class="team-heroes-list" id="team2HeroesList"></div>
    </aside>
    
    <!-- DRAWER BUTTON pour mobile (droite) -->
    <button class="drawer-toggle team-2-toggle" id="drawerToggle2" onclick="toggleTeamDrawer(2)" title="Équipe 2">►</button>
    
    <!-- MODAL DE SWITCH -->
    <div id="switchModal" class="switch-modal" style="display:none;">
        <div class="switch-modal-content">
            <div class="switch-modal-header">
                <h3 id="switchModalTitle">🔄 Changer de Héros</h3>
                <button class="switch-modal-close" onclick="closeSwitchModal(true)">✕</button>
            </div>
            <p class="switch-modal-subtitle">Sélectionnez un héros pour remplacer votre combattant actuel</p>
            <div id="switchHeroesGrid" class="switch-heroes-grid">
                <!-- Héros disponibles générés par JS -->
            </div>
            <button class="switch-modal-cancel" onclick="closeSwitchModal(true)">Annuler</button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tooltip System -->
<div id="customTooltip" class="custom-tooltip"></div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
<script>
// Define asset base path before loading external scripts
const ASSET_BASE_PATH = '<?php echo asset_url(""); ?>';  // Path to public/ folder
</script>
<script src="../../public/js/combat-animations.js"></script>
<script src="../../public/js/selection-tooltip.js"></script>
<style>
/* ===== 5v5 TEAM SIDEBARS ===== */
.game-container.mode-5v5 {
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
    justify-content: center;
    position: relative;
    width: 100%;
    max-width: 1600px;
    margin: 0 auto;
}

.game-container.mode-5v5 .arena {
    flex: 1 1 auto;
    min-width: 0;
    max-width: 700px;
}

.team-sidebar {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.8rem;
    background: rgba(10, 10, 10, 0.85);
    border: 1px solid var(--gold-accent);
    border-radius: 8px;
    flex: 0 0 180px;
    max-height: 85vh;
    overflow-y: auto;
    scrollbar-color: rgba(184, 134, 11, 0.7) rgba(20, 20, 20, 0.8);
    scrollbar-width: thin;
}

.team-sidebar .sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.3rem;
    padding-bottom: 0.3rem;
    border-bottom: 1px solid rgba(184, 134, 11, 0.5);
}

.team-sidebar h3 {
    margin: 0;
    color: var(--parchment-text);
    font-size: 0.9rem;
}

.sidebar-close {
    background: none;
    border: none;
    color: var(--parchment-text);
    font-size: 1.2rem;
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
    border-radius: 4px;
    padding: 0.5rem;
    font-size: 0.75rem;
    transition: all 0.2s ease;
}

.hero-card:hover {
    border-color: var(--gold-accent);
    background: rgba(28, 28, 28, 1);
}

.hero-card.active {
    border-color: var(--gold-accent);
    background: rgba(40, 35, 20, 0.9);
    box-shadow: 0 0 8px rgba(184, 134, 11, 0.25);
}

.hero-card.dead {
    opacity: 0.5;
    filter: grayscale(100%);
}

.hero-card-name {
    font-weight: bold;
    color: var(--text-light);
    margin-bottom: 0.2rem;
    font-size: 0.8rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hero-card-position {
    font-size: 0.7rem;
    color: #999;
    margin-bottom: 0.2rem;
}

.hero-card-hp {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    margin-bottom: 0.2rem;
}

.hero-card-hp-bar {
    flex: 1;
    height: 4px;
    background: #333;
    border-radius: 2px;
    overflow: hidden;
}

.hero-card-hp-bar .fill {
    height: 100%;
    background: linear-gradient(90deg, #ff4444, #ffaa00);
    border-radius: 2px;
}

.hero-card-stats {
    display: flex;
    gap: 0.2rem;
    font-size: 0.7rem;
    color: #ccc;
}

.hero-card-stats span {
    flex: 1;
    white-space: nowrap;
}

/* DRAWER MODE (Mobile/Tablet screens) */
@media (max-width: 1199px) {
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

/* ===== AMÉLIORATION BOUTONS D'ACTION 5v5 ===== */

/* Override controls-row pour 5v5 - plus d'espace pour les actions */
.mode-5v5 .controls-row {
    height: auto;
    min-height: 200px;
    max-height: 400px;
    flex-wrap: wrap;
    align-items: flex-start;
}

/* Panneau info à droite (timer + abandonner) */
.mode-5v5 .info-panel {
    flex: 0 0 auto;
    min-width: 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 15px;
    padding: 10px;
}

/* Form abandonner inline dans le panneau droit */
.abandon-form-inline {
    margin: 0;
}

.abandon-form-inline .action-btn.abandon {
    font-size: 0.8rem;
    padding: 8px 16px;
}

/* Container pour les actions 5v5 */
.action-container-5v5 {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    flex: 1;
    max-height: 350px;
    overflow-y: auto;
    padding-right: 5px;
    
    /* Scrollbar styling */
    scrollbar-width: thin;
    scrollbar-color: var(--gold-accent) var(--dungeon-gray);
}

.action-container-5v5::-webkit-scrollbar {
    width: 6px;
}

.action-container-5v5::-webkit-scrollbar-track {
    background: var(--dungeon-gray);
    border-radius: 3px;
}

.action-container-5v5::-webkit-scrollbar-thumb {
    background: var(--gold-accent);
    border-radius: 3px;
}

/* Switch button en haut */
.mode-5v5 .switch-btn {
    width: 100%;
    max-width: 300px;
    min-height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: linear-gradient(180deg, var(--stone-gray) 0%, var(--dungeon-gray) 100%);
    border: 2px solid var(--gold-accent) !important;
    color: var(--text-light);
    font-size: 1rem;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.mode-5v5 .switch-btn:hover:not(.disabled) {
    box-shadow: 0 0 15px rgba(184, 134, 11, 0.5);
    background: linear-gradient(180deg, rgba(60, 50, 30, 0.95) 0%, rgba(40, 35, 20, 0.98) 100%);
}

.mode-5v5 .action-list {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 10px;
    width: 100%;
    max-width: 300px;
}

.mode-5v5 .action-list .action-btn {
    /* Largeur complète, layout horizontal dans le bouton */
    width: 100%;
    min-height: 55px;
    padding: 10px 15px;
    
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    
    /* Style */
    background: linear-gradient(180deg, rgba(40, 40, 40, 0.95) 0%, rgba(25, 25, 25, 0.98) 100%);
    border: 2px solid #555;
    border-radius: 8px;
    color: var(--text-light);
    
    /* Transition sans changement de taille */
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    box-sizing: border-box;
}

.mode-5v5 .action-list .action-btn:hover:not(:disabled) {
    border-color: var(--gold-accent);
    box-shadow: 0 0 15px rgba(184, 134, 11, 0.4);
    background: linear-gradient(180deg, rgba(50, 45, 30, 0.95) 0%, rgba(35, 30, 20, 0.98) 100%);
    /* PAS de transform pour éviter le décalage */
}

.mode-5v5 .action-list .action-btn .action-emoji-icon {
    font-size: 1.3rem;
    line-height: 1;
    min-width: 30px;
    text-align: center;
}

.mode-5v5 .action-list .action-btn .action-label {
    font-size: 0.9rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    line-height: 1.2;
    flex: 1;
}

.mode-5v5 .action-list .action-btn .action-pp {
    font-size: 0.8rem;
    color: var(--gold-accent);
    margin-left: auto;
    white-space: nowrap;;
    font-weight: normal;
    opacity: 0.9;
}

/* Couleurs spécifiques par type d'action */
.mode-5v5 .action-list .action-btn.attack {
    border-color: #c44;
}
.mode-5v5 .action-list .action-btn.attack:hover:not(:disabled) {
    border-color: #f66;
    box-shadow: 0 0 15px rgba(255, 80, 80, 0.4);
}

.mode-5v5 .action-list .action-btn.defend {
    border-color: #48c;
}
.mode-5v5 .action-list .action-btn.defend:hover:not(:disabled) {
    border-color: #6af;
    box-shadow: 0 0 15px rgba(100, 170, 255, 0.4);
}

.mode-5v5 .action-list .action-btn.spell,
.mode-5v5 .action-list .action-btn.heal {
    border-color: #4a4;
}
.mode-5v5 .action-list .action-btn.spell:hover:not(:disabled),
.mode-5v5 .action-list .action-btn.heal:hover:not(:disabled) {
    border-color: #6c6;
    box-shadow: 0 0 15px rgba(100, 200, 100, 0.4);
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

/* ===== SWITCH MODAL ===== */
.switch-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.switch-modal-content {
    background: linear-gradient(145deg, rgba(25, 25, 25, 0.98), rgba(35, 35, 35, 0.98));
    border: 2px solid var(--gold-accent);
    border-radius: 12px;
    padding: 2rem;
    max-width: 700px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
}

.switch-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.switch-modal-header h3 {
    color: var(--gold-accent);
    font-size: 1.5rem;
    margin: 0;
}

.switch-modal-close {
    background: none;
    border: none;
    color: #888;
    font-size: 1.8rem;
    cursor: pointer;
    transition: color 0.2s;
    padding: 0;
    line-height: 1;
}

.switch-modal-close:hover {
    color: #fff;
}

.switch-modal-subtitle {
    color: #aaa;
    font-size: 0.95rem;
    margin-bottom: 1.5rem;
}

.switch-modal.forced-switch .switch-modal-header h3 {
    color: #ff6b6b;
}

.switch-modal.forced-switch .switch-modal-content {
    border-color: #ff6b6b;
}

.switch-heroes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.switch-hero-card {
    background: rgba(30, 30, 30, 0.9);
    border: 2px solid #444;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.switch-hero-card:hover:not(.unavailable) {
    border-color: var(--gold-accent);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(184, 134, 11, 0.3);
}

.switch-hero-card.unavailable {
    opacity: 0.4;
    cursor: not-allowed;
    filter: grayscale(100%);
}

.switch-hero-card.active {
    border-color: #4a90e2;
    background: rgba(74, 144, 226, 0.15);
}

.switch-hero-card.active::after {
    content: "EN COMBAT";
    display: block;
    font-size: 0.65rem;
    color: #4a90e2;
    margin-top: 0.3rem;
    font-weight: bold;
}

.switch-hero-card img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #555;
    margin-bottom: 0.5rem;
}

.switch-hero-name {
    font-weight: bold;
    color: #fff;
    font-size: 0.9rem;
    margin-bottom: 0.3rem;
}

.switch-hero-hp {
    font-size: 0.85rem;
    color: #8f8;
}

.switch-hero-hp.low {
    color: #f88;
}

.switch-hero-hp.dead {
    color: #888;
}

.switch-modal-cancel {
    width: 100%;
    padding: 0.8rem;
    background: #444;
    color: #fff;
    border: 1px solid #666;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.2s;
}

.switch-modal-cancel:hover {
    background: #555;
    border-color: var(--gold-accent);
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

/* ===== SWITCH ANIMATIONS ===== */
.switch-out {
    animation: switchOut 0.4s ease forwards;
}

.switch-in {
    animation: switchIn 0.5s ease forwards;
}

@keyframes switchOut {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(0.8) rotate(-10deg); opacity: 0.5; }
    100% { transform: scale(0.5) translateY(-30px); opacity: 0; }
}

@keyframes switchIn {
    0% { transform: scale(0.5) translateY(30px); opacity: 0; }
    50% { transform: scale(1.1) rotate(5deg); opacity: 0.7; }
    100% { transform: scale(1) rotate(0deg); opacity: 1; }
}

.switch-emoji-animation {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 4rem;
    animation: switchEmoji 0.9s ease forwards;
    z-index: 10;
    pointer-events: none;
}

@keyframes switchEmoji {
    0% { transform: translate(-50%, -50%) scale(0) rotate(0deg); opacity: 0; }
    30% { transform: translate(-50%, -50%) scale(1.5) rotate(180deg); opacity: 1; }
    70% { transform: translate(-50%, -50%) scale(1.2) rotate(360deg); opacity: 1; }
    100% { transform: translate(-50%, -50%) scale(0.8) rotate(720deg); opacity: 0; }
}

/* Fighter hit animation */
.fighter-hit {
    animation: fighterShake 0.4s ease-in-out;
}

@keyframes fighterShake {
    0%, 100% { transform: translateX(0); }
    20% { transform: translateX(-10px); }
    40% { transform: translateX(10px); }
    60% { transform: translateX(-8px); }
    80% { transform: translateX(8px); }
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
// Note: ASSET_BASE_PATH is defined earlier before external script loading

let pollInterval = null;
let lastLogCount = <?php echo count($gameState['logs']); ?>;
let currentGameState = INITIAL_STATE;
let lastTurnProcessed = <?php echo $gameState['turn'] ?? 1; ?>;
let isPlayingAnimations = false;
let isSwitchModalManuallyOpen = false; // Flag pour éviter que le polling ferme le modal

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

// Switch animation - fade out old hero, change image, fade in new hero
function triggerSwitchAnimation(fighterElement, newImgSrc) {
    if (!fighterElement) return;
    
    const img = fighterElement.querySelector('img');
    if (!img) return;
    
    // Conserver la classe enemy-img si c'est l'opponent
    const isEnemy = img.classList.contains('enemy-img') || fighterElement.id === 'oppFighter';
    
    // Add switch-out animation
    fighterElement.classList.add('switch-out');
    
    // Show switch emoji indicator
    const switchEmoji = document.createElement('div');
    switchEmoji.className = 'switch-emoji-animation';
    switchEmoji.textContent = '🔄';
    fighterElement.appendChild(switchEmoji);
    
    // After fade out, change image and fade in
    setTimeout(() => {
        img.src = newImgSrc;
        // S'assurer que la classe enemy-img est présente pour l'opponent
        if (isEnemy) {
            img.classList.add('enemy-img');
        }
        fighterElement.classList.remove('switch-out');
        fighterElement.classList.add('switch-in');
        
        // Remove switch-in class after animation
        setTimeout(() => {
            fighterElement.classList.remove('switch-in');
            if (switchEmoji.parentElement) {
                switchEmoji.remove();
            }
        }, 500);
    }, 400);
}

// Description handling for action buttons - using data-tooltip for hover tooltip
function setupActionTooltips() {
    // Le système de tooltip est géré par selection-tooltip.js via data-tooltip
    // On s'assure juste que les boutons ont bien l'attribut data-tooltip
    document.querySelectorAll('.action-btn[data-description]').forEach(btn => {
        const desc = btn.getAttribute('data-description');
        if (desc && !btn.hasAttribute('data-tooltip')) {
            btn.setAttribute('data-tooltip', desc);
        }
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

// Update effect indicators (active effects displayed as emojis with duration badge and tooltip)
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
        indicator.className = 'effect-indicator' + (effect.isPending ? ' pending' : '');
        
        // Emoji de l'effet
        const emoji = document.createElement('span');
        emoji.className = 'effect-emoji';
        emoji.textContent = effect.emoji || '✨';
        indicator.appendChild(emoji);
        
        // Badge de durée (en bas à droite)
        const duration = effect.duration || effect.turnsDelay || 0;
        if (duration > 0) {
            const badge = document.createElement('span');
            badge.className = 'effect-duration-badge';
            badge.textContent = duration;
            indicator.appendChild(badge);
        }
        
        // Tooltip (infobulle)
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


function updateCombatState() {
    // Mode test UI - pas de polling
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
            
            // DEBUG: Log actions received
            console.log('Combat state received:', {
                turn: data.turn,
                waiting_for_me: data.waiting_for_me,
                actions: data.actions,
                needsForcedSwitch: data.needsForcedSwitch,
                isOver: data.isOver
            });

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
                
                // Update hero images if changed (after switch) with animation
                if (data.me.img) {
                    const myFighterImg = document.querySelector('#myFighter img');
                    // Comparer avec endsWith car src est une URL complète et data.me.img est relatif
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
                
                // Update 5v5 team sidebars with current HP data
                if (IS_5V5 && data.myTeam && data.oppTeam) {
                    updateTeamSidebarsWithData(data.myTeam, data.oppTeam, data.myActiveIndex, data.oppActiveIndex);
                }
            }            
            // Logs
            const logBox = document.getElementById('battleLog');
            if (data.logs && data.logs.length > lastLogCount) {
                for (let i = lastLogCount; i < data.logs.length; i++) {
                    let div = document.createElement('div');
                    div.className = 'log-line';
                    div.innerText = data.logs[i];
                    logBox.appendChild(div);
                    
                    // Détecter les switchs dans les logs
                    if (IS_5V5 && data.logs[i].includes('🔄')) {
                        updateSwitchedHeroCard(data.logs[i]);
                    }
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
                    // Skip switch action - it has its own button that opens the modal
                    if (key === 'switch') continue;
                    
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = `action-btn ${key}${action.canUse ? '' : ' disabled'}`;
                    // Utiliser data-tooltip pour le système de tooltip flottant
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
                
                // Update switch button state if exists
                const switchBtn = document.getElementById('switchBtn');
                if (switchBtn && data.actions.switch) {
                    switchBtn.disabled = !data.actions.switch.canUse;
                    switchBtn.classList.toggle('disabled', !data.actions.switch.canUse);
                }
                
                setupActionTooltips();
                startActionTimer();
            }
            
            // Check for forced switch (hero death)
            if (IS_5V5 && data.needsForcedSwitch) {
                // Hide action buttons
                btnContainer.innerHTML = '';
                btnContainer.style.display = 'none';
                
                // Show the switch modal in forced mode
                showSwitchMenu(true);
                
            } else {
                // Close switch modal if open
                closeSwitchModal();
            }
            
            if (data.isOver) {
                clearInterval(pollInterval);
                stopActionTimer();
                closeSwitchModal(true); // Force close on game over
                btnContainer.style.display = 'none';
                
                // Masquer aussi le bouton switch
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
                
                // Récupérer le container d'actions
                const actionContainer = document.getElementById('actionContainer');
                
                if (data.waiting_for_me) {
                    // Afficher le conteneur d'actions (contient switch + boutons)
                    if (actionContainer) actionContainer.style.display = 'flex';
                    // Réafficher aussi le conteneur de boutons (peut être caché par performSwitch)
                    if (btnContainer) btnContainer.style.display = '';
                    waitMsg.style.display = 'none';
                } else {
                    // Cacher le conteneur d'actions (cache automatiquement switch + boutons)
                    if (actionContainer) actionContainer.style.display = 'none';
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
    errorMsg.innerHTML = message + '<br><button onclick="location.href=\'../../index.php\'" class="error-return-btn">Retour Menu</button>';
    errorMsg.style.display = 'block';
}

function sendAction(action) {
    const actionContainer = document.getElementById('actionContainer');
    const waitMsg = document.getElementById('waitingMessage');
    
    stopActionTimer();
    // Cacher le conteneur d'actions (cache automatiquement switch et boutons)
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
    
    // PAS d'event click ici - le switch se fait via le bouton SWITCH et le modal
    // L'équipe adverse ne doit pas être cliquable
    
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
 * Mettre à jour les sidebars avec les données en temps réel du serveur
 */
function updateTeamSidebarsWithData(myTeamData, oppTeamData, myActiveIndex, oppActiveIndex) {
    if (!IS_5V5) return;
    
    // Mettre à jour la sidebar de mon équipe (sidebar 1)
    updateSidebarWithTeamData('team1HeroesList', myTeamData, myActiveIndex, true);
    
    // Mettre à jour la sidebar adverse (sidebar 2)
    updateSidebarWithTeamData('team2HeroesList', oppTeamData, oppActiveIndex, false);
    
    // Mettre à jour les données locales pour le modal de switch
    if (IS_P1) {
        // Mise à jour de TEAM_DATA_P1 et P2 avec les nouvelles valeurs
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

/**
 * Mettre à jour une sidebar spécifique avec les données d'équipe
 */
function updateSidebarWithTeamData(containerId, teamData, activeIndex, isMyTeam) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const heroCards = container.querySelectorAll('.hero-card');
    
    teamData.forEach((heroData, index) => {
        const card = heroCards[index];
        if (!card) return;
        
        // Mettre à jour le statut actif
        if (index === activeIndex) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
        
        // Mettre à jour le statut mort
        if (heroData.isDead || heroData.pv <= 0) {
            card.classList.add('dead');
        } else {
            card.classList.remove('dead');
        }
        
        // Mettre à jour la barre de HP
        const maxPv = heroData.max_pv || heroData.pv;
        const hpPercent = Math.max(0, Math.min(100, (heroData.pv / maxPv) * 100));
        
        const hpBar = card.querySelector('.hero-card-hp-bar .fill');
        if (hpBar) {
            hpBar.style.width = hpPercent + '%';
            // Couleur selon le % de HP
            if (hpPercent <= 25) {
                hpBar.style.background = '#d32f2f';
            } else if (hpPercent <= 50) {
                hpBar.style.background = '#ff9800';
            } else {
                hpBar.style.background = 'var(--gold-accent)';
            }
        }
        
        // Mettre à jour le texte HP
        const hpText = card.querySelectorAll('.hero-card-hp');
        if (hpText.length >= 2) {
            hpText[1].innerHTML = `${Math.round(heroData.pv)} / ${maxPv} PV`;
        }
        
        // Mettre à jour le %
        const hpPercentEl = card.querySelector('.hero-card-hp span');
        if (hpPercentEl) {
            hpPercentEl.textContent = Math.round(hpPercent) + '%';
        }
    });
}

/**
 * Afficher le menu de switch (sélection du héros à switcher)
 */
function showSwitchMenu(isForcedSwitch = false) {
    if (!IS_5V5) return;
    
    const modal = document.getElementById('switchModal');
    const heroesGrid = document.getElementById('switchHeroesGrid');
    const modalTitle = document.getElementById('switchModalTitle');
    
    if (!modal || !heroesGrid) return;
    
    // Configurer le titre selon le type de switch
    if (isForcedSwitch) {
        modal.classList.add('forced-switch');
        modalTitle.innerHTML = '💀 HÉROS MORT - Choisissez un remplaçant!';
        modal.querySelector('.switch-modal-cancel').style.display = 'none';
    } else {
        modal.classList.remove('forced-switch');
        modalTitle.innerHTML = '🔄 Changer de Héros';
        modal.querySelector('.switch-modal-cancel').style.display = 'block';
    }
    
    // Obtenir les données de mon équipe
    const myTeamData = IS_P1 ? TEAM_DATA_P1 : TEAM_DATA_P2;
    
    // Générer les cartes de héros
    heroesGrid.innerHTML = '';
    
    myTeamData.forEach((heroData, index) => {
        const card = document.createElement('div');
        card.className = 'switch-hero-card';
        
        // Déterminer le statut du héros
        const isDead = heroData.isDead || heroData.pv <= 0;
        const isActive = index === (currentGameState?.me?.activeIndex ?? 0);
        
        if (isDead) {
            card.classList.add('unavailable');
        } else if (isActive) {
            card.classList.add('active');
        }
        
        // Calculer le % de HP
        const hpPercent = Math.max(0, Math.round((heroData.pv / (heroData.max_pv || heroData.pv)) * 100));
        let hpClass = '';
        if (isDead) hpClass = 'dead';
        else if (hpPercent < 30) hpClass = 'low';
        
        card.innerHTML = `
            <img src="${heroData.images?.p1 ? ASSET_BASE_PATH + heroData.images.p1 : ASSET_BASE_PATH + 'media/heroes/default.png'}" alt="${heroData.name}">
            <div class="switch-hero-name">${escapeHtml(heroData.name)}</div>
            <div class="switch-hero-hp ${hpClass}">${isDead ? '💀 MORT' : hpPercent + '% PV'}</div>
        `;
        
        // Event click pour switcher
        if (!isDead && !isActive) {
            card.addEventListener('click', () => {
                closeSwitchModal(true);
                performSwitch(index, isForcedSwitch);
            });
        }
        
        heroesGrid.appendChild(card);
    });
    
    // Afficher le modal et marquer comme ouvert manuellement (sauf forced switch)
    modal.style.display = 'flex';
    if (!isForcedSwitch) {
        isSwitchModalManuallyOpen = true;
    }
}

/**
 * Fermer le modal de switch (force = true pour ignorer le flag manuel)
 */
function closeSwitchModal(force = false) {
    // Si le modal est ouvert manuellement et qu'on ne force pas, ne pas fermer
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

/**
 * Effectuer un switch vers un autre héros
 */
function performSwitch(heroIndex, isForcedSwitch = false) {
    if (!IS_5V5) return;
    
    // Envoyer l'action de switch au serveur
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

    // Mode test UI - switch local sans API
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
    
    // Pour un switch obligatoire, utiliser l'endpoint submit_forced_switch
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

/**
 * Mettre à jour la sidebar après un switch détecté dans les logs
 * Extrait le nom du héros switché et marque-le comme actif
 */
function updateSwitchedHeroCard(logLine) {
    // Format: "🔄 Nom du Héros entre en combat!"
    // On récupère le texte entre 🔄 et "entre en combat!"
    const match = logLine.match(/🔄\s+(.+?)\s+entre en combat/);
    if (!match) return;
    
    const switchedHeroName = match[1];
    
    // Chercher le héros dans la sidebar et le marquer comme actif
    // D'abord, mettre à jour la sidebar gauche (mon équipe)
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
        imgEl.src = ASSET_BASE_PATH + heroData.images.p1;
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

// Initialiser les sidebars si 5v5
if (IS_5V5) {
    initializeTeamSidebars();
}
</script>

