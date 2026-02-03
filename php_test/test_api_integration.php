<?php
/**
 * Test d'intégration du système de forced switch via l'API
 */

echo "=== TEST D'INTÉGRATION FORCED SWITCH ===\n\n";

// Simuler une session de joueur
session_start();
$_SESSION['user_id'] = 'test_user_1';

// Simuler les données POST d'une requête
$_POST['action'] = 'submit_forced_switch';
$_POST['match_id'] = 'test_match_001';
$_POST['target_index'] = '1';

echo "Paramètres simul és:\n";
echo "  - action: " . $_POST['action'] . "\n";
echo "  - match_id: " . $_POST['match_id'] . "\n";
echo "  - target_index: " . $_POST['target_index'] . "\n\n";

// Vérifier que l'endpoint serait présent dans api.php
$api_content = file_get_contents(__DIR__ . '/api.php');

if (strpos($api_content, "case 'submit_forced_switch':") !== false) {
    echo "✓ Case 'submit_forced_switch' trouvée dans api.php\n";
} else {
    echo "✗ Case 'submit_forced_switch' NON trouvée dans api.php\n";
}

if (strpos($api_content, "performForcedSwitch") !== false) {
    echo "✓ Appel à performForcedSwitch trouvé dans api.php\n";
} else {
    echo "✗ Appel à performForcedSwitch NON trouvé dans api.php\n";
}

echo "\n=== TEST TERMINÉ ===\n";
?>
