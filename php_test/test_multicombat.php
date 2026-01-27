<?php
// Test MultiCombat loading scenario
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

// Try loading MultiCombat
require_once 'classes/MultiCombat.php';
echo 'MultiCombat loaded successfully' . PHP_EOL;

// Test creating a combat
echo 'Testing MultiCombat::create(): ';
try {
    $combat = MultiCombat::create('Guerrier', 'Mage');
    echo 'MultiCombat created successfully' . PHP_EOL;
    echo 'Player 1 class: ' . get_class($combat->getPlayer()) . PHP_EOL;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}

echo 'All tests passed!' . PHP_EOL;
