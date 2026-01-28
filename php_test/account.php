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
    <link rel="icon" href="./media/website/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="css/account.css">
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
            <form method="POST">
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
