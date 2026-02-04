<?php
/**
 * MULTIPLAYER COMBAT PAGE
 * Interface complÃ¨te de combat multiplayer avec polling en temps rÃ©el
 * BasÃ©e sur la structure de single_player.php
 */

// Autoloader centralisÃ© (dÃ©marre la session automatiquement)
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
                            // Mode 5v5 : enregistrer tous les hÃ©ros de chaque Ã©quipe
                            $p1Heroes = array_column($metaData['player1']['heroes'] ?? [], 'id');
                            $p2Heroes = array_column($metaData['player2']['heroes'] ?? [], 'id');
                            $p1TeamName = $metaData['player1']['team_name'] ?? 'Équipe 1';
                            $p2TeamName = $metaData['player2']['team_name'] ?? 'Équipe 2';
                            $p1DisplayName = $metaData['player1']['display_name'] ?? 'Joueur 1';
                            $p2DisplayName = $metaData['player2']['display_name'] ?? 'Joueur 2';
                            
                            // Enregistrer pour P1 (tous les hÃ©ros de son Ã©quipe)
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
                            
                            // Enregistrer pour P2 (tous les hÃ©ros de son Ã©quipe)
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
    
    // PrÃ©server les donnÃ©es de connexion
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;
    
    // Nettoyer les donnÃ©es de combat/match
    unset($_SESSION['combat']);
    unset($_SESSION['matchId']);
    unset($_SESSION['queueHeroId']);
    unset($_SESSION['queueStartTime']);
    unset($_SESSION['queueHeroData']);
    unset($_SESSION['queueDisplayName']);
    
    // Restaurer les donnÃ©es de connexion
    if ($userId !== null) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
    }
    
    header("Location: ../../index.php");
    exit;
}

// RÃ©cupÃ©rer le matchId depuis l'URL
$matchId = $_GET['match_id'] ?? $_SESSION['matchId'] ?? null;

if (!$matchId) {
    header("Location: ../../index.php");
    exit;
}

$_SESSION['matchId'] = $matchId;

// Charger l'Ã©tat initial du match directement depuis le fichier
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
    die("Erreur: Impossible de lire les donnÃ©es du match.");
}

// Charger l'Ã©tat du combat via MultiCombat (ou TeamCombat pour 5v5)
// L'autoloader se charge des classes MultiCombat et TeamCombat

$stateFile = DATA_PATH . '/matches/' . $matchId . '.state';
$multiCombat = null;

// Essayer de charger l'Ã©tat existant
if (file_exists($stateFile)) {
    $multiCombat = MultiCombat::load($stateFile);
}

// Si pas d'Ã©tat, crÃ©er un Ã©tat initial (pour les tests UI ou nouveau combat)
if (!$multiCombat && isset($matchData['player1']['hero'])) {
    // Initialiser le combat si pas encore crÃ©Ã©
    try {
        $multiCombat = MultiCombat::create($matchData['player1'], $matchData['player2']);
        if ($multiCombat && !$multiCombat->save($stateFile)) {
            error_log("Erreur: Impossible de sauvegarder l'Ã©tat initial du combat.");
        }
    } catch (Exception $e) {
        error_log("Erreur lors de l'initialisation du combat: " . $e->getMessage());
        // Continuer sans Ã©tat - pour les tests UI
    }
}

// DÃ©terminer le rÃ´le du joueur actuel
$sessionId = session_id();
$isP1 = ($matchData['player1']['session'] === $sessionId);
$myRole = $isP1 ? 'p1' : 'p2';

// DÃ‰TECTION MODE 5v5
$is5v5 = $matchData['mode'] === '5v5' || ($multiCombat instanceof TeamCombat);
$teamSidebars = ['p1' => [], 'p2' => []];

// isTestUI = vrai seulement si pas de fichier de combat (mode visualisation UI sans vrai combat)
$isTestUI = !$multiCombat && isset($matchData['player1']['heroes']) && isset($matchData['player2']['heroes']);

// Noms d'Ã©quipes pour l'UI
$myTeamName = $isP1 ? ($matchData['player1']['display_name'] ?? 'Mon Équipe') : ($matchData['player2']['display_name'] ?? 'Mon Équipe');
$oppTeamName = $isP1 ? ($matchData['player2']['display_name'] ?? 'Adversaire') : ($matchData['player1']['display_name'] ?? 'Adversaire');

if ($is5v5) {
    $teamSidebars['p1'] = $matchData['player1']['heroes'] ?? [];
    $teamSidebars['p2'] = $matchData['player2']['heroes'] ?? [];
}

// Construire l'Ã©tat du jeu pour l'affichage
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
        // Mode test UI - crÃ©er un Ã©tat par dÃ©faut
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
                'name' => $myHero['name'] ?? 'HÃ©ros 1',
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
    
    // DÃ©terminer qui doit jouer
    $actions = $matchData['current_turn_actions'] ?? [];
    $oppRole = $isP1 ? 'p2' : 'p1';
    if (!isset($gameState['waiting_for_me'])) {
        $gameState['waiting_for_me'] = !isset($actions[$myRole]);
    }
    if (!isset($gameState['waiting_for_opponent'])) {
        $gameState['waiting_for_opponent'] = isset($actions[$myRole]) && !isset($actions[$oppRole]) && ($matchData['mode'] ?? '') !== 'bot';
    }
    
} catch (Exception $e) {
    die("Erreur lors de la construction de l'Ã©tat du jeu: " . $e->getMessage());
}

// Configuration du header
$pageTitle = 'Combat Multijoueur - Horus Battle Arena';
$extraCss = ['shared-selection', 'combat', 'multiplayer-combat'];
$showUserBadge = true;
$showMainTitle = false;
require_once INCLUDES_PATH . '/header.php';
?>

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
                    <?php $myPvPct = ($gameState['me']['max_pv'] > 0) ? ($gameState['me']['pv'] / $gameState['me']['max_pv']) * 100 : 0; ?>
                    <div class="pv-bar" id="myPvBar" style="width: <?php echo $myPvPct; ?>%;"></div>
                </div>
                <span class="stat-numbers" id="myStats"><?php echo round($gameState['me']['pv']); ?> / <?php echo $gameState['me']['max_pv']; ?> | ATK: <?php echo $gameState['me']['atk']; ?> | DEF: <?php echo $gameState['me']['def']; ?></span>
            </div>
            
            <div class="stats enemy-stats">
                <strong id="oppName"><?php echo htmlspecialchars($gameState['opponent']['name']); ?></strong>
                <span class="type-badge" id="oppType"><?php echo $gameState['opponent']['type']; ?></span>
                <div class="stat-bar">
                    <?php $oppPvPct = ($gameState['opponent']['max_pv'] > 0) ? ($gameState['opponent']['pv'] / $gameState['opponent']['max_pv']) * 100 : 0; ?>
                    <div class="pv-bar enemy-pv" id="oppPvBar" style="width: <?php echo $oppPvPct; ?>%;"></div>
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

<script src="../../public/js/combat-animations.js"></script>
<script src="../../public/js/selection-tooltip.js"></script>
<script src="../../public/js/multiplayer-combat.js"></script>
<script>
// Initialiser le combat avec les données PHP
initMultiplayerCombat({
    matchId: '<?php echo addslashes($matchId); ?>',
    initialState: <?php echo json_encode($gameState); ?>,
    isP1: <?php echo $isP1 ? 'true' : 'false'; ?>,
    is5v5: <?php echo $is5v5 ? 'true' : 'false'; ?>,
    isTestUI: <?php echo $isTestUI ? 'true' : 'false'; ?>,
    teamDataP1: <?php echo json_encode($teamSidebars['p1']); ?>,
    teamDataP2: <?php echo json_encode($teamSidebars['p2']); ?>,
    assetBasePath: '<?php echo asset_url(""); ?>'
});
</script>

<?php 
$showBackLink = true;
require_once INCLUDES_PATH . '/footer.php'; 
?>
