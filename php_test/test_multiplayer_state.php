<?php
// Test multiplayer match creation and state retrieval
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

// Get heros.json
$heros = json_decode(file_get_contents('heros.json'), true);

// Simulate match metadata like it would be stored by MatchQueue
$matchData = [
    'id' => 'match_test_bless',
    'created_at' => time(),
    'status' => 'active',
    'turn' => 1,
    'mode' => 'pvp',
    'player1' => [
        'session' => 'session_p1',
        'hero' => $heros[0],
        'display_name' => 'Warrior',
        'user_id' => 1,
        'hp' => $heros[0]['pv'],
        'max_hp' => $heros[0]['pv'],
        'blessing_id' => 'WheelOfFortune',
        'last_poll' => time()
    ],
    'player2' => [
        'session' => 'session_p2',
        'hero' => $heros[1],
        'display_name' => 'Mage',
        'user_id' => 2,
        'hp' => $heros[1]['pv'],
        'max_hp' => $heros[1]['pv'],
        'blessing_id' => 'LoversCharm',
        'last_poll' => time()
    ],
    'logs' => [],
    'current_turn_actions' => [],
    'last_update' => time()
];

echo "Creating MultiCombat match..." . PHP_EOL;

try {
    // Create combat like api.php does
    $combat = MultiCombat::create($matchData['player1'], $matchData['player2']);
    
    // Get state for player 1
    $state = $combat->getStateForUser('session_p1', $matchData);
    
    echo "✓ Combat created and state retrieved" . PHP_EOL;
    echo "\nPlayer 1 available actions: " . count($state['actions'] ?? []) . PHP_EOL;
    
    if (!empty($state['actions'])) {
        foreach ($state['actions'] as $key => $action) {
            $name = $action['name'] ?? $key;
            $pp = $action['ppText'] ?? '';
            echo "  - $key: $name $pp" . PHP_EOL;
        }
    }
    
    // Verify blessings are present in the actions
    $hasBlessing = false;
    foreach (($state['actions'] ?? []) as $key => $action) {
        if (strpos($key, 'concoction') !== false) {
            $hasBlessing = true;
            echo "\n✓ Blessing action found: $key" . PHP_EOL;
            break;
        }
    }
    
    if (!$hasBlessing) {
        echo "\n⚠ WARNING: No blessing actions found in state!" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
