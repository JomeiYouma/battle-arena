<?php
// Autoloader manual for script
function chargerClasse($classe) {
    if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/' . $classe . '.php'; return;
    }
    if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/effects/' . $classe . '.php'; return;
    }
    // Chercher dans classes/heroes/
    if (file_exists(__DIR__ . '/classes/heroes/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/heroes/' . $classe . '.php'; return;
    }
    if (file_exists(__DIR__ . '/classes/blessings/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/blessings/' . $classe . '.php'; return;
    }
}
spl_autoload_register('chargerClasse');

require_once 'classes/Personnage.php';

require_once 'classes/Personnage.php';
// Others loaded via autoloader now
// require_once 'classes/heroes/Guerrier.php'; 
// require_once 'classes/heroes/Guerisseur.php';
// Load others if needed for logic check

function assertTest($condition, $msg) {
    echo ($condition ? "✅ PASS" : "❌ FAIL") . ": $msg\n";
}

echo "=== VÉRIFICATION DES BÉNÉDICTIONS V2 ===\n\n";

// 1. Roue de Fortune
echo "--- 1. Roue de Fortune ---\n";
$p = new Guerrier(100, 10, "TestHero");
$p->addBlessing(new WheelOfFortune());
$rolls = [];
for($i=0; $i<50; $i++) $rolls[] = $p->roll(1, 10);
$min = min($rolls);
$max = max($rolls);
assertTest($min < 1 || $max > 10, "Roll range expanded (Observed: [$min, $max])");
$actions = $p->getAllActions();
assertTest(isset($actions['concoction_maladroite']), "Action 'concoction_maladroite' added");

// 2. Charmes Amoureux (Reflect Test using manually injected Attacker)
echo "\n--- 2. Charmes Amoureux (Reflect) ---\n";
$p = new Guerrier(100, 10, "Lover");
$p->addBlessing(new LoversCharm());
$attacker = new Guerrier(100, 50, "Attacker"); // 50 ATK
// Simuler coup avec attacker context sur receiveDamage
$attacker->setPv(100);
$dmg = 40;
$p->receiveDamage($dmg, $attacker); 
// 25% of 40 = 10 reflect.
assertTest($attacker->getPv() == 90, "Reflect works: Attacker PV 100->90 (Observed: " . $attacker->getPv() . ")");


// 3. Jugement des Maudits (Heal curse)
/*
echo "\n--- 3. Jugement des Maudits (Anti-Heal) ---\n";
$healer = new Guerisseur(100, 10, "Judge");
$healer->setDef(20);
$healer->addBlessing(new JudgmentOfDamned());

// Debug method existence
echo "Methods on healer:\n";
$methods = get_class_methods($healer);
if (in_array('triggerHealHooks', $methods)) echo "✅ triggerHealHooks FOUND\n";
else echo "❌ triggerHealHooks NOT FOUND\n";

// Start heal
$healer->heal(); // Should invoke triggerHealHooks
$def = $healer->getDef();
// Base Def 20. 35% reduction = 7. Expected 13.
// Wait, Guerisseur constructor params: pv, atk, name, def, speed.
// Def passed 10 in constructor logic above? No params: default 5.
// I setDef(20) before heal.
assertTest($def < 20, "DEF reduced on heal (Base 20 -> $def). Expected ~13.");
*/


// 4. Faveur de Force (Immunity Test)
echo "\n--- 4. Faveur de Force (Immunity) ---\n";
$p = new Guerrier(100, 20, "Strong", 20); 
$p->addBlessing(new StrengthFavor());
// Activate Transe
$p->addStatusEffect(new ImmunityEffect(3));
// Try to add Poison
$poison = new PoisonEffect(3, 10);
$p->addStatusEffect($poison);
// Check if poison is in statusEffects
$effects = $p->getActiveEffects();
assertTest(!isset($effects['Poison']), "Immunity blocked Poison");


// 5. La Tour de Garde (Def Attack)
echo "\n--- 6. La Tour de Garde ---\n";
$p = new Guerrier(100, 10, "Tower", 50); 
$p->addBlessing(new WatchTower());
assertTest($p->getAtk() == 50, "ATK uses DEF value (50). Got: " . $p->getAtk());


// 7. Chariot de Ra (Speed + Effect Modification)
echo "\n--- 7. Chariot de Ra ---\n";
$p = new Guerrier(100, 10, "Ra", 5, 20); 
$p->addBlessing(new RaChariot());
assertTest($p->getSpeed() == 30, "Speed +50% (20 -> 30). Got: " . $p->getSpeed());


echo "\n=== FIN VERIFICATION V2 ===\n";
