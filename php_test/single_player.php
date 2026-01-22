<?php
/**
 * SINGLE PLAYER MODE - Combat contre l'IA
 */

// Autoloader et session (si accès direct)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('chargerClasse')) {
    function chargerClasse($classe) {
        if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
            require __DIR__ . '/classes/' . $classe . '.php';
        }
    }
    spl_autoload_register('chargerClasse');
}

// --- RESET ---
if (isset($_POST['logout']) || isset($_POST['new_game'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- INITIALISATION DU COMBAT ---
if (isset($_POST['hero_choice']) && !isset($_SESSION['combat'])) {
    $personnages = json_decode(file_get_contents('heros.json'), true);
    
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

        $hero = new $heroClass($heroStats['pv'], $heroStats['atk'], $heroStats['name'], $heroStats['def'] ?? 5, $heroStats['speed'] ?? 10);
        $enemy = new $enemyClass($enemyStats['pv'], $enemyStats['atk'], $enemyStats['name'], $enemyStats['def'] ?? 5, $enemyStats['speed'] ?? 10);

        $_SESSION['combat'] = new Combat($hero, $enemy);
        $_SESSION['hero_img'] = $heroStats['images']['p1'];
        $_SESSION['enemy_img'] = $enemyStats['images']['p1'];
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
<link rel="stylesheet" href="./style.css">

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
        <?php if ($combat->playerIsFaster()): ?>
            <span class="speed-indicator player-faster">⚡ <?php echo $hero->getName(); ?> plus rapide</span>
        <?php else: ?>
            <span class="speed-indicator enemy-faster">⚡ <?php echo $enemy->getName(); ?> plus rapide</span>
        <?php endif; ?>
    </div>
    
    <!-- STATS -->
    <div class="stats-row">
        <div class="stats hero-stats">
            <strong><?php echo $hero->getName(); ?></strong>
            <span class="type-badge"><?php echo $hero->getType(); ?></span>
            <div class="stat-bar">
                <div class="pv-bar" style="width: <?php echo ($hero->getPv() / $hero->getBasePv()) * 100; ?>%"></div>
            </div>
            <span class="stat-numbers"><?php echo $hero->getPv(); ?>/<?php echo $hero->getBasePv(); ?> PV | <?php echo $hero->getAtk(); ?> ATK | <?php echo $hero->getDef(); ?> DEF | ⚡<?php echo $hero->getSpeed(); ?></span>
        </div>
        
        <div class="stats enemy-stats">
            <strong><?php echo $enemy->getName(); ?></strong>
            <span class="type-badge"><?php echo $enemy->getType(); ?></span>
            <div class="stat-bar">
                <div class="pv-bar enemy-pv" style="width: <?php echo ($enemy->getPv() / $enemy->getBasePv()) * 100; ?>%"></div>
            </div>
            <span class="stat-numbers"><?php echo $enemy->getPv(); ?>/<?php echo $enemy->getBasePv(); ?> PV | <?php echo $enemy->getAtk(); ?> ATK | <?php echo $enemy->getDef(); ?> DEF | ⚡<?php echo $enemy->getSpeed(); ?></span>
        </div>
    </div>
    
    <!-- ZONE DE COMBAT -->
    <div class="fighters-area">
        <div class="fighter hero" id="heroFighter">
            <img src="<?php echo $_SESSION['hero_img']; ?>" alt="Hero">
            <!-- Emojis and effects injected via JS -->
            <div id="heroEmojiContainer"></div>
            <?php foreach ($hero->getActiveEffects() as $name => $effect): ?>
                <div class="effect-indicator" title="<?php echo $name; ?>"><?php echo $effect['emoji']; ?></div>
            <?php endforeach; ?>
        </div>

        <div class="vs-indicator">VS</div>

        <div class="fighter enemy" id="enemyFighter">
            <img src="<?php echo $_SESSION['enemy_img']; ?>" alt="Enemy" class="enemy-img">
            <!-- Emojis and effects injected via JS -->
            <div id="enemyEmojiContainer"></div>
            <?php foreach ($enemy->getActiveEffects() as $name => $effect): ?>
                <div class="effect-indicator" title="<?php echo $name; ?>"><?php echo $effect['emoji']; ?></div>
            <?php endforeach; ?>
            <?php foreach ($enemy->getPendingEffects() as $name => $effect): ?>
                <div class="effect-indicator pending" title="<?php echo $name; ?> (<?php echo $effect['turnsDelay']; ?> tour)"><?php echo $effect['emoji']; ?></div>
            <?php endforeach; ?>
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
        <?php if (!$combat->isOver()): ?>
            <form method="POST" class="action-form">
                <input type="hidden" name="mode" value="single">
                
                <!-- LISTE D'ACTIONS DÉFILANTE -->
                <div class="action-list">
                    <?php foreach ($hero->getAvailableActions() as $key => $action): 
                        $ppText = $hero->getPPText($key);
                        $canUse = $hero->canUseAction($key);
                        $hasPP = isset($action['pp']);
                    ?>
                        <button type="submit" 
                                name="action" 
                                value="<?php echo $key; ?>" 
                                class="action-btn <?php echo $key; ?> <?php echo !$canUse ? 'disabled' : ''; ?>"
                                title="<?php echo htmlspecialchars($action['description']); ?>"
                                <?php echo !$canUse ? 'disabled' : ''; ?>>
                            <span class="action-emoji-icon"><?php echo $action['emoji'] ?? '⚔️'; ?></span>
                            <span class="action-label"><?php echo $action['label']; ?></span>
                            <?php if ($hasPP): ?>
                                <span class="action-pp"><?php echo $ppText; ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" name="logout" class="action-btn abandon">Abandonner</button>
            </form>
        
        <?php else: ?>
            <div class="game-over">
                <?php if ($combat->getWinner() === $hero): ?>
                    <h3 class="victory-text">VICTOIRE !</h3>
                <?php else: ?>
                    <h3 class="defeat-text">DÉFAITE...</h3>
                    <p><?php echo $enemy->getName(); ?> vous a vaincu.</p>
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
    document.getElementById("logBox").scrollTop = document.getElementById("logBox").scrollHeight;

    // Récupération des actions du tour depuis PHP
    const turnActions = <?php echo json_encode($combat->getTurnActions()); ?>;
    
    async function playTurnAnimations() {
        // Si aucune action (changement de page ou refresh), on ne fait rien
        if (!turnActions || turnActions.length === 0) return;

        const heroEmojiContainer = document.getElementById('heroEmojiContainer');
        const enemyEmojiContainer = document.getElementById('enemyEmojiContainer');

        for (const action of turnActions) {
            await playAction(action);
        }
    }

    function playAction(action) {
        return new Promise(resolve => {
            const isPlayer = action.actor === 'player';
            const needsTarget = action.needsTarget;
            const emoji = action.emoji;

            // Déterminer où afficher l'emoji
            let container = null;
            let cssClass = 'action-emoji';

            if (isPlayer) {
                if (needsTarget) {
                    // Le joueur attaque l'ennemi -> Emoji sur l'ennemi
                    container = document.getElementById('enemyEmojiContainer');
                    cssClass += ' on-target'; 
                } else {
                    // Le joueur se buff/heal -> Emoji sur le joueur
                    container = document.getElementById('heroEmojiContainer');
                    cssClass += ' on-self';
                }
            } else { // Enemy
                if (needsTarget) {
                    // L'ennemi attaque le joueur -> Emoji sur le joueur
                    container = document.getElementById('heroEmojiContainer');
                    cssClass += ' on-target enemy-action';
                } else {
                    // L'ennemi se buff/heal -> Emoji sur l'ennemi
                    container = document.getElementById('enemyEmojiContainer');
                    cssClass += ' on-self enemy-action';
                }
            }

            // Créer l'élément Emoji
            const emojiElement = document.createElement('div');
            emojiElement.className = cssClass;
            emojiElement.textContent = emoji;
            
            // Ajouter au conteneur
            if (container) {
                container.appendChild(emojiElement);
            }

            // Attendre la fin de l'animation CSS (approx 1.5s) puis nettoyer
            setTimeout(() => {
                if (container && container.contains(emojiElement)) {
                    container.removeChild(emojiElement);
                }
                resolve(); // Passer à l'action suivante
            }, 1500); // Durée égale ou légèrement sup à l'animation CSS
        });
    }

    // Lancer les animations au chargement
    window.addEventListener('load', playTurnAnimations);
</script>

<?php 
// ============================================================
// ÉCRAN DE SÉLECTION DU HÉROS
// ============================================================
else: 
    $personnages = json_decode(file_get_contents('heros.json'), true);
?>

<div class="select-screen">
    <h2>Choisissez votre Champion</h2>
    
    <form method="POST">
        <input type="hidden" name="mode" value="single">
        <!-- LISTE DES HÉROS (avec images) -->
        <div class="hero-list">
            <?php foreach ($personnages as $perso): ?>
                <label class="hero-row">
                    <input type="radio" name="hero_choice" value="<?php echo $perso['id']; ?>" required>
                    <div class="hero-row-content">
                        <img src="<?php echo $perso['images']['p1']; ?>" alt="<?php echo $perso['name']; ?>" class="hero-thumb">
                        <div class="hero-info">
                            <h4><?php echo $perso['name']; ?></h4>
                            <span class="type-badge"><?php echo $perso['type']; ?></span>
                            <p class="hero-theme"><?php echo $perso['description']; ?></p>
                            <div class="hero-stats-mini">
                                <?php echo $perso['pv']; ?> PV | <?php echo $perso['atk']; ?> ATK | <?php echo $perso['def'] ?? 5; ?> DEF | ⚡<?php echo $perso['speed'] ?? 10; ?>
                            </div>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
        
        <button type="submit" class="action-btn enter-arena">Entrer dans l'arène</button>
    </form>
</div>

<?php endif; ?>
</div>