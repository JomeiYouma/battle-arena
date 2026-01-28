<?php
/**
 * DEBUG TOOLS - Outils de développement et debug
 */

require_once 'auth_helper.php';
requireDebugAuth();

// Actions
$message = null;
$messageType = 'success';

// Reset Session
if (isset($_POST['reset_session'])) {
    session_destroy();
    session_start();
    $_SESSION['debug_auth'] = true; // Garder l'auth debug
    $message = "Session réinitialisée avec succès";
}

// Clear Match Files
if (isset($_POST['clear_matches'])) {
    $matchDir = __DIR__ . '/data/matches/';
    $files = glob($matchDir . 'match_*');
    $count = 0;
    foreach ($files as $file) {
        if (unlink($file)) $count++;
    }
    $message = "$count fichiers de match supprimés";
}

// Clear Queue
if (isset($_POST['clear_queue'])) {
    $queueFile = __DIR__ . '/data/queue.json';
    file_put_contents($queueFile, '[]');
    $message = "File d'attente vidée";
}

// Récupérer les infos
$sessionData = $_SESSION ?? [];
$matchFiles = glob(__DIR__ . '/data/matches/match_*.json');
$queueData = json_decode(file_get_contents(__DIR__ . '/data/queue.json') ?: '[]', true);
$personnages = json_decode(file_get_contents('heros.json'), true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Tools - Horus Battle Arena</title>
    <link rel="icon" href="./media/website/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/account.css">
    <style>
        .debug-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 15px;
        }
        .debug-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4a0000;
        }
        .debug-header h1 {
            margin: 0;
            padding: 0;
            border: none;
            font-size: 1.5em;
        }
        .debug-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .debug-card {
            background: rgba(20, 20, 30, 0.9);
            border: 2px solid #4a0000;
            border-radius: 10px;
            padding: 15px;
        }
        .debug-card h3 {
            color: #ffd700;
            margin: 0 0 12px 0;
            font-size: 1em;
            padding-bottom: 8px;
            border-bottom: 1px solid #333;
        }
        .debug-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #c41e3a;
            margin-bottom: 5px;
        }
        .debug-card .label {
            color: #888;
            font-size: 0.85em;
        }
        .debug-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }
        .debug-btn {
            padding: 8px 16px;
            border: 2px solid #4a0000;
            border-radius: 6px;
            background: linear-gradient(135deg, #2a2a2a, #1a1a1a);
            color: #e0e0e0;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.3s;
        }
        .debug-btn:hover {
            border-color: #c41e3a;
            background: linear-gradient(135deg, #3a3a3a, #2a2a2a);
        }
        .debug-btn.danger {
            border-color: #8b0000;
            color: #ff6b6b;
        }
        .debug-btn.danger:hover {
            background: linear-gradient(135deg, #4a0000, #2a0000);
            border-color: #c41e3a;
        }
        .debug-btn.primary {
            background: linear-gradient(135deg, #c41e3a, #7a1226);
            border-color: #c41e3a;
            color: white;
        }
        .debug-btn.primary:hover {
            box-shadow: 0 0 15px rgba(196, 30, 58, 0.5);
        }
        .debug-info {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 6px;
            padding: 10px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 0.8em;
            color: #aaa;
            max-height: 150px;
            overflow-y: auto;
        }
        .debug-info pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }
        .alert.success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid #4caf50;
            color: #81c784;
        }
        .alert.error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            color: #e57373;
        }
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .quick-links a {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <div class="debug-header">
            <h1>🔧 Debug Tools</h1>
            <a href="index.php" class="debug-btn">← Retour au menu</a>
        </div>

        <?php if ($message): ?>
        <div class="alert <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- QUICK LINKS -->
        <div class="debug-card">
            <h3>🔗 Accès Rapide</h3>
            <div class="quick-links">
                <a href="simulation.php" class="debug-btn primary">📊 Simulateur</a>
                <a href="account.php" class="debug-btn">👤 Mon Compte</a>
                <a href="single_player.php" class="debug-btn">⚔️ Solo</a>
                <a href="multi_player.php" class="debug-btn">👥 Multi</a>
                <a href="reset_session.php" class="debug-btn danger">🔄 Reset Session</a>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="debug-grid">
            <div class="debug-card">
                <h3>📁 Fichiers de Match</h3>
                <div class="value"><?php echo count($matchFiles); ?></div>
                <div class="label">matchs sauvegardés</div>
                <div class="debug-actions">
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Supprimer tous les fichiers de match ?');">
                        <button type="submit" name="clear_matches" class="debug-btn danger">🗑️ Vider</button>
                    </form>
                </div>
            </div>

            <div class="debug-card">
                <h3>⏳ File d'attente</h3>
                <div class="value"><?php echo count($queueData); ?></div>
                <div class="label">joueurs en attente</div>
                <div class="debug-actions">
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="clear_queue" class="debug-btn danger">🗑️ Vider</button>
                    </form>
                </div>
            </div>

            <div class="debug-card">
                <h3>🦸 Personnages</h3>
                <div class="value"><?php echo count($personnages); ?></div>
                <div class="label">héros disponibles</div>
            </div>

            <div class="debug-card">
                <h3>💾 Session</h3>
                <div class="value"><?php echo count($sessionData); ?></div>
                <div class="label">variables en session</div>
                <div class="debug-actions">
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="reset_session" class="debug-btn danger">🔄 Reset</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- SESSION DATA -->
        <div class="debug-card">
            <h3>📋 Données de Session</h3>
            <div class="debug-info">
                <pre><?php echo htmlspecialchars(json_encode($sessionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>
        </div>

        <!-- QUEUE DATA -->
        <?php if (!empty($queueData)): ?>
        <div class="debug-card">
            <h3>⏳ Contenu de la File d'attente</h3>
            <div class="debug-info">
                <pre><?php echo htmlspecialchars(json_encode($queueData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>
        </div>
        <?php endif; ?>

        <!-- MATCH FILES -->
        <?php if (!empty($matchFiles)): ?>
        <div class="debug-card">
            <h3>📁 Derniers Matchs</h3>
            <div class="debug-info">
                <?php 
                $recentMatches = array_slice($matchFiles, -5);
                foreach ($recentMatches as $file): 
                    $matchData = json_decode(file_get_contents($file), true);
                    $filename = basename($file);
                ?>
                <div style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #333;">
                    <strong style="color: #ffd700;"><?php echo $filename; ?></strong><br>
                    <?php if ($matchData): ?>
                    Status: <?php echo $matchData['status'] ?? 'N/A'; ?> | 
                    P1: <?php echo $matchData['player1']['hero'] ?? 'N/A'; ?> vs 
                    P2: <?php echo $matchData['player2']['hero'] ?? 'N/A'; ?>
                    <?php else: ?>
                    <span style="color: #f44336;">Erreur de lecture</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SERVER INFO -->
        <div class="debug-card">
            <h3>🖥️ Infos Serveur</h3>
            <div class="debug-info">
                <pre>PHP: <?php echo phpversion(); ?>

Date: <?php echo date('Y-m-d H:i:s'); ?>

Session ID: <?php echo session_id(); ?>

Document Root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?>

Script: <?php echo $_SERVER['SCRIPT_NAME'] ?? 'N/A'; ?></pre>
            </div>
        </div>
    </div>
</body>
</html>
