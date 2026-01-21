<?php
function chargerClasse($classe) {
    if (file_exists('classes/' . $classe . '.php')) {
        require 'classes/' . $classe . '.php';
    }
}
spl_autoload_register('chargerClasse');
session_start();
$modeChoisi = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode'])) {
    $modeChoisi = $_POST['mode'];
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