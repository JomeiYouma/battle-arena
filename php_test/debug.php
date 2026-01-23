<?php
/**
 * DEBUG PAGE - Affiche l'état des sessions, queue et matchs
 * Utile pour débugger le mode multijoueur
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Action: Clear All
if (isset($_POST['clear_all'])) {
    // Nettoyer la session
    session_unset();
    
    // Nettoyer la queue
    $queueFile = __DIR__ . '/data/queue.json';
    if (file_exists($queueFile)) {
        file_put_contents($queueFile, '[]');
    }
    
    // Nettoyer tous les matchs
    $matchesDir = __DIR__ . '/data/matches/';
    if (is_dir($matchesDir)) {
        $files = glob($matchesDir . 'match_*.*');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    $clearMessage = "✅ Tout a été nettoyé !";
}

// Lire les données actuelles
$sessionData = $_SESSION;
$sessionId = session_id();

$queueFile = __DIR__ . '/data/queue.json';
$queueData = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];

$matchesDir = __DIR__ . '/data/matches/';
$matchFiles = is_dir($matchesDir) ? glob($matchesDir . 'match_*.json') : [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Multiplayer</title>
    <link rel="stylesheet" href="./style.css">
    <style>
        body {
            padding: 20px;
            background: #1a1a2e;
            color: #e0e0e0;
            font-family: 'Courier New', monospace;
        }
        .debug-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .debug-section {
            background: rgba(20, 20, 30, 0.9);
            border: 2px solid #c41e3a;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .debug-section h2 {
            color: #ffd700;
            margin-top: 0;
            border-bottom: 2px solid #b8860b;
            padding-bottom: 10px;
        }
        .debug-section h3 {
            color: #c41e3a;
            margin-top: 15px;
        }
        pre {
            background: #0a0a0f;
            border: 1px solid #444;
            padding: 15px;
            overflow-x: auto;
            border-radius: 5px;
        }
        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-danger {
            background: #c41e3a;
            color: white;
        }
        .btn-primary {
            background: #b8860b;
            color: white;
        }
        .success-message {
            background: #2ecc71;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1>Debug Mode - Multiplayer</h1>
        
        <?php if (isset($clearMessage)): ?>
            <div class="success-message"><?php echo $clearMessage; ?></div>
        <?php endif; ?>
        
        <div class="debug-section">
            <h2>Session Info</h2>
            <p><strong>Session ID:</strong> <?php echo htmlspecialchars($sessionId); ?></p>
            <h3>Données de Session:</h3>
            <pre><?php print_r($sessionData); ?></pre>
        </div>
        
        <div class="debug-section">
            <h2>Queue Status</h2>
            <p><strong>Joueurs en attente:</strong> <?php echo count($queueData); ?></p>
            <pre><?php print_r($queueData); ?></pre>
        </div>
        
        <div class="debug-section">
            <h2>Active Matches</h2>
            <p><strong>Nombre de matchs:</strong> <?php echo count($matchFiles); ?></p>
            <?php if (count($matchFiles) > 0): ?>
                <?php foreach ($matchFiles as $matchFile): ?>
                    <?php
                        $matchData = json_decode(file_get_contents($matchFile), true);
                        $matchId = basename($matchFile, '.json');
                    ?>
                    <h3><?php echo htmlspecialchars($matchId); ?></h3>
                    <pre><?php print_r($matchData); ?></pre>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Aucun match actif</p>
            <?php endif; ?>
        </div>
        
        <div class="debug-section">
            <h2>Actions</h2>
            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir tout nettoyer ?');">
                <button type="submit" name="clear_all" class="btn btn-danger">Clear All</button>
            </form>
            <button onclick="location.reload()" class="btn btn-primary">Refresh</button>
            <button onclick="location.href='index.php'" class="btn btn-primary">Menu</button>
        </div>
    </div>
</body>
</html>
