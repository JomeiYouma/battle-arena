<?php
/**
 * =============================================================================
 * SINGLE PLAYER MODE - Combat contre l'IA
 * =============================================================================
 * 
 * TODO [À RECODER PAR TOI-MÊME] :
 * - Ce fichier gère l'interface de combat
 * - Tu peux personnaliser les animations, les effets visuels
 * - Ajouter un système de récompenses après victoire
 * 
 * =============================================================================
 */

// --- LOGIQUE DE RESET (DOIT ÊTRE EN PREMIER !) ---
if (isset($_POST['logout']) || isset($_POST['new_game'])) {
    // Clear toutes les variables de session puis détruit la session
    session_unset();
    session_destroy();
    // Redirige vers index.php (pas single_player.php qui n'est pas exécutable seul)
    header("Location: index.php");
    exit;
}

// --- LOGIQUE D'INITIALISATION DU COMBAT ---
if (isset($_POST['hero_choice']) && !isset($_SESSION['combat'])) {
    
    // 1. On charge la liste des héros
    $json_data = file_get_contents('heros.json');
    $personnages = json_decode($json_data, true);
    
    // 2. On récupère les stats du héros choisi
    $heroStats = null;
    foreach ($personnages as $p) {
        if ($p['id'] === $_POST['hero_choice']) {
            $heroStats = $p;
            break;
        }
    }

    // 3. On choisit l'ennemi au hasard (différent du joueur)
    if ($heroStats) {
        $potentialEnemies = array_filter($personnages, function($p) use ($heroStats) {
            return $p['id'] !== $heroStats['id'];
        });
        $randomKey = array_rand($potentialEnemies);
        $enemyStats = $potentialEnemies[$randomKey];

        // 4. INSTANCIATION selon le TYPE du personnage
        // TODO [À RECODER] : Ajoute ici tes nouvelles classes quand tu en crées
        $heroClass = $heroStats['type'];
        $enemyClass = $enemyStats['type'];

        // Création du héros selon son type
        $hero = new $heroClass(
            $heroStats['pv'], 
            $heroStats['atk'], 
            $heroStats['name'],
            $heroStats['def'] ?? 5
        );
        
        // Création de l'ennemi selon son type
        $enemy = new $enemyClass(
            $enemyStats['pv'], 
            $enemyStats['atk'], 
            $enemyStats['name'],
            $enemyStats['def'] ?? 5
        );

        // 5. Création du combat et mise en session
        $_SESSION['combat'] = new Combat($hero, $enemy);
        $_SESSION['hero_img'] = $heroStats['images']['p1'];
        $_SESSION['enemy_img'] = $enemyStats['images']['p1'];
        $_SESSION['hero_desc'] = $heroStats['description'] ?? '';
        $_SESSION['enemy_desc'] = $enemyStats['description'] ?? '';
    }
}

// --- LOGIQUE D'ACTION EN COMBAT ---
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
// Si on a un combat en session, on affiche l'ARÈNE
if (isset($_SESSION['combat'])): 
    $combat = $_SESSION['combat'];
    $hero = $combat->getPlayer();
    $enemy = $combat->getEnemy();
?>

   <div class="arena">
        <div class="turn-indicator">Tour <?php echo $combat->getTurn(); ?></div>
        
        <!-- STATS ROW - EN DEHORS DE LA ZONE DE COMBAT -->
        <div class="stats-row">
            <div class="stats hero-stats">
                <strong><?php echo $hero->getName(); ?></strong>
                <span class="type-badge"><?php echo $hero->getType(); ?></span><br>
                <div class="stat-bar">
                    <div class="pv-bar" style="width: <?php echo ($hero->getPv() / $hero->getBasePv()) * 100; ?>%"></div>
                </div>
                ❤️ <?php echo $hero->getPv(); ?>/<?php echo $hero->getBasePv(); ?> PV
                | ⚔️ <?php echo $hero->getAtk(); ?> ATK
                | 🛡️ <?php echo $hero->getDef(); ?> DEF
            </div>
            
            <div class="stats enemy-stats">
                <strong><?php echo $enemy->getName(); ?></strong>
                <span class="type-badge"><?php echo $enemy->getType(); ?></span><br>
                <div class="stat-bar">
                    <div class="pv-bar enemy-pv" style="width: <?php echo ($enemy->getPv() / $enemy->getBasePv()) * 100; ?>%"></div>
                </div>
                ❤️ <?php echo $enemy->getPv(); ?>/<?php echo $enemy->getBasePv(); ?> PV
                | ⚔️ <?php echo $enemy->getAtk(); ?> ATK
                | 🛡️ <?php echo $enemy->getDef(); ?> DEF
            </div>
        </div>
        
        <!-- ZONE DE COMBAT - IMAGES SEULEMENT -->
        <div class="fighters-area">
            
            <!-- HÉROS -->
            <div class="fighter hero">
                <img src="<?php echo $_SESSION['hero_img']; ?>" alt="Hero">
            </div>

            <div class="vs-indicator">VS</div>

            <!-- ENNEMI -->
            <div class="fighter enemy">
                <img src="<?php echo $_SESSION['enemy_img']; ?>" alt="Enemy" class="enemy-img">
            </div>
        </div>

        <!-- LOGS DE COMBAT -->
        <div class="battle-log" id="logBox">
            <?php 
            foreach ($combat->getLogs() as $log) {
                // Coloration selon le type de message
                $class = 'log-line';
                if (strpos($log, '🎮') !== false) $class .= ' player-action';
                if (strpos($log, '🤖') !== false) $class .= ' enemy-action';
                if (strpos($log, '🏆') !== false) $class .= ' victory';
                if (strpos($log, '💀') !== false) $class .= ' defeat';
                if (strpos($log, '---') !== false) $class .= ' turn-separator';
                
                echo "<div class='$class'>$log</div>";
            }
            ?>
        </div>

        <!-- CONTRÔLES -->
        <div class="controls">
            
            <?php if (!$combat->isOver()): ?>
                <form method="POST" class="action-form">
                    <input type="hidden" name="mode" value="single">
                    
                    <!-- Génération dynamique des boutons selon la classe du personnage -->
                    <!-- TODO [À RECODER] : Tu peux ajouter des animations, des cooldowns visuels, etc. -->
                    <?php foreach ($hero->getAvailableActions() as $key => $action): ?>
                        <button type="submit" 
                                name="action" 
                                value="<?php echo $key; ?>" 
                                class="action-btn <?php echo $key; ?>"
                                title="<?php echo htmlspecialchars($action['description']); ?>">
                            <?php echo $action['label']; ?>
                        </button>
                    <?php endforeach; ?>
                    
                    <button type="submit" name="logout" class="action-btn abandon">🚪 Abandonner</button>
                </form>
            
            <?php else: ?>
                <div class="game-over">
                    <?php if ($combat->getWinner() === $hero): ?>
                        <h3>🏆 VICTOIRE !</h3><br>
                       <!--  <p>Vous avez terrassé <?php echo $enemy->getName(); ?> !</p> -->
                    <?php else: ?>
                        <h3>💀 DÉFAITE...</h3>
                        <p><?php echo $enemy->getName(); ?> vous a vaincu...</p>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <button type="submit" name="new_game" class="action-btn new-game">
                            NOUVEAU COMBAT
                        </button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        // Auto-scroll du log de combat
        var logBox = document.getElementById("logBox");
        logBox.scrollTop = logBox.scrollHeight;
    </script>

<?php 
// Sinon afficher la SÉLECTION de personnage
else: 
    $json_data = file_get_contents('heros.json');
    $personnages = json_decode($json_data, true);
?>

    <div class="select-screen">
        <h2>⚔️ Choisissez votre Champion ⚔️</h2>
        
        <form method="POST">
            <input type="hidden" name="mode" value="single">
            
            <!-- Grille de sélection avec infobulles (sans spoiler les images) -->
            <div class="hero-select-grid">
                <?php 
                foreach ($personnages as $perso): 
                    // Instancier temporairement pour récupérer les actions
                    $tempClass = $perso['type'];
                    $tempHero = new $tempClass(
                        $perso['pv'], 
                        $perso['atk'], 
                        $perso['name'],
                        $perso['def'] ?? 5
                    );
                    $actions = $tempHero->getAvailableActions();
                ?>
                    <label class="hero-card">
                        <input type="radio" name="hero_choice" value="<?php echo $perso['id']; ?>" required>
                        <div class="hero-card-content">
                            <!-- Image mystère au lieu de l'image réelle -->
                            <div class="mystery-silhouette">
                                <!-- <span class="mystery-icon">❓</span> -->
                            </div>
                            <h4><?php echo $perso['name']; ?></h4>
                            <span class="type-badge"><?php echo $perso['type']; ?></span>
                            <div class="hero-stats-preview">
                                ❤️ <?php echo $perso['pv']; ?> | 
                                ⚔️ <?php echo $perso['atk']; ?> | 
                                🛡️ <?php echo $perso['def'] ?? 5; ?>
                            </div>
                            
                            <!-- Infobulle au survol avec description ET compétences -->
                            <div class="tooltip">
                                <p class="hero-description"><?php echo $perso['description'] ?? 'Aucune description'; ?></p>
                                <hr>
                                <strong>Compétences :</strong>
                                <ul>
                                <?php foreach ($actions as $key => $action): ?>
                                    <li><strong><?php echo $action['label']; ?></strong>: <?php echo $action['description']; ?></li>
                                <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <br>
            <button type="submit" class="action-btn enter-arena">⚔️ Entrer dans l'arène</button>
        </form>
    </div>

<?php endif; ?>
</div>