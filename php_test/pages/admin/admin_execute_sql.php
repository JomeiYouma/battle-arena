<?php
/**
 * SCRIPT D'ADMINISTRATION - Exécuter le SQL de création des tables d'équipes
 * À exécuter une fois pour initialiser la BD
 */

// Autoloader centralisé
require_once __DIR__ . '/../../includes/autoload.php';

// Connexion BD
try {
    $pdo = new PDO('mysql:host=localhost;dbname=horus_arena;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connexion BD échouée: " . $e->getMessage());
}

// Lire et exécuter le fichier SQL
$sqlFile = DATA_PATH . '/schema_teams.sql';
if (!file_exists($sqlFile)) {
    die("Fichier SQL non trouvé: $sqlFile");
}

$sqlContent = file_get_contents($sqlFile);

// Supprimer les commentaires et découper par ;
$statements = array_filter(
    array_map('trim', explode(';', $sqlContent)),
    fn($s) => !empty($s) && !str_starts_with(trim($s), '--')
);

echo "<h2>Exécution des scripts SQL</h2>";
echo "<ul>";

foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        echo "<li><strong style='color: green;'>✅</strong> " . substr($statement, 0, 80) . "...</li>";
    } catch (PDOException $e) {
        echo "<li><strong style='color: orange;'>⚠️</strong> " . substr($statement, 0, 80) . "... - " . $e->getMessage() . "</li>";
    }
}

echo "</ul>";

// Vérifier les tables créées
echo "<h2>Vérification des Tables</h2>";
try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<strong>Tables créées:</strong><br>";
    foreach ($tables as $table) {
        echo "- <strong>" . htmlspecialchars($table) . "</strong><br>";
    }
    
    // Détails de chaque nouvelle table
    foreach (['teams', 'team_members', 'team_combat_state'] as $table) {
        if (in_array($table, $tables)) {
            echo "<br><strong>Colonnes de $table:</strong><br>";
            $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                echo "- {$col['Field']} ({$col['Type']}) " . ($col['Null'] === 'NO' ? '<strong>NOT NULL</strong>' : '') . "<br>";
            }
        }
    }

    // Vérifier les colonnes ajoutées à match_queue
    echo "<br><strong>Colonnes de match_queue (check game_mode et team_id):</strong><br>";
    $cols = $pdo->query("DESCRIBE match_queue")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        if (in_array($col['Field'], ['game_mode', 'team_id'])) {
            echo "✅ {$col['Field']} ({$col['Type']})<br>";
        }
    }

} catch (PDOException $e) {
    echo "Erreur lors de la vérification: " . $e->getMessage();
}

echo "<p><a href='javascript:history.back()'>← Retour</a></p>";
?>
