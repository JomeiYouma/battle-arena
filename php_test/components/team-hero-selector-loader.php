<?php
/**
 * LOADER: Affiche selection-screen.php adapté pour les équipes
 * Chargé via AJAX
 */

// Autoloader
function chargerClasse($classe) {
    if (file_exists(__DIR__ . '/../classes/' . $classe . '.php')) {
        require __DIR__ . '/../classes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/../classes/heroes/' . $classe . '.php')) {
        require __DIR__ . '/../classes/heroes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/../classes/blessings/' . $classe . '.php')) {
        require __DIR__ . '/../classes/blessings/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/../classes/Services/' . $classe . '.php')) {
        require __DIR__ . '/../classes/Services/' . $classe . '.php';
        return;
    }
}
spl_autoload_register('chargerClasse');

$teamId = (int) ($_GET['team_id'] ?? 0);
$position = (int) ($_GET['position'] ?? 0);

if ($teamId > 0 && $position > 0) {
    // Inclure et afficher selection-screen.php
    include 'selection-screen.php';
    renderSelectionScreen([
        'mode' => 'team_selection',
        'teamId' => $teamId,
        'position' => $position,
        'showPlayerNameInput' => false,
        'singleHeroSelection' => true
    ]);
}
?>
