<?php
/** VUE: Multiplayer Combat - Interface de combat multijoueur temps réel */

// Variables disponibles depuis le contrôleur :
// $matchId, $matchData, $gameState, $isP1, $is5v5, $myTeamName, $oppTeamName

$teamSidebars = ['p1' => [], 'p2' => []];
if ($is5v5) {
    $teamSidebars['p1'] = $matchData['player1']['heroes'] ?? [];
    $teamSidebars['p2'] = $matchData['player2']['heroes'] ?? [];
}

$isTestUI = !isset($matchData['combat_state']) && isset($matchData['player1']['heroes']);
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
    <button class="drawer-toggle team-1-toggle" id="drawerToggle1" onclick="toggleTeamDrawer(1)" title="Équipe 1">►</button>
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
            <?php foreach ($gameState['logs'] ?? [] as $log): ?>
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
                <button class="action-btn new-game" onclick="location.href='<?php echo View::url('game/multiplayer'); ?>'">Rejouer</button>
                <button class="action-btn" onclick="location.href='<?php echo View::url(''); ?>'" style="margin-left: 10px;">Menu Principal</button>
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
    <button class="drawer-toggle team-2-toggle" id="drawerToggle2" onclick="toggleTeamDrawer(2)" title="Équipe 2">◄</button>
    
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

<script src="<?php echo View::asset('js/combat-animations.js'); ?>"></script>
<script src="<?php echo View::asset('js/selection-tooltip.js'); ?>"></script>
<script src="<?php echo View::asset('js/multiplayer-combat.js'); ?>"></script>
<script>
// Define API base path for JavaScript (avoid redeclaration)
if (typeof API_BASE_PATH === 'undefined') {
    var API_BASE_PATH = '<?php echo View::url('/api'); ?>';
}

// Initialiser le combat avec les données PHP
initMultiplayerCombat({
    matchId: '<?php echo addslashes($matchId); ?>',
    initialState: <?php echo json_encode($gameState); ?>,
    isP1: <?php echo $isP1 ? 'true' : 'false'; ?>,
    is5v5: <?php echo $is5v5 ? 'true' : 'false'; ?>,
    isTestUI: <?php echo $isTestUI ? 'true' : 'false'; ?>,
    teamDataP1: <?php echo json_encode($teamSidebars['p1']); ?>,
    teamDataP2: <?php echo json_encode($teamSidebars['p2']); ?>,
    assetBasePath: '<?php echo View::asset(""); ?>',
    apiBasePath: API_BASE_PATH
});
</script>
