<?php
/**
 * SINGLE PLAYER MODE - Combat contre l'IA
 */

// Autoloader centralisé
require_once __DIR__ . '/../../includes/autoload.php';

// Chemin de base pour les assets (accès direct depuis pages/game/)
$basePath = '../../';

// --- RESET ---
if (isset($_POST['logout']) || isset($_POST['new_game'])) {
    // Note: Pas d'enregistrement de stats en mode solo (vs bot)
    // Seuls les combats PvP sont enregistrés dans l'API
    
    // Préserver les données de connexion
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;
    
    // Nettoyer les données de combat
    unset($_SESSION['combat']);
    unset($_SESSION['hero_img']);
    unset($_SESSION['enemy_img']);
    unset($_SESSION['hero_id']);
    unset($_SESSION['enemy_id']);
    unset($_SESSION['combat_recorded']);
    
    // Restaurer les données de connexion si elles existaient
    if ($userId !== null) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
    }
    
    // Rediriger vers index.php (chemin selon contexte)
    $redirectPath = (strpos($_SERVER['SCRIPT_NAME'], 'index.php') !== false) ? 'index.php' : '../../index.php';
    header("Location: " . $redirectPath);
    exit;
}

// --- INITIALISATION DU COMBAT ---
if (isset($_POST['hero_choice']) && !isset($_SESSION['combat'])) {
    require_once COMPONENTS_PATH . '/selection-utils.php';
    $personnages = getHeroesList();
    
    // Trouver le héros choisi
    $heroStats = null;
    foreach ($personnages as $p) {
        if ($p['id'] === $_POST['hero_choice']) {
            $heroStats = $p;
            break;
        }
    }

    if ($heroStats) {
        // Ennemi aléatoire différent du joueur
        $potentialEnemies = array_filter($personnages, fn($p) => $p['id'] !== $heroStats['id']);
        $enemyStats = $potentialEnemies[array_rand($potentialEnemies)];

        // Instanciation
        $heroClass = $heroStats['type'];
        $enemyClass = $enemyStats['type'];

        // Utiliser le display_name si fourni (depuis le multijoueur)
        $heroDisplayName = isset($_POST['display_name']) && !empty(trim($_POST['display_name'])) 
            ? trim($_POST['display_name']) 
            : $heroStats['name'];

        $hero = new $heroClass($heroStats['pv'], $heroStats['atk'], $heroDisplayName, $heroStats['def'] ?? 5, $heroStats['speed'] ?? 10);
        $enemy = new $enemyClass($enemyStats['pv'], $enemyStats['atk'], $enemyStats['name'], $enemyStats['def'] ?? 5, $enemyStats['speed'] ?? 10);
        
        // --- APPLIQUER LA BÉNÉDICTION ---
        $blessingId = $_POST['blessing_choice'] ?? null;
        if ($blessingId) {
            $blessingClass = $blessingId;
            // On fait confiance à l'input car via liste prédéfinie, mais check file exists
            if (file_exists(CORE_PATH . '/blessings/' . $blessingClass . '.php')) {
                require_once CORE_PATH . '/blessings/' . $blessingClass . '.php';
                if (class_exists($blessingClass)) {
                    $hero->addBlessing(new $blessingClass());
                }
            }
        }
        
        $_SESSION['combat'] = new Combat($hero, $enemy);
        $_SESSION['hero_img'] = $heroStats['images']['p1'];
        $_SESSION['enemy_img'] = $enemyStats['images']['p1'];
        $_SESSION['hero_id'] = $heroStats['id'];
        $_SESSION['enemy_id'] = $enemyStats['id'];
        $_SESSION['combat_recorded'] = false;
    }
}

// --- ACTION EN COMBAT ---
if (isset($_POST['action']) && isset($_SESSION['combat'])) {
    $combat = $_SESSION['combat'];
    if (!$combat->isOver()) {
        $combat->executePlayerAction($_POST['action']);
    }
}
?>
<link rel="stylesheet" href="<?php echo $basePath; ?>public/css/style.css">
<link rel="stylesheet" href="<?php echo $basePath; ?>public/css/shared-selection.css">
<link rel="stylesheet" href="<?php echo $basePath; ?>public/css/single_player.css">
<link rel="stylesheet" href="<?php echo $basePath; ?>public/css/combat.css">

<div class="game-container">

<?php 
// ============================================================
// ÉCRAN DE COMBAT
// ============================================================
if (isset($_SESSION['combat'])): 
    $combat = $_SESSION['combat'];
    $hero = $combat->getPlayer();
    $enemy = $combat->getEnemy();
?>

<div class="arena">
    <div class="turn-indicator">
        Tour <?php echo $combat->getTurn(); ?>
    </div>
    
    <!-- STATS -->
    <div class="stats-row">
        <div class="stats hero-stats">
            <strong><?php echo htmlspecialchars($hero->getName()); ?></strong>
            <span class="type-badge"><?php echo $hero->getType(); ?></span>
            <div class="stat-bar">
                <div class="pv-bar" id="heroPvBar" style="width: 100%"></div>
            </div>
            <span class="stat-numbers" id="heroStats">-- PV | -- ATK | -- DEF | -- SPE</span>
        </div>
        
        <div class="stats enemy-stats">
            <strong><?php echo htmlspecialchars($enemy->getName()); ?></strong>
            <span class="type-badge"><?php echo $enemy->getType(); ?></span>
            <div class="stat-bar">
                <div class="pv-bar enemy-pv" id="enemyPvBar" style="width: 100%"></div>
            </div>
            <span class="stat-numbers" id="enemyStats">-- PV | -- ATK | -- DEF | -- SPE</span>
        </div>
    </div>
    
    <!-- ZONE DE COMBAT -->
    <div class="fighters-area">
        <div class="fighter hero" id="heroFighter">
            <img src="<?php echo asset_url($_SESSION['hero_img']); ?>" alt="Hero">
            <!-- Emojis and effects injected via JS -->
            <div id="heroEmojiContainer"></div>
            <div class="effects-container hero-effects">
                <?php foreach ($hero->getAllEffectsForUI() as $name => $effect): ?>
                    <div class="effect-indicator<?php echo $effect['isPending'] ? ' pending' : ''; ?>">
                        <span class="effect-emoji"><?php echo $effect['emoji']; ?></span>
                        <?php if ($effect['duration'] > 0): ?>
                            <span class="effect-duration-badge"><?php echo $effect['duration']; ?></span>
                        <?php endif; ?>
                        <div class="effect-tooltip">
                            <div class="effect-tooltip-title"><?php echo $effect['emoji'] . ' ' . htmlspecialchars($name); ?></div>
                            <div class="effect-tooltip-desc"><?php echo htmlspecialchars($effect['description']); ?></div>
                            <div class="effect-tooltip-duration"><?php echo $effect['isPending'] ? '⏳ Actif dans' : '⌛ Durée restante'; ?>: <?php echo $effect['duration']; ?> tour(s)</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="vs-indicator">VS</div>

        <div class="fighter enemy" id="enemyFighter">
            <img src="<?php echo asset_url($_SESSION['enemy_img']); ?>" alt="Enemy" class="enemy-img">
            <!-- Emojis and effects injected via JS -->
            <div id="enemyEmojiContainer"></div>
            <div class="effects-container enemy-effects">
                <?php foreach ($enemy->getAllEffectsForUI() as $name => $effect): ?>
                    <div class="effect-indicator<?php echo $effect['isPending'] ? ' pending' : ''; ?>">
                        <span class="effect-emoji"><?php echo $effect['emoji']; ?></span>
                        <?php if ($effect['duration'] > 0): ?>
                            <span class="effect-duration-badge"><?php echo $effect['duration']; ?></span>
                        <?php endif; ?>
                        <div class="effect-tooltip">
                            <div class="effect-tooltip-title"><?php echo $effect['emoji'] . ' ' . htmlspecialchars($name); ?></div>
                            <div class="effect-tooltip-desc"><?php echo htmlspecialchars($effect['description']); ?></div>
                            <div class="effect-tooltip-duration"><?php echo $effect['isPending'] ? '⏳ Actif dans' : '⌛ Durée restante'; ?>: <?php echo $effect['duration']; ?> tour(s)</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- LOGS -->
    <div class="battle-log" id="logBox">
        <?php foreach ($combat->getLogs() as $log): 
            $class = 'log-line';
            if (strpos($log, $hero->getName()) !== false && strpos($log, ':') !== false) $class .= ' player-action';
            if (strpos($log, $enemy->getName()) !== false && strpos($log, ':') !== false) $class .= ' enemy-action';
            if (strpos($log, 'VICTOIRE') !== false || strpos($log, 'remporte') !== false) $class .= ' victory';
            if (strpos($log, 'vaincu') !== false) $class .= ' defeat';
            if (strpos($log, '---') !== false) $class .= ' turn-separator';
        ?>
            <div class="<?php echo $class; ?>"><?php echo $log; ?></div>
        <?php endforeach; ?>
    </div>

    <!-- CONTRÔLES -->
    <div class="controls">
            <form method="POST" class="action-form" id="actionForm">
                <input type="hidden" name="mode" value="single">
                
                <!-- LISTE D'ACTIONS DÉFILANTE -->
                <div class="action-list">
                    <?php 
                    foreach ($hero->getAllActions() as $key => $action):
                        $ppText = '';
                        if (method_exists($hero, 'getPPText')) {
                             $ppText = $hero->getPPText($key);
                        }
                        $canUse = $hero->canUseAction($key);
                        $hasPP = isset($action['pp']);
                        $isGameOver = $combat->isOver();
                    ?>
                        <button type="submit" 
                                name="action" 
                                value="<?php echo $key; ?>" 
                                class="action-btn <?php echo $key; ?> <?php echo (!$canUse || $isGameOver) ? 'disabled' : ''; ?>"
                                title="<?php echo htmlspecialchars($action['description']); ?>"
                                <?php echo (!$canUse || $isGameOver) ? 'disabled' : ''; ?>>
                            <span class="action-emoji-icon"><?php echo $action['emoji'] ?? '⚔️'; ?></span>
                            <span class="action-label"><?php echo $action['label']; ?></span>
                            <?php if ($hasPP): ?>
                                <span class="action-pp"><?php echo $ppText; ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" name="logout" class="action-btn abandon" <?php echo $combat->isOver() ? 'disabled' : ''; ?>>Abandonner</button>
            </form>
        
        <?php if ($combat->isOver()): 
            // Note: Les stats ne sont pas enregistrées en mode solo (vs bot)
            // Seuls les combats PvP sont enregistrés
        ?>
            <div class="game-over game-over-hidden" id="gameOverSection">
                <?php if ($combat->getWinner() === $hero): ?>
                    <h3 class="victory-text">VICTOIRE !</h3>
                    <br>
                <?php else: ?>
                    <h3 class="defeat-text">DÉFAITE...</h3>
                    <br>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="mode" value="single">
                    <button type="submit" name="new_game" class="action-btn new-game">NOUVEAU COMBAT</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Define asset base path for JavaScript
const ASSET_BASE_PATH = '<?php echo $basePath; ?>public/';
</script>
<script src="<?php echo $basePath; ?>public/js/combat-animations.js"></script>
<script>

    // Récupération des données depuis PHP
    const turnActions = <?php echo json_encode($combat->getTurnActions()); ?>;
    const initialStates = <?php echo json_encode($combat->getInitialStates()); ?>;
    const heroName = <?php echo json_encode(htmlspecialchars($hero->getName())); ?>;
    const enemyName = <?php echo json_encode(htmlspecialchars($enemy->getName())); ?>;
    
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
            heroStats.textContent = `${states.player.pv}/${states.player.basePv} PV | ${states.player.atk} ATK | ${states.player.def} DEF | ${states.player.speed} SPE`;
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
            enemyStats.textContent = `${states.enemy.pv}/${states.enemy.basePv} PV | ${states.enemy.atk} ATK | ${states.enemy.def} DEF | ${states.enemy.speed} SPE`;
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

    // Wrapper pour intégrer avec le reste du code single_player
    async function playTurnAnimationsWrapper() {
        if (!turnActions || turnActions.length === 0) {
            showGameOver();
            return;
        }
        await playTurnAnimations(turnActions);
        showGameOver();
    }

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

    // Appliquer les états initiaux IMMÉDIATEMENT sans transition
    if (initialStates && Object.keys(initialStates).length > 0) {
        updateStats(initialStates, true);
    }

    // Fonction pour scroller les logs en bas
    function scrollLogsToBottom() {
        const logBox = document.getElementById('logBox');
        if (logBox) {
            logBox.scrollTop = logBox.scrollHeight;
        }
    }

    // Lancer les animations au chargement puis scroller les logs
    window.addEventListener('load', async () => {
        await playTurnAnimationsWrapper();
        scrollLogsToBottom();
    });
    
    // Scroller immédiatement aussi (avant les animations)
    scrollLogsToBottom();
</script>

<?php 
// ============================================================
// ÉCRAN DE SÉLECTION DU HÉROS
// ============================================================
else:
    // Inclure le composant de sélection
    include COMPONENTS_PATH . '/selection-screen.php';
    renderSelectionScreen(['mode' => 'single', 'showPlayerNameInput' => false]);
endif; 
require_once INCLUDES_PATH . '/footer.php';
?>
</div>

<!-- Tooltip System -->
<div id="customTooltip" class="custom-tooltip"></div>
<script src="<?php echo $basePath; ?>public/js/selection-tooltip.js"></script>