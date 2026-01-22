<?php
// Autoloader (AVANT session_start pour la désérialisation des objets StatusEffect)
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
session_start();

// --- LOGIQUE DE RESET GLOBALE ---
// Doit être ici car les formulaires de reset sont soumis vers index.php
if (isset($_POST['logout']) || isset($_POST['new_game'])) {
    session_unset();
    session_destroy();
    // Redémarrer une nouvelle session vide
    session_start();
    // Afficher le menu principal (pas de mode choisi)
    $modeChoisi = null;
} else {
    $modeChoisi = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode'])) {
        $modeChoisi = $_POST['mode'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horus Battle Arena</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <h1>Horus Battle Arena</h1>

    <?php if ($modeChoisi === null): ?>

        <div class="menu-container">
            <form method="POST">
                <button type="submit" name="mode" value="single" class="menu-btn">
                    Single player
                </button>
                
                <button type="submit" name="mode" value="multi" class="menu-btn">
                    Multiplayer
                </button>
            </form>
        </div>

    <?php else: ?>

        <div class="mode-container">
            <?php
            if ($modeChoisi === 'single') {
                require 'single_player.php'; 
            } elseif ($modeChoisi === 'multi') {
                require 'multi_player.php'; 
            }
            ?>
            
            <br><br>
            <a href="index.php" class="back-link">Retour au menu</a>
        </div>

    <?php endif; ?>
    
</body>
</html>