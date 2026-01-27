<?php
/**
 * SINGLE PLAYER MODE - Combat contre l'IA
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

        // Utiliser le display_name si fourni (depuis le multijoueur)
        $heroDisplayName = isset($_POST['display_name']) && !empty(trim($_POST['display_name'])) 
            ? trim($_POST['display_name']) 
            : $heroStats['name'];

        $hero = new $heroClass($heroStats['pv'], $heroStats['atk'], $heroDisplayName, $heroStats['def'] ?? 5, $heroStats['speed'] ?? 10);
        $enemy = new $enemyClass($enemyStats['pv'], $enemyStats['atk'], $enemyStats['name'], $enemyStats['def'] ?? 5, $enemyStats['speed'] ?? 10);

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
            <img src="<?php echo $_SESSION['hero_img']; ?>" alt="Hero">
            <!-- Emojis and effects injected via JS -->
            <div id="heroEmojiContainer"></div>
            <div class="effects-container hero-effects">
                <?php foreach ($hero->getActiveEffects() as $name => $effect): ?>
                    <div class="effect-indicator" title="<?php echo $name; ?>"><?php echo $effect['emoji']; ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="vs-indicator">VS</div>

        <div class="fighter enemy" id="enemyFighter">
            <img src="<?php echo $_SESSION['enemy_img']; ?>" alt="Enemy" class="enemy-img">
            <!-- Emojis and effects injected via JS -->
            <div id="enemyEmojiContainer"></div>
            <div class="effects-container enemy-effects">
                <?php foreach ($enemy->getActiveEffects() as $name => $effect): ?>
                    <div class="effect-indicator" title="<?php echo $name; ?>"><?php echo $effect['emoji']; ?></div>
                <?php endforeach; ?>
                <?php foreach ($enemy->getPendingEffects() as $name => $effect): ?>
                    <div class="effect-indicator pending" title="<?php echo $name; ?> (<?php echo $effect['turnsDelay']; ?> tour)"><?php echo $effect['emoji']; ?></div>
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
                    <?php foreach ($hero->getAvailableActions() as $key => $action): 
                        $ppText = $hero->getPPText($key);
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
            <div class="game-over" id="gameOverSection" style="display: none;">
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
    document.getElementById("logBox").scrollTop = document.getElementById("logBox").scrollHeight;

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
    
    function triggerHitAnimation(fighterElement) {
        if (!fighterElement) return;
        fighterElement.classList.add('fighter-hit');
        setTimeout(() => fighterElement.classList.remove('fighter-hit'), 400);
    }
    
    async function playTurnAnimations() {
        if (!turnActions || turnActions.length === 0) {
            // Pas d'animations, afficher directement le game-over si présent
            showGameOver();
            return;
        }

        for (const action of turnActions) {
            await playAction(action);
        }
        
        // Afficher le game-over après toutes les animations
        showGameOver();
    }
    
    function showGameOver() {
        const gameOver = document.getElementById('gameOverSection');
        const actionForm = document.getElementById('actionForm');
        if (gameOver) {
            // Cacher le formulaire d'actions
            if (actionForm) {
                actionForm.style.display = 'none';
            }
            // Afficher le game-over
            gameOver.style.display = 'block';
        }
    }

    function playAction(action) {
        return new Promise(resolve => {
            const isPlayer = action.actor === 'player';
            const emoji = action.emoji;
            const actionName = action.label || 'Effet';
            const phase = action.phase || 'action';

            // Conteneurs
            const actorContainer = isPlayer 
                ? document.getElementById('heroEmojiContainer') 
                : document.getElementById('enemyEmojiContainer');
            const targetContainer = isPlayer 
                ? document.getElementById('enemyEmojiContainer') 
                : document.getElementById('heroEmojiContainer');
            const actorFighter = isPlayer 
                ? document.getElementById('heroFighter') 
                : document.getElementById('enemyFighter');

            // --- ANIMATION DE MORT ---
            if (action.isDeath || phase === 'death') {
                const deathElement = document.createElement('div');
                deathElement.className = 'action-name-display death-label';
                deathElement.textContent = '💀 K.O.';
                if (actorContainer) actorContainer.appendChild(deathElement);

                // Faire disparaître l'image avec fondu
                if (actorFighter) {
                    actorFighter.classList.add('fighter-dead');
                }
                
                // Mettre à jour les stats APRÈS l'animation
                if (action.statesAfter) {
                    updateStats(action.statesAfter);
                }

                setTimeout(() => {
                    if (actorContainer && actorContainer.contains(deathElement)) {
                        actorContainer.removeChild(deathElement);
                    }
                    resolve();
                }, 2000);
                return;
            }

            // --- ANIMATION STANDARD ---
            
            // 1. Afficher le nom au-dessus de l'acteur
            const nameElement = document.createElement('div');
            nameElement.className = 'action-name-display';
            
            // Style différent selon la phase
            if (phase === 'damage_effect') {
                nameElement.classList.add('effect-damage');
            } else if (phase === 'stat_effect') {
                nameElement.classList.add('effect-stats');
            }
            
            nameElement.textContent = actionName;
            if (actorContainer) actorContainer.appendChild(nameElement);

            // 2. Déterminer où afficher l'emoji
            let emojiContainer = null;
            let cssClass = 'action-emoji';

            if (phase === 'damage_effect' || phase === 'stat_effect') {
                emojiContainer = actorContainer;
                cssClass += ' on-self';
            } else {
                if (action.needsTarget) {
                    emojiContainer = targetContainer;
                    cssClass += ' on-target';
                } else {
                    emojiContainer = actorContainer;
                    cssClass += ' on-self';
                }
            }

            if (!isPlayer) {
                cssClass += ' enemy-action';
            }

            // 3. Créer l'emoji
            const emojiElement = document.createElement('div');
            emojiElement.className = cssClass;
            emojiElement.textContent = emoji;
            
            if (emojiContainer) emojiContainer.appendChild(emojiElement);

            // Trigger hit animation on target if action targets them
            if (action.needsTarget !== false && phase === 'action') {
                 const targetFighter = isPlayer 
                    ? document.getElementById('enemyFighter') 
                    : document.getElementById('heroFighter');
                triggerHitAnimation(targetFighter);
            }

            // 4. Mettre à jour les stats APRÈS l'animation (à mi-chemin)
            setTimeout(() => {
                if (action.statesAfter) {
                    updateStats(action.statesAfter);
                }
            }, 750);

            // 5. Nettoyer après animation
            setTimeout(() => {
                if (actorContainer && actorContainer.contains(nameElement)) {
                    actorContainer.removeChild(nameElement);
                }
                if (emojiContainer && emojiContainer.contains(emojiElement)) {
                    emojiContainer.removeChild(emojiElement);
                }
                resolve();
            }, 1500);
        });
    }

    // Appliquer les états initiaux IMMÉDIATEMENT sans transition
    if (initialStates && Object.keys(initialStates).length > 0) {
        updateStats(initialStates, true);
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
                                <?php echo $perso['pv']; ?> PV | <?php echo $perso['atk']; ?> ATK | <?php echo $perso['def'] ?? 5; ?> DEF | <?php echo $perso['speed'] ?? 10; ?> SPE
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