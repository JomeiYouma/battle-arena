<?php
/**
 * Script de migration des héros JSON → BDD
 * 
 * Usage: php scripts/migrate_heroes.php
 * Ou via navigateur: http://localhost/.../scripts/migrate_heroes.php
 */

require_once __DIR__ . '/../includes/autoload.php';

echo "=== MIGRATION DES HÉROS JSON → BDD ===\n\n";

// Charger le fichier JSON
$jsonFile = __DIR__ . '/../heros.json';
if (!file_exists($jsonFile)) {
    die("Erreur: Fichier heros.json introuvable\n");
}

$heroesData = json_decode(file_get_contents($jsonFile), true);
if (!$heroesData) {
    die("Erreur: Impossible de parser heros.json\n");
}

echo "Héros trouvés dans JSON: " . count($heroesData) . "\n\n";

$manager = new HeroManager();
$success = 0;
$errors = 0;

foreach ($heroesData as $data) {
    try {
        // Vérifier si le héros existe déjà
        $existing = $manager->getByHeroId($data['id']);
        if ($existing) {
            echo "⏭ Ignoré (existe déjà): {$data['name']}\n";
            continue;
        }
        
        // Créer le héros
        $hero = new Hero([
            'hero_id' => $data['id'],
            'name' => $data['name'],
            'type' => $data['type'],
            'pv' => $data['pv'],
            'atk' => $data['atk'],
            'def' => $data['def'],
            'speed' => $data['speed'],
            'description' => $data['description'] ?? '',
            'image_p1' => $data['images']['p1'] ?? '',
            'image_p2' => $data['images']['p2'] ?? '',
            'is_active' => 1
        ]);
        
        $manager->add($hero);
        echo "✓ Importé: {$data['name']} ({$data['type']})\n";
        $success++;
        
    } catch (Exception $e) {
        echo "✗ Erreur pour {$data['name']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== RÉSULTAT ===\n";
echo "Réussis: $success\n";
echo "Erreurs: $errors\n";
echo "\nMigration terminée !\n";
