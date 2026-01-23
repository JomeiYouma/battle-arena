<?php
/**
 * SIMULATION - Simule des combats automatiques pour statistiques d'équilibrage
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

// Charger les personnages
$personnages = json_decode(file_get_contents('heros.json'), true);

// Variables pour les résultats
$results = null;
$combatsPerMatchup = 10; // Valeur par défaut

// --- LANCER LA SIMULATION ---
if (isset($_POST['simulate'])) {
    $combatsPerMatchup = (int) $_POST['combats_count'];
    $combatsPerMatchup = max(1, min(1000, $combatsPerMatchup)); // Limiter entre 1 et 1000
    
    $results = runSimulation($personnages, $combatsPerMatchup);
}

/**
 * Crée une instance fraîche d'un personnage à partir de ses stats
 */
function createFighter(array $stats): Personnage {
    $class = $stats['type'];
    return new $class(
        $stats['pv'], 
        $stats['atk'], 
        $stats['name'], 
        $stats['def'] ?? 5, 
        $stats['speed'] ?? 10
    );
}

/**
 * Lance la simulation complète
 */
function runSimulation(array $personnages, int $combatsPerMatchup): array {
    $stats = [];
    $matchups = [];
    
    // Initialiser les stats pour chaque personnage
    foreach ($personnages as $p) {
        $stats[$p['id']] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'type' => $p['type'],
            'wins' => 0,
            'losses' => 0,
            'battles' => 0,
            'avgTurns' => 0,
            'totalTurns' => 0,
            'matchups' => []
        ];
    }
    
    // Faire combattre chaque paire de personnages
    $count = count($personnages);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $p1 = $personnages[$i];
            $p2 = $personnages[$j];
            
            $p1Wins = 0;
            $p2Wins = 0;
            $totalTurns = 0;
            
            // Lancer N combats pour cette paire
            for ($n = 0; $n < $combatsPerMatchup; $n++) {
                // Créer des instances fraîches à chaque combat
                $fighter1 = createFighter($p1);
                $fighter2 = createFighter($p2);
                
                $combat = new AutoCombat($fighter1, $fighter2);
                $winner = $combat->run();
                $totalTurns += $combat->getTurns();
                
                if ($winner->getName() === $p1['name']) {
                    $p1Wins++;
                } else {
                    $p2Wins++;
                }
            }
            
            // Mettre à jour les stats globales
            $stats[$p1['id']]['wins'] += $p1Wins;
            $stats[$p1['id']]['losses'] += $p2Wins;
            $stats[$p1['id']]['battles'] += $combatsPerMatchup;
            $stats[$p1['id']]['totalTurns'] += $totalTurns;
            
            $stats[$p2['id']]['wins'] += $p2Wins;
            $stats[$p2['id']]['losses'] += $p1Wins;
            $stats[$p2['id']]['battles'] += $combatsPerMatchup;
            $stats[$p2['id']]['totalTurns'] += $totalTurns;
            
            // Stocker les résultats du matchup
            $stats[$p1['id']]['matchups'][$p2['id']] = [
                'opponent' => $p2['name'],
                'wins' => $p1Wins,
                'losses' => $p2Wins,
                'winRate' => round(($p1Wins / $combatsPerMatchup) * 100, 1)
            ];
            
            $stats[$p2['id']]['matchups'][$p1['id']] = [
                'opponent' => $p1['name'],
                'wins' => $p2Wins,
                'losses' => $p1Wins,
                'winRate' => round(($p2Wins / $combatsPerMatchup) * 100, 1)
            ];
        }
    }
    
    // Calculer les moyennes et ratios
    foreach ($stats as $id => &$s) {
        if ($s['battles'] > 0) {
            $s['winRate'] = round(($s['wins'] / $s['battles']) * 100, 1);
            $s['avgTurns'] = round($s['totalTurns'] / $s['battles'], 1);
        } else {
            $s['winRate'] = 0;
            $s['avgTurns'] = 0;
        }
    }
    
    // Trier par winRate décroissant
    uasort($stats, fn($a, $b) => $b['winRate'] <=> $a['winRate']);
    
    return $stats;
}

/**
 * Retourne une classe CSS selon le taux de victoire
 */
function getBalanceClass(float $winRate): string {
    if ($winRate >= 60) return 'overpowered';
    if ($winRate >= 55) return 'slightly-strong';
    if ($winRate <= 40) return 'underpowered';
    if ($winRate <= 45) return 'slightly-weak';
    return 'balanced';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulation - Horus Battle Arena</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <h1>Simulateur de Matchs</h1>

    <div class="simulation-container">
        
        <!-- FORMULAIRE DE SIMULATION -->
        <form method="POST" class="simulation-form">
            <div class="form-group">
                <label for="combats_count">Nombre de combats par matchup :</label>
                <input type="number" 
                       name="combats_count" 
                       id="combats_count" 
                       value="<?php echo $combatsPerMatchup; ?>" 
                       min="1" 
                       max="1000" 
                       placeholder="Ex: 100">
            </div>
            
            <div class="form-info">
                <?php 
                $nbPersonnages = count($personnages);
                $nbMatchups = ($nbPersonnages * ($nbPersonnages - 1)) / 2;
                ?>
                <p>📊 <?php echo $nbPersonnages; ?> personnages = <?php echo $nbMatchups; ?> matchups</p>
                <p>⚔️ Total : <strong id="totalCombats"><?php echo $nbMatchups * $combatsPerMatchup; ?></strong> combats simulés</p>
            </div>
            
            <button type="submit" name="simulate" class="action-btn simulate-btn">
                🎲 Lancer la Simulation
            </button>
        </form>

        <?php if ($results): ?>
        
        <!-- RÉSULTATS -->
        <div class="simulation-results">
            <h2>Résultats de la Simulation</h2>
            <p class="results-summary">
                <?php echo array_sum(array_column($results, 'battles')) / 2; ?> combats simulés 
                (<?php echo $combatsPerMatchup; ?> par matchup)
            </p>
            
            <!-- LÉGENDE -->
            <div class="balance-legend">
                <span class="legend-item overpowered">🔴≥ 60%</span>
                <span class="legend-item slightly-strong">🟠 55-60%</span>
                <span class="legend-item balanced">🟢 45-55%</span>
                <span class="legend-item slightly-weak">🟡 40-45%</span>
                <span class="legend-item underpowered">🔵≤ 40%</span>
            </div>
            
            <!-- TABLEAU DES RÉSULTATS -->
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Personnage</th>
                        <th>Type</th>
                        <th>Victoires</th>
                        <th>Défaites</th>
                        <th>Win Rate</th>
                        <th>Moy. Tours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; foreach ($results as $id => $stat): ?>
                    <tr class="<?php echo getBalanceClass($stat['winRate']); ?>">
                        <td class="rank">#<?php echo $rank++; ?></td>
                        <td class="name">
                            <strong><?php echo htmlspecialchars($stat['name']); ?></strong>
                        </td>
                        <td><span class="type-badge"><?php echo $stat['type']; ?></span></td>
                        <td class="wins"><?php echo $stat['wins']; ?></td>
                        <td class="losses"><?php echo $stat['losses']; ?></td>
                        <td class="winrate">
                            <div class="winrate-bar-container">
                                <div class="winrate-bar" style="width: <?php echo $stat['winRate']; ?>%"></div>
                                <span class="winrate-text"><?php echo $stat['winRate']; ?>%</span>
                            </div>
                        </td>
                        <td class="avg-turns"><?php echo $stat['avgTurns']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- MATRICE DE MATCHUPS -->
            <h3>Matrice des Matchups</h3>
            <p class="matrix-help">Taux de victoire du personnage en ligne contre celui en colonne</p>
            
            <div class="matchup-matrix-wrapper">
                <table class="matchup-matrix">
                    <thead>
                        <tr>
                            <th></th>
                            <?php foreach ($personnages as $p): ?>
                            <th title="<?php echo $p['name']; ?>"><?php echo substr($p['name'], 0, 8); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personnages as $p1): ?>
                        <tr>
                            <th title="<?php echo $p1['name']; ?>"><?php echo $p1['name']; ?></th>
                            <?php foreach ($personnages as $p2): ?>
                                <?php if ($p1['id'] === $p2['id']): ?>
                                    <td class="self">-</td>
                                <?php else: ?>
                                    <?php 
                                    $matchup = $results[$p1['id']]['matchups'][$p2['id']] ?? null;
                                    $wr = $matchup ? $matchup['winRate'] : 0;
                                    $class = $wr >= 60 ? 'strong-win' : ($wr >= 50 ? 'slight-win' : ($wr >= 40 ? 'slight-loss' : 'strong-loss'));
                                    ?>
                                    <td class="<?php echo $class; ?>" title="<?php echo $p1['name']; ?> vs <?php echo $p2['name']; ?>: <?php echo $wr; ?>%">
                                        <?php echo $wr; ?>%
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php endif; ?>
        
        <br><br>
        <a href="index.php" class="back-link">← Retour au menu</a>
    </div>

    <script>
        // Mise à jour du total de combats en temps réel
        document.getElementById('combats_count').addEventListener('input', function() {
            const combats = parseInt(this.value) || 0;
            const matchups = <?php echo $nbMatchups; ?>;
            document.getElementById('totalCombats').textContent = combats * matchups;
        });
    </script>

</body>
</html>
