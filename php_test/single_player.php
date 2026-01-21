<?php


// --- LOGIQUE D'INITIALISATION DU COMBAT ---
// On rentre ici seulement si le formulaire a été envoyé ET qu'il n'y a pas encore de session
if (isset($_POST['hero_choice']) && !isset($_SESSION['hero'])) {
    
    // 1. On charge la liste complète des héros
    $json_data = file_get_contents('heros.json'); // Assure-toi que le nom du fichier est bon
    $personnages = json_decode($json_data, true);
    
    // 2. On récupère les stats du héros choisi par le joueur
    $heroStats = null;
    foreach ($personnages as $p) {
        if ($p['id'] === $_POST['hero_choice']) {
            $heroStats = $p;
            break;
        }
    }

    // 3. On choisit l'ennemi au hasard
    if ($heroStats) {
        // A. On crée une liste de "candidats" en excluant le héros choisi (pour ne pas se battre contre soi-même)
        // array_filter permet de garder seulement ceux qui ne sont PAS le joueur
        $potentialEnemies = array_filter($personnages, function($p) use ($heroStats) {
            return $p['id'] !== $heroStats['id'];
        });

        // B. On tire au sort dans cette liste restante
        // array_rand renvoie une clé (index) au hasard
        $randomKey = array_rand($potentialEnemies);
        $enemyStats = $potentialEnemies[$randomKey];

        // 4. INSTANCIATION : On crée les Objets et on les met en Session
        // Note : J'ajoute l'image dans la session pour l'affichage plus tard, si ta classe ne la gère pas
        $_SESSION['hero'] = new Personnage($heroStats['pv'], $heroStats['atk'], $heroStats['name']);
        $_SESSION['hero_img'] = $heroStats['images']['p1']; // On garde l'image de côté

        $_SESSION['enemy'] = new Personnage($enemyStats['pv'], $enemyStats['atk'], $enemyStats['name']);
        $_SESSION['enemy_img'] = $enemyStats['images']['p1']; // On garde l'image de côté

        $_SESSION['logs'] = []; // Historique vide
    }
}

// --- LOGIQUE DE RESET (Pour tester) ---
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>
<link rel="stylesheet" href="./style.css">

<div class="game-container">

<?php 
// 4. AFFICHAGE CONDITIONNEL
// Si on a un héros en session, on affiche le COMBAT
if (isset($_SESSION['hero'])): 
?>

   <div class="arena">
        <div class="fighters-area">
            
            <div class="fighter hero">
                <div class="stats">
                    <strong><?php echo $_SESSION['hero']->getName(); ?></strong><br>
                    ❤️ <?php echo $_SESSION['hero']->getPv(); ?> PV
                </div>
                <img src="<?php echo $_SESSION['hero_img']; ?>" alt="Hero">
            </div>

            <div class="fighter enemy">
                <div class="stats">
                    <strong><?php echo $_SESSION['enemy']->getName(); ?></strong><br>
                    ❤️ <?php echo $_SESSION['enemy']->getPv(); ?> PV
                </div>
                <img src="<?php echo $_SESSION['enemy_img']; ?>" alt="Enemy" class="enemy-img">
            </div>
        </div>

        <div class="battle-log" id="logBox">
            <?php 
            // On affiche l'historique inversé (le plus récent en bas) ou normal
            if (!empty($_SESSION['logs'])) {
                foreach ($_SESSION['logs'] as $log) {
                    echo "<div class='log-line'>$log</div>";
                }
            } else {
                echo "<div class='log-line'>Le combat commence ! Préparez-vous...</div>";
            }
            ?>
        </div>

        <div class="controls">
            
            <?php if (!$_SESSION['hero']->isDead() && !$_SESSION['enemy']->isDead()): ?>
                <form method="POST">
                    <input type="hidden" name="mode" value="single">

                    <button type="submit" name="action" value="attack" class="action-btn attack">
                        ⚔️ ATTAQUER
                    </button>
                    <button type="submit" name="action" value="heal" class="action-btn heal">
                        🧪 SOIGNER
                    </button>
                    <button type="submit" name="logout" class="action-btn abandon">Abandonner</button>
                </form>
            
            <?php else: ?>
                <div class="game-over">
                    <h3>COMBAT TERMINÉ</h3>
                    <form method="POST">
                        <button type="submit" name="logout" class="action-btn new-game">
                            🔄 NOUVEAU COMBAT
                        </button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        var logBox = document.getElementById("logBox");
        logBox.scrollTop = logBox.scrollHeight;
    </script>

<?php 
// Sinon (pas de héros en session), on affiche la SÉLECTION
else: 
    // On charge le JSON seulement ici, car on en a besoin pour la liste
    $json_data = file_get_contents('heros.json');
    $personnages = json_decode($json_data, true);
?>

    <div class="select-screen">
        <h2>Choisissez votre Champion</h2>
        <form method="POST">
            <input type="hidden" name="mode" value="single">
            <select name="hero_choice" required>
                <option value="">-- Sélectionnez un héros --</option>
                <?php foreach ($personnages as $perso): ?>
                    <option value="<?php echo $perso['id']; ?>">
                        <?php echo $perso['name']; ?> (<?php echo $perso['type']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <br>
            <button type="submit" class="action-btn">Entrer dans l'arène</button>
        </form>
    </div>

<?php endif; ?>
</div>