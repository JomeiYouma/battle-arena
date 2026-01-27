<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'classes/Personnage.php';

echo "Reflecting Personnage...\n";
$ref = new ReflectionClass('Personnage');
if ($ref->hasMethod('triggerHealHooks')) {
    echo "✅ triggerHealHooks exists.\n";
} else {
    echo "❌ triggerHealHooks DOES NOT EXIST.\n";
}

if ($ref->hasMethod('receiveDamage')) {
    echo "✅ receiveDamage exists.\n";
    $method = $ref->getMethod('receiveDamage');
    $params = $method->getParameters();
    echo "receiveDamage params: " . count($params) . "\n";
    foreach($params as $p) {
        echo "- " . $p->getName() . "\n";
    }
} else {
    echo "❌ receiveDamage DOES NOT EXIST.\n";
}
