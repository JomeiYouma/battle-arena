<?php
// Simulate a full multiplayer turn
session_start();
$_SESSION['test_session'] = 'test_' . uniqid();

function chargerClasse($classe) {
    if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
        require __DIR__ . '/classes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
        require __DIR__ . '/classes/effects/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/blessings/' . $classe . '.php')) {
        require __DIR__ . '/classes/blessings/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/heroes/' . $classe . '.php')) {
        require __DIR__ . '/classes/heroes/' . $classe . '.php';
        return;
    }
}
spl_autoload_register('chargerClasse');

require_once 'classes/MultiCombat.php';

// Get heros
$heros = json_decode(file_get_contents('heros.json'), true);

// Create a match like MatchQueue would
$matchId = 'match_test_' . uniqid();
$matchData = [
    'id' => $matchId,
    'created_at' => time(),
    'status' => 'active',
    'turn' => 1,
    'mode' => 'pvp',
    'player1' => [
        'session' => 'session_p1',
        'hero' => $heros[0],
        'display_name' => 'Warrior',
        'hp' => $heros[0]['pv'],
        'max_hp' => $heros[0]['pv'],
        'blessing_id' => 'WheelOfFortune',
        'last_poll' => time()
    ],
    'player2' => [
        'session' => 'session_p2',
        'hero' => $heros[1],
        'display_name' => 'Mage',
        'hp' => $heros[1]['pv'],
        'max_hp' => $heros[1]['pv'],
        'blessing_id' => 'LoversCharm',
        'last_poll' => time()
    ],
    'logs' => [],
    'current_turn_actions' => [],
    'last_update' => time()
];

echo "Test: Full multiplayer turn simulation\n";
echo "=====================================\n\n";

try {
    // Create combat
    echo "1. Creating MultiCombat... ";
    $combat = MultiCombat::create($matchData['player1'], $matchData['player2']);
    echo "OK\n";
    
    // Get state for both players
    echo "2. Getting state for Player 1... ";
    $stateP1 = $combat->getStateForUser('session_p1', $matchData);
    echo "OK (" . count($stateP1['actions'] ?? []) . " actions)\n";
    
    echo "3. Getting state for Player 2... ";
    $stateP2 = $combat->getStateForUser('session_p2', $matchData);
    echo "OK (" . count($stateP2['actions'] ?? []) . " actions)\n";
    
    // Get action keys
    $p1ActionKeys = array_keys($stateP1['actions'] ?? []);
    $p2ActionKeys = array_keys($stateP2['actions'] ?? []);
    
    if (empty($p1ActionKeys) || empty($p2ActionKeys)) {
        throw new Exception("No actions available!");
    }
    
    $p1Move = $p1ActionKeys[0];
    $p2Move = $p2ActionKeys[0];
    
    echo "4. P1 selected: $p1Move\n";
    echo "5. P2 selected: $p2Move\n";
    
    // Resolve the turn
    echo "6. Resolving turn... ";
    $combat->resolveMultiTurn($p1Move, $p2Move);
    echo "OK\n";
    
    // Get updated state
    echo "7. Getting updated state... ";
    $stateP1After = $combat->getStateForUser('session_p1', $matchData);
    echo "OK\n";
    
    echo "\n✓ All tests passed!\n";
    echo "Turn actions executed successfully\n";
    
} catch (Exception $e) {
    echo "FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
