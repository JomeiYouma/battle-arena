<?php
/**
 * ACCOUNT PAGE - Page "Mon compte" avec statistiques
 */

// Autoloader
function chargerClasse($classe) {
    if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
        require __DIR__ . '/classes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
        require __DIR__ . '/classes/effects/' . $classe . '.php';
        return;
    }
}
spl_autoload_register('chargerClasse');
session_start();

// Rediriger si non connecté
if (!User::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Traitement déconnexion
if (isset($_POST['logout'])) {
    User::logout();
    header('Location: index.php');
    exit;
}

// Récupérer les données
$userId = User::getCurrentUserId();
$username = User::getCurrentUsername();
$userModel = new User();

$globalStats = $userModel->getGlobalStats($userId);
$mostPlayed = $userModel->getMostPlayedHeroes($userId);
$heroStats = $userModel->getHeroStats($userId);
$recentCombats = $userModel->getRecentCombats($userId);

// Charger les héros pour les noms
$heroes = json_decode(file_get_contents(__DIR__ . '/heros.json'), true);
$heroNames = [];
foreach ($heroes as $hero) {
    $heroNames[$hero['id']] = $hero['name'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte - Horus Battle Arena</title>
    <link rel="icon" href="./media/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
    <style>
        .account-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .account-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4a0000;
        }
        
        .account-header h1 {
            color: #ffd700;
            margin: 0;
        }
        
        .account-header .username {
            color: #c41e3a;
            font-size: 1.5em;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #333, #222);
            border: 2px solid #555;
            color: #aaa;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            border-color: #c41e3a;
            color: #fff;
        }
        
        /* Stats Cards Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(20, 20, 30, 0.9);
            border: 2px solid #4a0000;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: transform 0.3s, border-color 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #c41e3a;
        }
        
        .stat-card .value {
            font-size: 2.5em;
            font-weight: bold;
            color: #ffd700;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #888;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card.wins .value { color: #4CAF50; }
        .stat-card.losses .value { color: #f44336; }
        .stat-card.ratio .value { color: #2196F3; }
        
        /* Section */
        .section {
            background: rgba(20, 20, 30, 0.9);
            border: 2px solid #4a0000;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #ffd700;
            margin: 0 0 20px 0;
            font-size: 1.3em;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        
        /* Most Played Heroes */
        .hero-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .hero-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border-left: 4px solid #c41e3a;
        }
        
        .hero-item .rank {
            font-size: 1.5em;
            color: #ffd700;
            width: 50px;
        }
        
        .hero-item .name {
            flex: 1;
            color: #e0e0e0;
            font-weight: bold;
        }
        
        .hero-item .games {
            color: #888;
            margin-right: 15px;
        }
        
        .hero-item .winrate {
            color: #4CAF50;
            font-weight: bold;
        }
        
        /* Combat History */
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th,
        .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #333;
        }
        
        .history-table th {
            color: #b8860b;
            font-weight: normal;
            text-transform: uppercase;
            font-size: 0.8em;
            letter-spacing: 1px;
        }
        
        .history-table td {
            color: #e0e0e0;
        }
        
        .result-victory {
            color: #4CAF50;
            font-weight: bold;
        }
        
        .result-defeat {
            color: #f44336;
            font-weight: bold;
        }
        
        .mode-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            background: #333;
            color: #888;
        }
        
        .mode-badge.multi {
            background: #1a237e;
            color: #7986cb;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        
        /* Navigation */
        .nav-links {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .nav-links a {
            color: #b8860b;
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(20, 20, 30, 0.8);
            border: 1px solid #333;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            border-color: #c41e3a;
            color: #ffd700;
        }
    </style>
</head>
<body>
    <div class="account-container">
        <div class="nav-links">
            <a href="index.php">← Retour au jeu</a>
        </div>
        
        <div class="account-header">
            <div>
                <h1>Mon Compte</h1>
                <span class="username">⚔️ <?php echo htmlspecialchars($username); ?></span>
            </div>
            <form method="POST" style="margin: 0;">
                <button type="submit" name="logout" class="logout-btn">Déconnexion</button>
            </form>
        </div>
        
        <!-- Stats Globales -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?php echo $globalStats['total']; ?></div>
                <div class="label">Combats</div>
            </div>
            <div class="stat-card wins">
                <div class="value"><?php echo $globalStats['wins']; ?></div>
                <div class="label">Victoires</div>
            </div>
            <div class="stat-card losses">
                <div class="value"><?php echo $globalStats['losses']; ?></div>
                <div class="label">Défaites</div>
            </div>
            <div class="stat-card ratio">
                <div class="value"><?php echo $globalStats['ratio']; ?>%</div>
                <div class="label">Ratio</div>
            </div>
        </div>
        
        <!-- Personnages les plus joués -->
        <div class="section">
            <h2>🎮 Personnages les plus joués</h2>
            <?php if (empty($mostPlayed)): ?>
                <div class="empty-state">
                    <div class="icon">⚔️</div>
                    <p>Aucun combat enregistré. Lancez-vous dans l'arène !</p>
                </div>
            <?php else: ?>
                <div class="hero-list">
                    <?php foreach ($mostPlayed as $i => $hero): 
                        $winrate = $hero['games'] > 0 ? round(($hero['wins'] / $hero['games']) * 100) : 0;
                        $rankEmoji = ['🥇', '🥈', '🥉'][$i] ?? ($i + 1);
                    ?>
                        <div class="hero-item">
                            <span class="rank"><?php echo $rankEmoji; ?></span>
                            <span class="name"><?php echo htmlspecialchars($heroNames[$hero['hero_id']] ?? $hero['hero_id']); ?></span>
                            <span class="games"><?php echo $hero['games']; ?> parties</span>
                            <span class="winrate"><?php echo $winrate; ?>% winrate</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Historique récent -->
        <div class="section">
            <h2>📜 Historique récent</h2>
            <?php if (empty($recentCombats)): ?>
                <div class="empty-state">
                    <div class="icon">📜</div>
                    <p>Aucun historique disponible.</p>
                </div>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Héros</th>
                            <th>Adversaire</th>
                            <th>Résultat</th>
                            <th>Mode</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCombats as $combat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($heroNames[$combat['hero_id']] ?? $combat['hero_id']); ?></td>
                                <td><?php echo htmlspecialchars($heroNames[$combat['opponent_hero_id']] ?? $combat['opponent_hero_id'] ?? '-'); ?></td>
                                <td class="<?php echo $combat['victory'] ? 'result-victory' : 'result-defeat'; ?>">
                                    <?php echo $combat['victory'] ? '✓ Victoire' : '✗ Défaite'; ?>
                                </td>
                                <td>
                                    <span class="mode-badge <?php echo $combat['game_mode']; ?>">
                                        <?php echo $combat['game_mode'] === 'multi' ? 'Multi' : 'Solo'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($combat['played_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Stats par héros -->
        <?php if (!empty($heroStats)): ?>
        <div class="section">
            <h2>📊 Statistiques par héros</h2>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Héros</th>
                        <th>Parties</th>
                        <th>Victoires</th>
                        <th>Défaites</th>
                        <th>Winrate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($heroStats as $stat): 
                        $winrate = $stat['games'] > 0 ? round(($stat['wins'] / $stat['games']) * 100) : 0;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($heroNames[$stat['hero_id']] ?? $stat['hero_id']); ?></td>
                            <td><?php echo $stat['games']; ?></td>
                            <td class="result-victory"><?php echo $stat['wins']; ?></td>
                            <td class="result-defeat"><?php echo $stat['losses']; ?></td>
                            <td><?php echo $winrate; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
