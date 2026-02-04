<?php
/** TEAM-HERO-SELECTOR-LOADER - Chargement AJAX du sélecteur héros */

require_once __DIR__ . '/../includes/autoload.php';

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
