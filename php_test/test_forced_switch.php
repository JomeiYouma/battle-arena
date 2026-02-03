<?php
/**
 * Test du système de forced switch (remplacement obligatoire après mort)
 */

require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Personnage.php';
require_once __DIR__ . '/classes/heroes/Guerrier.php';
require_once __DIR__ . '/classes/Combat.php';
require_once __DIR__ . '/classes/MultiCombat.php';
require_once __DIR__ . '/classes/TeamCombat.php';

// Créer une équipe de test
$team1 = [];
for ($i = 0; $i < 5; $i++) {
    $hero = new Guerrier(150, 20, "Guerrier P1 #" . ($i + 1), 10, 10);
    $team1[] = $hero;
}

$team2 = [];
for ($i = 0; $i < 5; $i++) {
    $hero = new Guerrier(150, 20, "Guerrier P2 #" . ($i + 1), 10, 10);
    $team2[] = $hero;
}

// Créer un combat d'équipe
$combat = new TeamCombat($team1, $team2);

echo "=== TEST FORCED SWITCH ===\n\n";

// État initial
echo "1. État initial:\n";

// Utiliser Reflection pour accéder aux propriétés privées
$reflection = new ReflectionClass($combat);

$currentPlayer1Index = $reflection->getProperty('currentPlayer1Index');
$currentPlayer1Index->setAccessible(true);

echo "   - P1 Héros actif index: " . $currentPlayer1Index->getValue($combat) . "\n";
echo "   - P1 Héros actif nom: " . $team1[$currentPlayer1Index->getValue($combat)]->getName() . "\n";
echo "   - Tous les héros vivants\n\n";

// Tuer le héros actif de P1
echo "2. Tuer le héros actif de P1...\n";
$team1[0]->setPv(0); // Le tuer

// Vérifier que le héros est mort
if ($team1[0]->isDead()) {
    echo "   ✓ Héros mort: " . $team1[0]->getName() . " (PV: " . $team1[0]->getPv() . ")\n\n";
} else {
    echo "   ✗ ERREUR: Héros non mort\n\n";
}

// Appeler checkAndMarkForcedSwitches
echo "3. Appeler checkAndMarkForcedSwitches()...\n";
$combat->checkAndMarkForcedSwitches();

// Vérifier le flag (utiliser reflection pour accéder au private)
$needsForcedSwitchProp = $reflection->getProperty('player1NeedsForcedSwitch');
$needsForcedSwitchProp->setAccessible(true);
$needsForcedSwitch = $needsForcedSwitchProp->getValue($combat);

echo "   - P1 needsForcedSwitch: " . ($needsForcedSwitch ? "true" : "false") . "\n";
if ($needsForcedSwitch) {
    echo "   ✓ Le flag est activé\n\n";
} else {
    echo "   ✗ ERREUR: Le flag n'est pas activé\n\n";
}

// Effectuer un switch obligatoire vers le héros #2
echo "4. Effectuer performForcedSwitch(1, 1)...\n";
$result = $combat->performForcedSwitch(1, 1);
if ($result) {
    echo "   ✓ Switch réussi\n";
    echo "   - P1 Héros actif index: " . $currentPlayer1Index->getValue($combat) . "\n";
    echo "   - P1 Héros actif nom: " . $team1[$currentPlayer1Index->getValue($combat)]->getName() . "\n";
    
    // Vérifier le flag à nouveau
    $needsForcedSwitch = $needsForcedSwitchProp->getValue($combat);
    echo "   - P1 needsForcedSwitch: " . ($needsForcedSwitch ? "true" : "false") . "\n\n";
} else {
    echo "   ✗ ERREUR: Switch échoué\n\n";
}

// Vérifier le log
echo "5. Vérifier les logs:\n";
$logs = $combat->getLogs();
foreach ($logs as $log) {
    if (strpos($log, '🔄') !== false || strpos($log, '💀') !== false) {
        echo "   - " . $log . "\n";
    }
}

echo "\n=== TEST TERMINÉ ===\n";
?>
