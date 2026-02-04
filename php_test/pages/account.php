<?php
/**
 * ACCOUNT PAGE - Page "Mon compte" avec statistiques
 */

// Autoloader centralisé
require_once __DIR__ . '/../includes/autoload.php';
// Note: autoload.php démarre déjà la session

// Rediriger si non connecté
if (!User::isLoggedIn()) {
    header('Location: auth/login.php');
    exit;
}

// Traitement déconnexion
if (isset($_POST['logout'])) {
    User::logout();
    header('Location: ../index.php');
    exit;
}

// Récupérer les données
$userId = User::getCurrentUserId();
$username = User::getCurrentUsername();
$userModel = new User();

// Statistiques 1v1 (single + multi, EXCLUT 5v5)
$globalStats = $userModel->get1v1GlobalStats($userId);
$mostPlayed = $userModel->getMostPlayedHeroes($userId, 3, null, true); // true = exclure 5v5
$heroStats = $userModel->getHeroStats($userId, null, true); // true = exclure 5v5
$recentCombats = $userModel->getRecentCombats($userId, 10, null, true); // true = exclure 5v5

// Statistiques 5v5 détaillées
$stats5v5 = $userModel->get5v5Stats($userId);
$statsByMode = $userModel->getStatsByMode($userId);
$bestHero5v5 = $userModel->getBestHeroByWinrate($userId, '5v5', 2);

// Charger les héros pour les noms depuis la BDD
// HeroManager et Hero sont chargés par l'autoloader
$heroManager = new HeroManager();
$heroesModels = $heroManager->getAll(true);
$heroNames = [];
foreach ($heroesModels as $hero) {
    $heroNames[$hero->getHeroId()] = $hero->getName();
}

// Charger les équipes de l'utilisateur
$teamManager = new TeamManager(new PDO('mysql:host=localhost;dbname=horus_arena;charset=utf8mb4', 'root', ''));
$userTeams = $teamManager->getTeamsByUser($userId);

// Traitement des actions équipes
$actionMessage = null;
$actionType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_team':
                $teamName = trim($_POST['team_name'] ?? '');
                if ($teamName && strlen($teamName) > 0) {
                    $newTeamId = $teamManager->createTeam($userId, $teamName, $_POST['team_description'] ?? '');
                    if ($newTeamId) {
                        $actionMessage = "Équipe créée avec succès!";
                        $actionType = "success";
                        $userTeams = $teamManager->getTeamsByUser($userId);
                    } else {
                        $actionMessage = "Erreur lors de la création de l'équipe.";
                        $actionType = "error";
                    }
                }
                break;
                
            case 'delete_team':
                $teamId = (int) $_POST['team_id'];
                if ($teamManager->userOwnsTeam($userId, $teamId)) {
                    if ($teamManager->deleteTeam($teamId)) {
                        $actionMessage = "Équipe supprimée avec succès!";
                        $actionType = "success";
                        $userTeams = $teamManager->getTeamsByUser($userId);
                    }
                }
                break;

            case 'add_hero_to_team':
                $teamId = (int) ($_POST['team_id'] ?? 0);
                $position = (int) ($_POST['position'] ?? 0);
                $heroId = $_POST['hero_id'] ?? '';  // String, pas int!
                $blessingId = $_POST['blessing_id'] ?? null;
                
                if (!empty($heroId) && $position >= 1 && $position <= 5) {
                    if ($teamManager->userOwnsTeam($userId, $teamId)) {
                        if ($teamManager->addMemberToTeam($teamId, $position, $heroId, $blessingId)) {
                            $actionMessage = "Héros ajouté à l'équipe!";
                            $actionType = "success";
                            $userTeams = $teamManager->getTeamsByUser($userId);
                        } else {
                            $actionMessage = "Erreur lors de l'ajout du héros.";
                            $actionType = "error";
                        }
                    }
                }
                break;

            case 'remove_hero_from_team':
                $teamId = (int) $_POST['team_id'];
                $position = (int) $_POST['position'];
                
                if ($position >= 1 && $position <= 5) {
                    if ($teamManager->userOwnsTeam($userId, $teamId)) {
                        if ($teamManager->removeMemberFromTeam($teamId, $position)) {
                            $actionMessage = "Héros retiré de l'équipe!";
                            $actionType = "success";
                            $userTeams = $teamManager->getTeamsByUser($userId);
                        }
                    }
                }
                break;
        }
    }
}

// Configuration du header
$pageTitle = 'Mon Compte - Horus Battle Arena';
$extraCss = ['account', 'shared-selection', 'multiplayer'];
$showUserBadge = false;
$showMainTitle = false;
require_once INCLUDES_PATH . '/header.php';
?>
    <!-- Tooltip system -->
    <div id="customTooltip" class="custom-tooltip"></div>
    <div class="account-container">
        
        <div class="account-header">
            <div>
                <h1>Mon Compte</h1>
                <span class="username"><?php echo htmlspecialchars($username); ?></span>
            </div>
            <form method="POST">
                <button type="submit" name="logout" class="logout-btn">Déconnexion</button>
            </form>
        </div>

        <!-- Message de notification -->
        <?php if ($actionMessage): ?>
            <div class="notification notification-<?php echo $actionType; ?>">
                <?php echo htmlspecialchars($actionMessage); ?>
            </div>
        <?php endif; ?>
        
        <!-- Système de Tabs -->
        <div class="tabs-navigation">
            <button class="tab-button active" onclick="switchTab('stats')">Statistiques 1v1</button>
            <button class="tab-button" onclick="switchTab('stats5v5')">Statistiques 5v5</button>
            <button class="tab-button" onclick="switchTab('teams')">Mes Équipes</button>
        </div>

        <!-- TAB 1: Statistiques -->
        <div id="stats-tab" class="tab-content active">
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
            <h2>Personnages les plus joués</h2>
            <?php if (empty($mostPlayed)): ?>
                <div class="empty-state">
                    <div class="icon"></div>
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
            <h2>Historique récent</h2>
            <?php if (empty($recentCombats)): ?>
                <div class="empty-state">
                    <div class="icon"></div>
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
            <h2>Statistiques par héros</h2>
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

    <!-- TAB 2: Statistiques 5v5 -->
    <div id="stats5v5-tab" class="tab-content">
        <?php 
        $s5v5 = $stats5v5['global'];
        $mostPlayed5v5 = $stats5v5['mostPlayed'];
        $heroStats5v5 = $stats5v5['heroStats'];
        $recentCombats5v5 = $stats5v5['recentCombats'];
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?php echo $s5v5['total']; ?></div>
                <div class="label">Combats</div>
            </div>
            <div class="stat-card wins">
                <div class="value"><?php echo $s5v5['wins']; ?></div>
                <div class="label">Victoires</div>
            </div>
            <div class="stat-card losses">
                <div class="value"><?php echo $s5v5['losses']; ?></div>
                <div class="label">Défaites</div>
            </div>
            <div class="stat-card ratio">
                <div class="value"><?php echo $s5v5['ratio']; ?>%</div>
                <div class="label">Ratio</div>
            </div>
        </div>
        
        <!-- Personnages les plus joués -->
        <div class="section">
            <h2>Personnages les plus joués</h2>
            <?php if (empty($mostPlayed5v5)): ?>
                <div class="empty-state">
                    <div class="icon">⚔️</div>
                    <p>Aucun combat 5v5 enregistré. Lancez-vous dans l'arène avec votre équipe !</p>
                    <a href="game/multiplayer_5v5_setup.php" class="btn-primary">Jouer en 5v5</a>
                </div>
            <?php else: ?>
                <div class="hero-list">
                    <?php foreach ($mostPlayed5v5 as $i => $hero): 
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
            <h2>Historique récent</h2>
            <?php if (empty($recentCombats5v5)): ?>
                <div class="empty-state">
                    <div class="icon"></div>
                    <p>Aucun historique disponible.</p>
                </div>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Équipe</th>
                            <th>Adversaire</th>
                            <th>Résultat</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCombats5v5 as $combat): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($combat['team_name'] ?? 'Mon équipe'); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($combat['opponent_name'] ?? '-'); ?></td>
                                <td class="<?php echo $combat['victory'] ? 'result-victory' : 'result-defeat'; ?>">
                                    <?php echo $combat['victory'] ? '✓ Victoire' : '✗ Défaite'; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($combat['played_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Stats par héros -->
        <?php if (!empty($heroStats5v5)): ?>
        <div class="section">
            <h2>Statistiques par héros</h2>
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
                    <?php foreach ($heroStats5v5 as $stat): 
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

    <!-- TAB 3: Gestion des Équipes -->
    <div id="teams-tab" class="tab-content">
        <?php include COMPONENTS_PATH . '/team-manager.php'; ?>
    </div>
</div>

<script src="../public/js/selection-tooltip.js"></script>
<script src="../public/js/account.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initAccountPage({
        postTabToSelect: '<?php echo isset($_POST['action']) ? ($_POST['action'] === 'add_hero_to_team' ? 'teams' : '') : ''; ?>'
    });
});
</script>

<?php 
$showBackLink = true;
require_once INCLUDES_PATH . '/footer.php'; 
?>
