<?php
/**
 * Test de vérification du système de blessings et du combat
 */

require_once __DIR__ . '/index.php';

echo "=== TEST BLESSINGS ===\n\n";

// Créer deux guerriers avec des blessings
$p1 = new Guerrier(100, 15, "Guerrier1");
$p2 = new Guerrier(100, 15, "Guerrier2");

// Ajouter des blessings
$wheelOfFortune = new WheelOfFortune();
$loversCharm = new LoversCharm();

$p1->addBlessing($wheelOfFortune);
$p2->addBlessing($loversCharm);

echo "P1: " . $p1->getName() . " avec " . $wheelOfFortune->getName() . "\n";
echo "P2: " . $p2->getName() . " avec " . $loversCharm->getName() . "\n\n";

// Test des actions blessings
echo "Actions disponibles pour P1:\n";
$actions = $p1->getAllActions();
foreach ($actions as $key => $action) {
    echo "  - $key: " . $action['label'] . "\n";
}
echo "\n";

// Créer un combat
echo "=== DÉMARRAGE DU COMBAT ===\n\n";
$combat = new Combat($p1, $p2);

// Exécuter quelques tours
for ($i = 0; $i < 3 && !$combat->isOver(); $i++) {
    echo "Tour " . ($i+1) . ":\n";
    $combat->executePlayerAction('attack');
    
    $logs = $combat->getLogs();
    $lastLog = array_pop($logs);
    echo "  " . $lastLog . "\n";
}

echo "\n=== TEST NÉCROMANCIEN ===\n\n";

// Créer un nécromancien
$necro = new Necromancien(100, 15, "Nécro");
$guerrier = new Guerrier(100, 15, "Guerrier");

echo "Nécromancien: " . $necro->getName() . "\n";
echo "Guerrier: " . $guerrier->getName() . "\n\n";

$necro->addBlessing(new WheelOfFortune());

$combat2 = new Combat($necro, $guerrier);

echo "Tour 1: Nécro attaque\n";
$combat2->executePlayerAction('attack');

echo "Tour 2: Nécro utilise Ordre Nécrotique\n";
$combat2->executePlayerAction('ordre_necrotique');

$logs = $combat2->getLogs();
foreach (array_slice($logs, -5) as $log) {
    echo "  $log\n";
}

echo "\n✅ Test terminé!\n";
?>
