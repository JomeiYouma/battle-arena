<?php
/**
 * SIMULATION - Simule des combats automatiques pour statistiques d'équilibrage
 */

// Autoloader centralisé
require_once __DIR__ . '/../../includes/autoload.php';

// Charger les personnages depuis la BDD
require_once COMPONENTS_PATH . '/selection-utils.php';
$personnages = getHeroesList();

// Liste des bénédictions disponibles
$blessingsList = [
    ['id' => 'none', 'name' => 'Aucune', 'emoji' => '❌'],
    ['id' => 'WheelOfFortune', 'name' => 'Roue de Fortune', 'emoji' => '🎰'],
    ['id' => 'LoversCharm', 'name' => 'Charmes Amoureux', 'emoji' => '💘'],
    ['id' => 'JudgmentOfDamned', 'name' => 'Jugement des Maudits', 'emoji' => '⚖️'],
    ['id' => 'StrengthFavor', 'name' => 'Faveur de Force', 'emoji' => '💪'],
    ['id' => 'MoonCall', 'name' => 'Appel de la Lune', 'emoji' => '🌙'],
    ['id' => 'WatchTower', 'name' => 'La Tour de Garde', 'emoji' => '🏰'],
    ['id' => 'RaChariot', 'name' => 'Chariot de Ra', 'emoji' => '☀️'],
    ['id' => 'HangedMan', 'name' => 'Corde du Pendu', 'emoji' => '🪢']
];

// Variables pour les résultats
$results = null;
$blessingResults = null;
$combatsPerMatchup = 10; // Valeur par défaut
$simulationType = 'classic'; // classic, blessing_single, blessing_all
$selectedHero = 'all';
$selectedBlessing = 'all';
$opponentBlessingMode = 'same'; // same, all

// --- LANCER LA SIMULATION ---
if (isset($_POST['simulate'])) {
    set_time_limit(0); // Pas de limite de temps
    $combatsPerMatchup = (int) $_POST['combats_count'];
    $combatsPerMatchup = max(1, min(1000, $combatsPerMatchup));
    
    $results = runSimulation($personnages, $combatsPerMatchup);
}

// --- SIMULATION AVEC BÉNÉDICTIONS ---
if (isset($_POST['simulate_blessing'])) {
    set_time_limit(0); // Pas de limite de temps
    $combatsPerMatchup = (int) $_POST['combats_count_blessing'];
    $combatsPerMatchup = max(1, min(500, $combatsPerMatchup));
    $selectedHero = $_POST['hero_select'] ?? 'all';
    $selectedBlessing = $_POST['blessing_select'] ?? 'all';
    $opponentBlessingMode = $_POST['opponent_blessing_mode'] ?? 'same';
    
    $blessingResults = runBlessingSimulation($personnages, $blessingsList, $combatsPerMatchup, $selectedHero, $selectedBlessing, $opponentBlessingMode);
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

/**
 * Crée une instance de bénédiction à partir de son ID
 */
function createBlessing(string $blessingId): ?Blessing {
    if ($blessingId === 'none') return null;
    
    $blessingFile = CORE_PATH . '/blessings/' . $blessingId . '.php';
    if (file_exists($blessingFile)) {
        require_once $blessingFile;
        return new $blessingId();
    }
    return null;
}

/**
 * Crée un combattant avec une bénédiction optionnelle
 */
function createFighterWithBlessing(array $stats, ?string $blessingId = null): Personnage {
    $fighter = createFighter($stats);
    if ($blessingId && $blessingId !== 'none') {
        $blessing = createBlessing($blessingId);
        if ($blessing) {
            $fighter->addBlessing($blessing);
        }
    }
    return $fighter;
}

/**
 * Lance la simulation avec bénédictions
 */
function runBlessingSimulation(array $personnages, array $blessingsList, int $combatsPerMatchup, string $selectedHero, string $selectedBlessing, string $opponentBlessingMode): array {
    $results = [];
    
    // Filtrer les bénédictions (exclure 'none' sauf si sélectionné explicitement)
    $activeBlessings = array_filter($blessingsList, fn($b) => $b['id'] !== 'none');
    
    // Déterminer les héros à tester
    $herosToTest = ($selectedHero === 'all') 
        ? $personnages 
        : array_filter($personnages, fn($p) => $p['id'] === $selectedHero);
    
    // Déterminer les bénédictions à tester pour le héros
    $blessingsToTest = ($selectedBlessing === 'all') 
        ? $activeBlessings 
        : array_filter($activeBlessings, fn($b) => $b['id'] === $selectedBlessing);
    
    foreach ($herosToTest as $hero) {
        foreach ($blessingsToTest as $heroBlessing) {
            // Clé unique pour ce héros+bénédiction
            $key = $hero['id'] . '_' . $heroBlessing['id'];
            
            $results[$key] = [
                'heroId' => $hero['id'],
                'heroName' => $hero['name'],
                'heroType' => $hero['type'],
                'blessingId' => $heroBlessing['id'],
                'blessingName' => $heroBlessing['name'],
                'blessingEmoji' => $heroBlessing['emoji'],
                'wins' => 0,
                'losses' => 0,
                'battles' => 0,
                'totalTurns' => 0,
                'matchups' => []
            ];
            
            // Autres héros comme adversaires
            foreach ($personnages as $opponent) {
                if ($opponent['id'] === $hero['id']) continue;
                
                // Déterminer les bénédictions adverses à tester
                if ($opponentBlessingMode === 'same') {
                    // Même bénédiction pour l'adversaire
                    $opponentBlessings = [$heroBlessing];
                } else {
                    // Toutes les bénédictions pour l'adversaire
                    $opponentBlessings = $activeBlessings;
                }
                
                foreach ($opponentBlessings as $oppBlessing) {
                    $matchupKey = $opponent['id'] . '_' . $oppBlessing['id'];
                    
                    $heroWins = 0;
                    $oppWins = 0;
                    $totalTurns = 0;
                    
                    // Lancer les combats
                    for ($n = 0; $n < $combatsPerMatchup; $n++) {
                        $fighter1 = createFighterWithBlessing($hero, $heroBlessing['id']);
                        $fighter2 = createFighterWithBlessing($opponent, $oppBlessing['id']);
                        
                        $combat = new AutoCombat($fighter1, $fighter2);
                        $winner = $combat->run();
                        $totalTurns += $combat->getTurns();
                        
                        if ($winner->getName() === $hero['name']) {
                            $heroWins++;
                        } else {
                            $oppWins++;
                        }
                    }
                    
                    // Stocker les résultats
                    $results[$key]['wins'] += $heroWins;
                    $results[$key]['losses'] += $oppWins;
                    $results[$key]['battles'] += $combatsPerMatchup;
                    $results[$key]['totalTurns'] += $totalTurns;
                    
                    $results[$key]['matchups'][$matchupKey] = [
                        'opponentName' => $opponent['name'],
                        'opponentType' => $opponent['type'],
                        'opponentBlessing' => $oppBlessing['name'],
                        'opponentBlessingEmoji' => $oppBlessing['emoji'],
                        'wins' => $heroWins,
                        'losses' => $oppWins,
                        'winRate' => round(($heroWins / $combatsPerMatchup) * 100, 1)
                    ];
                }
            }
        }
    }
    
    // Calculer les moyennes
    foreach ($results as $key => &$r) {
        if ($r['battles'] > 0) {
            $r['winRate'] = round(($r['wins'] / $r['battles']) * 100, 1);
            $r['avgTurns'] = round($r['totalTurns'] / $r['battles'], 1);
        } else {
            $r['winRate'] = 0;
            $r['avgTurns'] = 0;
        }
    }
    
    // Trier par winRate décroissant
    uasort($results, fn($a, $b) => $b['winRate'] <=> $a['winRate']);
    
    return $results;
}

// Configuration du header
$pageTitle = 'Simulation - Horus Battle Arena';
$extraCss = ['simulation'];
$showUserBadge = false;
$showMainTitle = true;
require_once INCLUDES_PATH . '/header.php';
?>

    <!-- LOADING OVERLAY -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Simulation en cours...</div>
        <div class="loading-combat-count" id="loadingCombatCount">0 combats</div>
        <div class="loading-time-estimate" id="loadingTimeEstimate">Temps estimé : ~0s</div>
        <div class="loading-subtext" id="loadingSubtext">Préparation des combats</div>
        <div class="loading-tips">Conseil : Réduisez le nombre de combats pour des résultats plus rapides</div>
    </div>

    <!-- <h1>Simulateur de Matchs</h1> -->

    <div class="simulation-container">
        
        <!-- ONGLETS -->
        <div class="simulation-tabs">
            <button type="button" class="tab-btn active" data-tab="classic">Simulation Classique</button>
            <button type="button" class="tab-btn" data-tab="blessing">Simulation Bénédictions</button>
        </div>
        
        <!-- TAB: SIMULATION CLASSIQUE -->
        <div id="tab-classic" class="tab-content active">
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
                    <p><?php echo $nbPersonnages; ?> personnages = <?php echo $nbMatchups; ?> matchups</p>
                    <p>Total : <strong id="totalCombats"><?php echo $nbMatchups * $combatsPerMatchup; ?></strong> combats simulés</p>
                    <p class="time-estimate-preview">Temps estimé : <strong id="timeEstimateClassic">~0s</strong></p>
                </div>
                
                <button type="submit" name="simulate" class="action-btn simulate-btn">
                    Lancer la Simulation Classique
                </button>
            </form>
        </div>
        
        <!-- TAB: SIMULATION BÉNÉDICTIONS -->
        <div id="tab-blessing" class="tab-content">
            <form method="POST" class="simulation-form">
                <h3 class="simulation-section-title">Test d'équilibrage des Bénédictions</h3>
                
                <div class="blessing-form-grid">
                    <div class="form-group">
                        <label for="hero_select">Personnage à tester :</label>
                        <select name="hero_select" id="hero_select">
                            <option value="all" <?php echo $selectedHero === 'all' ? 'selected' : ''; ?>>Tous les personnages</option>
                            <?php foreach ($personnages as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $selectedHero === $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['type']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="blessing_select">Bénédiction du héros :</label>
                        <select name="blessing_select" id="blessing_select">
                            <option value="all" <?php echo $selectedBlessing === 'all' ? 'selected' : ''; ?>>Toutes les bénédictions</option>
                            <?php foreach ($blessingsList as $b): if ($b['id'] === 'none') continue; ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $selectedBlessing === $b['id'] ? 'selected' : ''; ?>>
                                <?php echo $b['emoji']; ?> <?php echo $b['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="opponent_blessing_mode">Bénédiction adversaires :</label>
                        <select name="opponent_blessing_mode" id="opponent_blessing_mode">
                            <option value="same" <?php echo $opponentBlessingMode === 'same' ? 'selected' : ''; ?>>
                                Même bénédiction (miroir)
                            </option>
                            <option value="all" <?php echo $opponentBlessingMode === 'all' ? 'selected' : ''; ?>>
                                Toutes les bénédictions
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="combats_count_blessing">Combats par matchup :</label>
                        <input type="number" 
                               name="combats_count_blessing" 
                               id="combats_count_blessing" 
                               value="<?php echo $combatsPerMatchup; ?>" 
                               min="1" 
                               max="500" 
                               placeholder="Ex: 10">
                    </div>
                </div>
                
                <div class="form-info">
                    <p><strong>Mode Miroir</strong> : Teste chaque personnage contre tous les autres avec la même bénédiction</p>
                    <p><strong>Toutes bénédictions</strong> : Teste contre toutes les combinaisons adverses (plus long)</p>
                </div>
                
                <div class="form-info estimate-box">
                    <p>Total : <strong id="totalCombatsBlessing">0</strong> combats simulés</p>
                    <p class="time-estimate-preview">Temps estimé : <strong id="timeEstimateBlessing">~0s</strong></p>
                </div>
                
                <button type="submit" name="simulate_blessing" class="action-btn simulate-btn">
                    Lancer la Simulation Bénédictions
                </button>
            </form>
        </div>

        <?php if ($results): ?>
        
        <!-- RÉSULTATS -->
        <div class="simulation-results">
            <h2>Résultats de la Simulation</h2>
            <p class="results-summary">
                <?php echo array_sum(array_column($results, 'battles')) / 2; ?> combats simulés 
                (<?php echo $combatsPerMatchup; ?> par matchup)
            </p>
            
            <!-- SECTION: CLASSEMENT GÉNÉRAL -->
            <details class="result-section" open>
                <summary>Classement Général</summary>
                
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
            </details>
            
            <!-- SECTION: MATRICE DE MATCHUPS -->
            <details class="result-section">
                <summary>Matrice des Matchups</summary>
                <p class="section-subtitle">Taux de victoire du personnage en ligne contre celui en colonne</p>
                
                <div class="matchup-matrix-wrapper">
                    <table class="matchup-matrix">
                        <thead>
                            <tr>
                                <th></th>
                                <?php foreach ($personnages as $p): ?>
                                <?php $shortName = explode(' ', $p['name'])[0]; ?>
                                <th title="<?php echo $p['name']; ?>"><?php echo $shortName; ?></th>
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
            </details>
        </div>
        
        <?php endif; ?>
        
        <?php if ($blessingResults): ?>
        
        <!-- RÉSULTATS BÉNÉDICTIONS -->
        <div class="simulation-results">
            <h2>Résultats Simulation Bénédictions</h2>
            <p class="results-summary">
                <?php echo array_sum(array_column($blessingResults, 'battles')); ?> combats simulés 
                (<?php echo $combatsPerMatchup; ?> par matchup)
            </p>
            
            <!-- TABLEAU RÉCAPITULATIF -->
            <details class="result-section" open>
                <summary>Tableau Récapitulatif</summary>
                
                <!-- LÉGENDE -->
                <div class="balance-legend">
                    <span class="legend-item overpowered">🔴 ≥60%</span>
                    <span class="legend-item slightly-strong">🟠 55-60%</span>
                    <span class="legend-item balanced">🟢 45-55%</span>
                    <span class="legend-item slightly-weak">🟡 40-45%</span>
                    <span class="legend-item underpowered">🔵 ≤40%</span>
                </div>
                
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Personnage</th>
                            <th>Bénédiction</th>
                            <th>Victoires</th>
                            <th>Défaites</th>
                            <th>Win Rate</th>
                            <th>Moy. Tours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($blessingResults as $key => $stat): ?>
                        <tr class="<?php echo getBalanceClass($stat['winRate']); ?>">
                            <td class="rank">#<?php echo $rank++; ?></td>
                            <td class="name">
                                <strong><?php echo htmlspecialchars($stat['heroName']); ?></strong>
                                <span class="type-badge"><?php echo $stat['heroType']; ?></span>
                            </td>
                            <td>
                                <span class="blessing-badge"><?php echo $stat['blessingEmoji']; ?> <?php echo $stat['blessingName']; ?></span>
                            </td>
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
            </details>
            
            <!-- PERFORMANCES PAR BÉNÉDICTION -->
            <details class="result-section">
                <summary>Performances par Bénédiction</summary>
                <?php
                // Agréger les stats par bénédiction
                $blessingStats = [];
                foreach ($blessingResults as $key => $stat) {
                    $bId = $stat['blessingId'];
                    if (!isset($blessingStats[$bId])) {
                        $blessingStats[$bId] = [
                            'id' => $bId,
                            'name' => $stat['blessingName'],
                            'emoji' => $stat['blessingEmoji'],
                            'wins' => 0,
                            'losses' => 0,
                            'battles' => 0,
                            'totalTurns' => 0,
                            'heroCount' => 0
                        ];
                    }
                    $blessingStats[$bId]['wins'] += $stat['wins'];
                    $blessingStats[$bId]['losses'] += $stat['losses'];
                    $blessingStats[$bId]['battles'] += $stat['battles'];
                    $blessingStats[$bId]['totalTurns'] += $stat['totalTurns'];
                    $blessingStats[$bId]['heroCount']++;
                }
                
                // Calculer les winrates et trier
                foreach ($blessingStats as $bId => &$bs) {
                    $bs['winRate'] = $bs['battles'] > 0 ? round(($bs['wins'] / $bs['battles']) * 100, 1) : 0;
                    $bs['avgTurns'] = $bs['battles'] > 0 ? round($bs['totalTurns'] / $bs['battles'], 1) : 0;
                }
                uasort($blessingStats, fn($a, $b) => $b['winRate'] <=> $a['winRate']);
                ?>
                
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Bénédiction</th>
                            <th>Héros testés</th>
                            <th>Victoires</th>
                            <th>Défaites</th>
                            <th>Win Rate</th>
                            <th>Moy. Tours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($blessingStats as $bId => $bs): ?>
                        <tr class="<?php echo getBalanceClass($bs['winRate']); ?>">
                            <td class="rank">#<?php echo $rank++; ?></td>
                            <td>
                                <span class="blessing-badge blessing-badge-large"><?php echo $bs['emoji']; ?> <?php echo $bs['name']; ?></span>
                            </td>
                            <td><?php echo $bs['heroCount']; ?></td>
                            <td class="wins"><?php echo $bs['wins']; ?></td>
                            <td class="losses"><?php echo $bs['losses']; ?></td>
                            <td class="winrate">
                                <div class="winrate-bar-container">
                                    <div class="winrate-bar" style="width: <?php echo $bs['winRate']; ?>%"></div>
                                    <span class="winrate-text"><?php echo $bs['winRate']; ?>%</span>
                                </div>
                            </td>
                            <td class="avg-turns"><?php echo $bs['avgTurns']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
            
            <!-- PERFORMANCES PAR PERSONNAGE -->
            <details class="result-section">
                <summary>Performances par Personnage</summary>
                <?php
                // Agréger les stats par personnage
                $heroStats = [];
                foreach ($blessingResults as $key => $stat) {
                    $hId = $stat['heroId'];
                    if (!isset($heroStats[$hId])) {
                        $heroStats[$hId] = [
                            'id' => $hId,
                            'name' => $stat['heroName'],
                            'type' => $stat['heroType'],
                            'wins' => 0,
                            'losses' => 0,
                            'battles' => 0,
                            'totalTurns' => 0,
                            'blessingCount' => 0
                        ];
                    }
                    $heroStats[$hId]['wins'] += $stat['wins'];
                    $heroStats[$hId]['losses'] += $stat['losses'];
                    $heroStats[$hId]['battles'] += $stat['battles'];
                    $heroStats[$hId]['totalTurns'] += $stat['totalTurns'];
                    $heroStats[$hId]['blessingCount']++;
                }
                
                // Calculer les winrates et trier
                foreach ($heroStats as $hId => &$hs) {
                    $hs['winRate'] = $hs['battles'] > 0 ? round(($hs['wins'] / $hs['battles']) * 100, 1) : 0;
                    $hs['avgTurns'] = $hs['battles'] > 0 ? round($hs['totalTurns'] / $hs['battles'], 1) : 0;
                }
                uasort($heroStats, fn($a, $b) => $b['winRate'] <=> $a['winRate']);
                ?>
                
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Personnage</th>
                            <th>Type</th>
                            <th>Bénéd. testées</th>
                            <th>Victoires</th>
                            <th>Défaites</th>
                            <th>Win Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($heroStats as $hId => $hs): ?>
                        <tr class="<?php echo getBalanceClass($hs['winRate']); ?>">
                            <td class="rank">#<?php echo $rank++; ?></td>
                            <td class="name"><strong><?php echo htmlspecialchars($hs['name']); ?></strong></td>
                            <td><span class="type-badge"><?php echo $hs['type']; ?></span></td>
                            <td><?php echo $hs['blessingCount']; ?></td>
                            <td class="wins"><?php echo $hs['wins']; ?></td>
                            <td class="losses"><?php echo $hs['losses']; ?></td>
                            <td class="winrate">
                                <div class="winrate-bar-container">
                                    <div class="winrate-bar" style="width: <?php echo $hs['winRate']; ?>%"></div>
                                    <span class="winrate-text"><?php echo $hs['winRate']; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        </div>
        
        <?php endif; ?>
        
    </div>

    <script src="../../public/js/simulation.js"></script>
    <script>
        initSimulation({
            nbPersonnages: <?php echo count($personnages); ?>,
            nbBlessings: <?php echo count(array_filter($blessingsList, fn($b) => $b['id'] !== 'none')); ?>,
            nbMatchups: <?php echo $nbMatchups; ?>,
            hasBlessingResults: <?php echo $blessingResults ? 'true' : 'false'; ?>
        });
    </script>

<?php 
$showBackLink = true;
require_once INCLUDES_PATH . '/footer.php'; 
?>
