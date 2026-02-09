<!-- Tooltip system -->
<div id="customTooltip" class="custom-tooltip"></div>

<div class="account-container">
    
    <div class="account-header">
        <div>
            <h1>Mon Compte</h1>
            <span class="username"><?= View::e($username) ?></span>
        </div>
        <form method="POST" action="<?= View::url('/logout') ?>">
            <button type="submit" class="logout-btn">Déconnexion</button>
        </form>
    </div>
    
    <!-- Système de Tabs -->
    <div class="tabs-navigation">
        <button class="tab-button active" onclick="switchTab('stats')">Statistiques 1v1</button>
        <button class="tab-button" onclick="switchTab('stats5v5')">Statistiques 5v5</button>
        <button class="tab-button" onclick="switchTab('teams')">Mes Équipes</button>
        <button class="tab-button" onclick="switchTab('leaderboard')">🏆 Leaderboard</button>
    </div>

    <!-- TAB 1: Statistiques 1v1 -->
    <div id="stats-tab" class="tab-content active">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?= $globalStats['total'] ?></div>
                <div class="label">Combats</div>
            </div>
            <div class="stat-card wins">
                <div class="value"><?= $globalStats['wins'] ?></div>
                <div class="label">Victoires</div>
            </div>
            <div class="stat-card losses">
                <div class="value"><?= $globalStats['losses'] ?></div>
                <div class="label">Défaites</div>
            </div>
            <div class="stat-card ratio">
                <div class="value"><?= $globalStats['ratio'] ?>%</div>
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
                            <span class="rank"><?= $rankEmoji ?></span>
                            <span class="name"><?= View::e($heroNames[$hero['hero_id']] ?? $hero['hero_id']) ?></span>
                            <span class="games"><?= $hero['games'] ?> parties</span>
                            <span class="winrate"><?= $winrate ?>% winrate</span>
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
                                <td><?= View::e($heroNames[$combat['hero_id']] ?? $combat['hero_id']) ?></td>
                                <td><?= View::e($heroNames[$combat['opponent_hero_id']] ?? $combat['opponent_hero_id'] ?? '-') ?></td>
                                <td class="<?= $combat['victory'] ? 'result-victory' : 'result-defeat' ?>">
                                    <?= $combat['victory'] ? '✓ Victoire' : '✗ Défaite' ?>
                                </td>
                                <td>
                                    <span class="mode-badge <?= $combat['game_mode'] ?>">
                                        <?= $combat['game_mode'] === 'multi' ? 'Multi' : 'Solo' ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($combat['played_at'])) ?></td>
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
                            <td><?= View::e($heroNames[$stat['hero_id']] ?? $stat['hero_id']) ?></td>
                            <td><?= $stat['games'] ?></td>
                            <td class="result-victory"><?= $stat['wins'] ?></td>
                            <td class="result-defeat"><?= $stat['losses'] ?></td>
                            <td><?= $winrate ?>%</td>
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
                <div class="value"><?= $s5v5['total'] ?></div>
                <div class="label">Combats</div>
            </div>
            <div class="stat-card wins">
                <div class="value"><?= $s5v5['wins'] ?></div>
                <div class="label">Victoires</div>
            </div>
            <div class="stat-card losses">
                <div class="value"><?= $s5v5['losses'] ?></div>
                <div class="label">Défaites</div>
            </div>
            <div class="stat-card ratio">
                <div class="value"><?= $s5v5['ratio'] ?>%</div>
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
                    <a href="<?= View::url('/game/5v5/setup') ?>" class="btn-primary">Jouer en 5v5</a>
                </div>
            <?php else: ?>
                <div class="hero-list">
                    <?php foreach ($mostPlayed5v5 as $i => $hero): 
                        $winrate = $hero['games'] > 0 ? round(($hero['wins'] / $hero['games']) * 100) : 0;
                        $rankEmoji = ['🥇', '🥈', '🥉'][$i] ?? ($i + 1);
                    ?>
                        <div class="hero-item">
                            <span class="rank"><?= $rankEmoji ?></span>
                            <span class="name"><?= View::e($heroNames[$hero['hero_id']] ?? $hero['hero_id']) ?></span>
                            <span class="games"><?= $hero['games'] ?> parties</span>
                            <span class="winrate"><?= $winrate ?>% winrate</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Historique récent 5v5 -->
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
                                    <strong><?= View::e($combat['team_name'] ?? 'Mon équipe') ?></strong>
                                </td>
                                <td><?= View::e($combat['opponent_name'] ?? '-') ?></td>
                                <td class="<?= $combat['victory'] ? 'result-victory' : 'result-defeat' ?>">
                                    <?= $combat['victory'] ? '✓ Victoire' : '✗ Défaite' ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($combat['played_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Stats par héros 5v5 -->
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
                            <td><?= View::e($heroNames[$stat['hero_id']] ?? $stat['hero_id']) ?></td>
                            <td><?= $stat['games'] ?></td>
                            <td class="result-victory"><?= $stat['wins'] ?></td>
                            <td class="result-defeat"><?= $stat['losses'] ?></td>
                            <td><?= $winrate ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB 3: Gestion des Équipes -->
    <div id="teams-tab" class="tab-content">
        <?php 
        // Le composant team-manager sera migré plus tard en view partielle
        // Pour l'instant, on inclut l'existant avec les variables nécessaires
        $teamManager = new TeamManager();
        include dirname(dirname(__DIR__)) . '/components/team-manager.php'; 
        ?>
    </div>

    <!-- TAB 4: Leaderboard -->
    <div id="leaderboard-tab" class="tab-content">
        <div class="section">
            <h2>🏆 Classement des Joueurs</h2>
            <?php if (empty($leaderboard)): ?>
                <div class="empty-state">
                    <div class="icon">🏆</div>
                    <p>Aucun joueur classé pour le moment.</p>
                </div>
            <?php else: ?>
                <table class="history-table leaderboard-table">
                    <thead>
                        <tr>
                            <th class="rank-col">#</th>
                            <th>Joueur</th>
                            <th>Victoires</th>
                            <th>Défaites</th>
                            <th>Winrate</th>
                            <th>Héros Principal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard as $i => $player): 
                            $rank = $i + 1;
                            $rankEmoji = ['🥇', '🥈', '🥉'][$i] ?? $rank;
                            $isCurrentUser = ($player['id'] == $userId);
                            $heroName = $player['main_hero'] ? ($heroNames[$player['main_hero']] ?? $player['main_hero']) : '-';
                        ?>
                            <tr class="<?= $isCurrentUser ? 'current-user-row' : '' ?> <?= $rank <= 3 ? 'top-3' : '' ?>">
                                <td class="rank-cell">
                                    <span class="rank-badge rank-<?= min($rank, 4) ?>"><?= $rankEmoji ?></span>
                                </td>
                                <td class="player-cell">
                                    <?= View::e($player['username']) ?>
                                    <?php if ($isCurrentUser): ?><span class="you-badge">Vous</span><?php endif; ?>
                                </td>
                                <td class="result-victory"><?= $player['wins'] ?></td>
                                <td class="result-defeat"><?= $player['losses'] ?></td>
                                <td class="winrate-cell">
                                    <span class="winrate-value"><?= $player['winrate'] ?>%</span>
                                    <div class="winrate-bar">
                                        <div class="winrate-fill" style="width: <?= min(100, $player['winrate']) ?>%"></div>
                                    </div>
                                </td>
                                <td class="hero-cell"><?= View::e($heroName) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= View::asset('js/selection-tooltip.js') ?>"></script>
<script src="<?= View::asset('js/account.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initAccountPage({
        postTabToSelect: '<?= View::e($postTabToSelect) ?>'
    });
});
</script>
