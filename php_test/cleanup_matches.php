<?php
$matchesDir = __DIR__ . '/data/matches/';

// Lister tous les fichiers
$files = glob($matchesDir . '*');

echo "Nettoyage des fichiers de match cassés...\n";
echo "Avant: " . count($files) . " fichiers trouvés\n";

foreach ($files as $file) {
    if (unlink($file)) {
        echo "✓ Supprimé: " . basename($file) . "\n";
    } else {
        echo "✗ Erreur pour: " . basename($file) . "\n";
    }
}

$remaining = glob($matchesDir . '*');
echo "\nAprès: " . count($remaining) . " fichiers restants\n";
echo "\n✅ Cleanup complète!";
?>
