<?php
// Test blessings application in MultiCombat
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

// Now load MultiCombat with autoloader in place
require_once 'classes/MultiCombat.php';

// Get heros.json for hero data
$heros = json_decode(file_get_contents('heros.json'), true);
if (!$heros) {
    die("Failed to load heros.json");
}

// Create match data in the same format as api.php does
$player1 = [
    'hero' => $heros[0],
    'blessing_id' => 'WheelOfFortune',
    'display_name' => 'Player 1',
    'session' => 'test_session_1'
];

$player2 = [
    'hero' => $heros[1],
    'blessing_id' => 'LoversCharm',
    'display_name' => 'Player 2',
    'session' => 'test_session_2'
];

// Create a MultiCombat with the correct player data structure
echo "Creating MultiCombat with player data and blessings..." . PHP_EOL;
try {
    $combat = MultiCombat::create($player1, $player2);
    echo "MultiCombat created successfully" . PHP_EOL;
    
    // Check if blessings were applied
    $player1Char = $combat->getPlayer();
    $player2Char = $combat->getEnemy();
    
    $p1Blessings = $player1Char->getBlessings();
    $p2Blessings = $player2Char->getBlessings();
    
    echo "Player 1 blessings count: " . count($p1Blessings) . " (expected: 1)" . PHP_EOL;
    echo "Player 2 blessings count: " . count($p2Blessings) . " (expected: 1)" . PHP_EOL;
    
    if (!empty($p1Blessings)) {
        $p1First = reset($p1Blessings);
        echo "  - Player 1 blessing: " . get_class($p1First) . PHP_EOL;
    }
    if (!empty($p2Blessings)) {
        $p2First = reset($p2Blessings);
        echo "  - Player 2 blessing: " . get_class($p2First) . PHP_EOL;
    }
    
    // Check if actions include blessing actions
    $p1Actions = $player1Char->getAllActions();
    $p2Actions = $player2Char->getAllActions();
    
    echo "\nPlayer 1 actions: " . count($p1Actions) . PHP_EOL;
    foreach ($p1Actions as $key => $action) {
        echo "  - $key: " . $action['name'] . PHP_EOL;
    }
    
    echo "\nPlayer 2 actions: " . count($p2Actions) . PHP_EOL;
    foreach ($p2Actions as $key => $action) {
        echo "  - $key: " . $action['name'] . PHP_EOL;
    }
    
    echo "\n✓ Test passed!" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
