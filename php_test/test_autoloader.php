<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// COPY OF LOGIC FROM API.PHP
function chargerClasse($classe) {
    if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/effects/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/heroes/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/heroes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/blessings/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/blessings/' . $classe . '.php';
        return;
    }
}
spl_autoload_register('chargerClasse');

echo "Testing Autoloader for Guerrier...\n";

try {
    if (class_exists('Guerrier')) {
        echo "✅ Class Guerrier loaded.\n";
        $ref = new ReflectionClass('Guerrier');
        echo "   File: " . $ref->getFileName() . "\n";
        $g = new Guerrier(100, 10, "Test");
        echo "   Instantiation successful.\n";
    } else {
        echo "❌ Class Guerrier NOT FOUND.\n";
    }
} catch (Throwable $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

try {
    if (class_exists('MultiCombat')) {
        echo "✅ Class MultiCombat loaded.\n";
        // Check if MultiCombat can see Guerrier?
    }
} catch (Throwable $e) {
    echo "❌ Exception MultiCombat: " . $e->getMessage() . "\n";
}
