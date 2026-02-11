<?php
/**
 * GAMECONTROLLER - Gestion des modes de jeu
 */

class GameController extends Controller {
    
    /**
     * Mode Solo - Sélection du héros et combat
     */
    public function singlePlayer(): void {
        // Traitement abandon/nouveau jeu
        if ($this->post('logout') || $this->post('new_game')) {
            $this->cleanupCombatSession();
            $this->redirect('/');
        }
        
        // Initialisation du combat
        if ($this->post('hero_choice') && !isset($_SESSION['combat'])) {
            $this->initSinglePlayerCombat();
        }
        
        // Action en combat
        if ($this->post('action') && isset($_SESSION['combat'])) {
            $combat = $_SESSION['combat'];
            if (!$combat->isOver()) {
                $combat->executePlayerAction($this->post('action'));
            }
        }
        
        // Préparer les données pour la vue
        $data = [
            'pageTitle' => 'Combat Solo - Horus Battle Arena',
            'extraCss' => ['shared-selection', 'single_player', 'combat'],
            'showUserBadge' => true,
            'showMainTitle' => true,
            'inCombat' => isset($_SESSION['combat']),
            'combat' => $_SESSION['combat'] ?? null,
            'heroImg' => $_SESSION['hero_img'] ?? null,
            'enemyImg' => $_SESSION['enemy_img'] ?? null,
        ];
        
        $this->render('game/single_player', $data);
    }
    
    /**
     * Mode Multijoueur - Menu de sélection du mode
     */
    public function multiplayer(): void {
        // Nettoyer les données de match précédent
        $this->cleanupMatchSession();
        
        $data = [
            'pageTitle' => 'Multijoueur - Horus Battle Arena',
            'extraCss' => ['shared-selection', 'multiplayer-mode'],
            'showUserBadge' => true,
            'showMainTitle' => true,
        ];
        
        $this->render('game/multiplayer', $data);
    }
    
    /**
     * Multijoueur 1v1 - Sélection du héros
     */
    public function multiplayerSelection(): void {
        $data = [
            'pageTitle' => 'Multijoueur 1v1 - Horus Battle Arena',
            'extraCss' => ['shared-selection', 'multiplayer'],
            'showUserBadge' => true,
            'showMainTitle' => true,
            'displayNameValue' => User::isLoggedIn() ? User::getCurrentUsername() : null,
            'displayNameIsStatic' => User::isLoggedIn(),
        ];
        
        $this->render('game/multiplayer_selection', $data);
    }
    
    /**
     * Multijoueur 5v5 - Sélection de l'équipe
     */
    public function selection5v5(): void {
        // 5v5 requiert d'être connecté
        if (!User::isLoggedIn()) {
            $this->redirect('/login?redirect=game/5v5-selection');
        }
        
        $userId = User::getCurrentUserId();
        $pdo = Database::getInstance();
        
        // Récupérer les équipes de l'utilisateur (5 héros obligatoires)
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   COUNT(tm.id) as hero_count
            FROM teams t
            LEFT JOIN team_members tm ON t.id = tm.team_id
            WHERE t.user_id = ? AND t.is_active = 1
            GROUP BY t.id
            HAVING hero_count = 5
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute([$userId]);
        $userTeams = $stmt->fetchAll();
        
        // Charger les bénédictions pour l'affichage
        require_once COMPONENTS_PATH . '/selection-utils.php';
        $blessingsList = getBlessingsList();
        $blessingsById = [];
        foreach ($blessingsList as $b) {
            $blessingsById[$b['id']] = $b;
        }
        
        // Charger les membres pour chaque équipe
        $teamsWithMembers = [];
        
        // Préparer la requête UNE SEULE FOIS avant la boucle (optimisation)
        $stmtMembers = $pdo->prepare("
            SELECT tm.position, tm.hero_id, tm.blessing_id, h.*
            FROM team_members tm
            LEFT JOIN heroes h ON tm.hero_id = h.hero_id
            WHERE tm.team_id = ?
            ORDER BY tm.position ASC
        ");
        
        foreach ($userTeams as $team) {
            $stmtMembers->execute([$team->id]);
            $members = $stmtMembers->fetchAll();
            
            $teamsWithMembers[] = [
                'team' => $team,
                'members' => $members
            ];
        }
        
        $data = [
            'pageTitle' => 'Multijoueur 5v5 - Horus Battle Arena',
            'extraCss' => ['shared-selection', 'multiplayer', 'multiplayer-5v5-selection'],
            'showUserBadge' => true,
            'showMainTitle' => true,
            'displayNameValue' => User::isLoggedIn() ? User::getCurrentUsername() : null,
            'displayNameIsStatic' => User::isLoggedIn(),
            'userTeams' => $teamsWithMembers,
            'blessingsById' => $blessingsById,
            'username' => $_SESSION['username'] ?? 'Joueur',
        ];
        
        $this->render('game/multiplayer_5v5_selection', $data);
    }
    
    /**
     * Combat multijoueur
     */
    public function multiplayerCombat(): void {
        $matchId = $this->get('match_id') ?? $_SESSION['matchId'] ?? null;
        
        // Traitement abandon
        if ($this->post('abandon_multi')) {
            $this->handleAbandon($matchId);
            $this->redirect('/');
        }
        
        if (!$matchId) {
            $this->redirect('/game/multiplayer');
        }
        
        $_SESSION['matchId'] = $matchId;
        
        // Charger le match
        $matchFile = DATA_PATH . '/matches/' . $matchId . '.json';
        
        if (!file_exists($matchFile)) {
            unset($_SESSION['matchId']);
            $this->redirect('/game/multiplayer?error=match_not_found');
        }
        
        $matchData = json_decode(file_get_contents($matchFile), true);
        
        if (!$matchData) {
            $this->flash('error', 'Impossible de lire les données du match.');
            $this->redirect('/game/multiplayer');
        }
        
        // Charger état combat
        $stateFile = DATA_PATH . '/matches/' . $matchId . '.state';
        $multiCombat = null;
        
        if (file_exists($stateFile)) {
            $multiCombat = MultiCombat::load($stateFile);
        }
        
        // Créer état initial si nécessaire
        if (!$multiCombat && isset($matchData['player1']['hero'])) {
            try {
                $multiCombat = MultiCombat::create($matchData['player1'], $matchData['player2']);
                if ($multiCombat) {
                    $multiCombat->save($stateFile);
                }
            } catch (Exception $e) {
                error_log("Erreur initialisation combat: " . $e->getMessage());
            }
        }
        
        // Déterminer le rôle du joueur
        $sessionId = session_id();
        $isP1 = ($matchData['player1']['session'] === $sessionId);
        $is5v5 = $matchData['mode'] === '5v5' || ($multiCombat instanceof TeamCombat);
        
        // Construire l'état du jeu
        $gameState = $this->buildGameState($multiCombat, $matchData, $sessionId, $isP1);
        
        $data = [
            'pageTitle' => ($is5v5 ? '5v5' : '1v1') . ' Combat - Horus Battle Arena',
            'extraCss' => ['shared-selection', 'multiplayer-combat', 'combat'],
            'showUserBadge' => false,
            'showMainTitle' => false,
            'matchId' => $matchId,
            'matchData' => $matchData,
            'gameState' => $gameState,
            'isP1' => $isP1,
            'is5v5' => $is5v5,
            'myTeamName' => $isP1 ? ($matchData['player1']['display_name'] ?? 'Mon Équipe') : ($matchData['player2']['display_name'] ?? 'Mon Équipe'),
            'oppTeamName' => $isP1 ? ($matchData['player2']['display_name'] ?? 'Adversaire') : ($matchData['player1']['display_name'] ?? 'Adversaire'),
        ];
        
        $this->render('game/multiplayer_combat', $data);
    }
    
    /**
     * 5v5 - Setup de test
     */
    public function setup5v5(): void {
        // Redirection temporaire - ce setup crée un match test
        header('Location: /nodeTest2/mood-checker/php_test/pages/game/multiplayer_5v5_setup.php');
        exit;
    }
    
    /**
     * Simulation de combat - Statistiques d'équilibrage
     */
    public function simulation(): void {
        set_time_limit(0); // Pas de limite de temps
        
        // Charger les utilitaires
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
        
        $nbPersonnages = count($personnages);
        $nbMatchups = ($nbPersonnages * ($nbPersonnages - 1)) / 2;
        
        // Variables pour les résultats
        $results = null;
        $blessingResults = null;
        $combatsPerMatchup = 10;
        $selectedHero = 'all';
        $selectedBlessing = 'all';
        $opponentBlessingMode = 'same';
        
        // Simulation classique
        if (isset($_POST['simulate'])) {
            $combatsPerMatchup = (int) $_POST['combats_count'];
            $combatsPerMatchup = max(1, min(1000, $combatsPerMatchup));
            $results = $this->runClassicSimulation($personnages, $combatsPerMatchup);
        }
        
        // Simulation avec bénédictions
        if (isset($_POST['simulate_blessing'])) {
            $combatsPerMatchup = (int) $_POST['combats_count_blessing'];
            $combatsPerMatchup = max(1, min(500, $combatsPerMatchup));
            $selectedHero = $_POST['hero_select'] ?? 'all';
            $selectedBlessing = $_POST['blessing_select'] ?? 'all';
            $opponentBlessingMode = $_POST['opponent_blessing_mode'] ?? 'same';
            
            $blessingResults = $this->runBlessingSimulation($personnages, $blessingsList, $combatsPerMatchup, $selectedHero, $selectedBlessing, $opponentBlessingMode);
        }
        
        $data = [
            'pageTitle' => 'Simulation - Horus Battle Arena',
            'extraCss' => ['simulation'],
            'showUserBadge' => true,
            'showMainTitle' => true,
            'personnages' => $personnages,
            'blessingsList' => $blessingsList,
            'nbPersonnages' => $nbPersonnages,
            'nbMatchups' => $nbMatchups,
            'combatsPerMatchup' => $combatsPerMatchup,
            'results' => $results,
            'blessingResults' => $blessingResults,
            'selectedHero' => $selectedHero,
            'selectedBlessing' => $selectedBlessing,
            'opponentBlessingMode' => $opponentBlessingMode,
        ];
        
        $this->render('game/simulation', $data);
    }
    
    /**
     * Crée une instance fraîche d'un personnage à partir de ses stats
     */
    private function createFighter(array $stats): Personnage {
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
     * Lance la simulation classique
     */
    private function runClassicSimulation(array $personnages, int $combatsPerMatchup): array {
        $stats = [];
        
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
                    $fighter1 = $this->createFighter($p1);
                    $fighter2 = $this->createFighter($p2);
                    
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
     * Crée une instance de bénédiction à partir de son ID
     */
    private function createBlessing(string $blessingId): ?Blessing {
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
    private function createFighterWithBlessing(array $stats, ?string $blessingId = null): Personnage {
        $fighter = $this->createFighter($stats);
        if ($blessingId && $blessingId !== 'none') {
            $blessing = $this->createBlessing($blessingId);
            if ($blessing) {
                $fighter->addBlessing($blessing);
            }
        }
        return $fighter;
    }
    
    /**
     * Lance la simulation avec bénédictions
     */
    private function runBlessingSimulation(array $personnages, array $blessingsList, int $combatsPerMatchup, string $selectedHero, string $selectedBlessing, string $opponentBlessingMode): array {
        $results = [];
        
        // Filtrer les bénédictions (exclure 'none')
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
                        $opponentBlessings = [$heroBlessing];
                    } else {
                        $opponentBlessings = $activeBlessings;
                    }
                    
                    foreach ($opponentBlessings as $oppBlessing) {
                        $matchupKey = $opponent['id'] . '_' . $oppBlessing['id'];
                        
                        $heroWins = 0;
                        $oppWins = 0;
                        $totalTurns = 0;
                        
                        // Lancer les combats
                        for ($n = 0; $n < $combatsPerMatchup; $n++) {
                            $fighter1 = $this->createFighterWithBlessing($hero, $heroBlessing['id']);
                            $fighter2 = $this->createFighterWithBlessing($opponent, $oppBlessing['id']);
                            
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
    
    // === Méthodes privées ===
    
    private function cleanupCombatSession(): void {
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? null;
        
        unset($_SESSION['combat']);
        unset($_SESSION['hero_img']);
        unset($_SESSION['enemy_img']);
        unset($_SESSION['hero_id']);
        unset($_SESSION['enemy_id']);
        unset($_SESSION['combat_recorded']);
        
        if ($userId !== null) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
        }
    }
    
    private function cleanupMatchSession(): void {
        unset($_SESSION['matchId']);
        unset($_SESSION['queue5v5Status']);
        unset($_SESSION['queue5v5Team']);
        unset($_SESSION['queue5v5DisplayName']);
        unset($_SESSION['queue5v5BlessingId']);
        unset($_SESSION['queueHeroId']);
        unset($_SESSION['queueStartTime']);
        unset($_SESSION['queueHeroData']);
        unset($_SESSION['queueDisplayName']);
        unset($_SESSION['queueBlessingId']);
    }
    
    private function initSinglePlayerCombat(): void {
        require_once COMPONENTS_PATH . '/selection-utils.php';
        $personnages = getHeroesList();
        
        $heroStats = null;
        foreach ($personnages as $p) {
            if ($p['id'] === $this->post('hero_choice')) {
                $heroStats = $p;
                break;
            }
        }
        
        if (!$heroStats) return;
        
        $potentialEnemies = array_filter($personnages, fn($p) => $p['id'] !== $heroStats['id']);
        $enemyStats = $potentialEnemies[array_rand($potentialEnemies)];
        
        $heroClass = $heroStats['type'];
        $enemyClass = $enemyStats['type'];
        
        $heroDisplayName = $this->post('display_name') && trim($this->post('display_name'))
            ? trim($this->post('display_name'))
            : $heroStats['name'];
        
        $hero = new $heroClass($heroStats['pv'], $heroStats['atk'], $heroDisplayName, $heroStats['def'] ?? 5, $heroStats['speed'] ?? 10);
        $enemy = new $enemyClass($enemyStats['pv'], $enemyStats['atk'], $enemyStats['name'], $enemyStats['def'] ?? 5, $enemyStats['speed'] ?? 10);
        
        // Appliquer bénédiction
        $blessingId = $this->post('blessing_choice');
        if ($blessingId && file_exists(CORE_PATH . '/blessings/' . $blessingId . '.php')) {
            require_once CORE_PATH . '/blessings/' . $blessingId . '.php';
            if (class_exists($blessingId)) {
                $hero->addBlessing(new $blessingId());
            }
        }
        
        $_SESSION['combat'] = new Combat($hero, $enemy);
        $_SESSION['hero_img'] = $heroStats['images']['p1'];
        $_SESSION['enemy_img'] = $enemyStats['images']['p1'];
        $_SESSION['hero_id'] = $heroStats['id'];
        $_SESSION['enemy_id'] = $enemyStats['id'];
        $_SESSION['combat_recorded'] = false;
    }
    
    private function handleAbandon(?string $matchId): void {
        if (!$matchId) return;
        
        $matchFile = DATA_PATH . '/matches/' . $matchId . '.json';
        if (!file_exists($matchFile)) return;
        
        $fp = fopen($matchFile, 'r+');
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return;
        }
        
        $metaData = json_decode(stream_get_contents($fp), true);
        if (!$metaData) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return;
        }
        
        $sessionId = session_id();
        $isP1 = ($metaData['player1']['session'] === $sessionId);
        $winnerKey = $isP1 ? 'player2' : 'player1';
        $loserName = $isP1 ? ($metaData['player1']['display_name'] ?? 'Joueur 1') : ($metaData['player2']['display_name'] ?? 'Joueur 2');
        
        $metaData['status'] = 'finished';
        $metaData['winner'] = $winnerKey;
        $metaData['logs'][] = "🏳️ " . $loserName . " a abandonné !";
        
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($metaData, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
        fclose($fp);
        
        $this->cleanupCombatSession();
        $this->cleanupMatchSession();
    }
    
    private function buildGameState($multiCombat, array $matchData, string $sessionId, bool $isP1): array {
        if ($multiCombat) {
            $gameState = $multiCombat->getStateForUser($sessionId, $matchData);
            $gameState['status'] = 'active';
            $gameState['turn'] = $matchData['turn'] ?? 1;
            $gameState['isOver'] = $multiCombat->isOver();
            
            // Déterminer qui doit jouer
            $myRole = $isP1 ? 'p1' : 'p2';
            $actions = $matchData['current_turn_actions'] ?? [];
            $gameState['waiting_for_me'] = !isset($actions[$myRole]) && !$gameState['isOver'];
            $gameState['waiting_for_opponent'] = isset($actions[$myRole]) && !$gameState['isOver'];
            
            if (!empty($gameState['me']['img'])) {
                $gameState['me']['img'] = View::asset($gameState['me']['img']);
            }
            if (!empty($gameState['opponent']['img'])) {
                $gameState['opponent']['img'] = View::asset($gameState['opponent']['img']);
            }
            
            return $gameState;
        }
        
        // Mode test UI
        $myPlayer = $isP1 ? $matchData['player1'] : $matchData['player2'];
        $oppPlayer = $isP1 ? $matchData['player2'] : $matchData['player1'];
        $myHero = $myPlayer['hero'] ?? $myPlayer['heroes'][0] ?? null;
        $oppHero = $oppPlayer['hero'] ?? $oppPlayer['heroes'][0] ?? null;
        
        return [
            'status' => 'active',
            'turn' => 1,
            'isOver' => false,
            'waiting_for_me' => true,
            'waiting_for_opponent' => false,
            'logs' => $matchData['logs'] ?? [],
            'turnActions' => [],
            'me' => [
                'name' => $myHero['name'] ?? 'Héros',
                'type' => $myHero['type'] ?? 'Unknown',
                'pv' => $myHero['pv'] ?? 100,
                'max_pv' => $myHero['pv'] ?? 100,
                'atk' => $myHero['atk'] ?? 20,
                'def' => $myHero['def'] ?? 5,
                'speed' => $myHero['speed'] ?? 10,
                'img' => View::asset($myHero['images'][$isP1 ? 'p1' : 'p2'] ?? 'media/heroes/default.png'),
                'activeEffects' => []
            ],
            'opponent' => [
                'name' => $oppHero['name'] ?? 'Adversaire',
                'type' => $oppHero['type'] ?? 'Unknown',
                'pv' => $oppHero['pv'] ?? 100,
                'max_pv' => $oppHero['pv'] ?? 100,
                'atk' => $oppHero['atk'] ?? 20,
                'def' => $oppHero['def'] ?? 5,
                'speed' => $oppHero['speed'] ?? 10,
                'img' => View::asset($oppHero['images'][$isP1 ? 'p2' : 'p1'] ?? 'media/heroes/default.png'),
                'activeEffects' => []
            ]
        ];
    }
}
