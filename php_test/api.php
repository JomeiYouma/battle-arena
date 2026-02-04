<?php
/**
 * MULTIPLAYER API - Queue & Combat
 */

// Disable error display output (prevent HTML in JSON responses)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Autoloader centralisé
require_once __DIR__ . '/includes/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header AFTER error setup but BEFORE any processing
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$sessionId = session_id();

try {
    switch ($action) {
        // ===== JOIN QUEUE =====
        case 'join_queue':
            if (!isset($_POST['hero_id'])) {
                throw new Exception("Héros non sélectionné");
            }
            
            // Récupérer stats du héros depuis la BDD
            $heroManager = new HeroManager();
            $heroModel = null;
            $allHeroes = $heroManager->getAll();
            foreach ($allHeroes as $h) {
                if ($h->getHeroId() === $_POST['hero_id']) {
                    $heroModel = $h;
                    break;
                }
            }
            
            if (!$heroModel) throw new Exception("Héros invalide");
            $heroData = $heroModel->toArray();
            
            // Récupérer le display_name
            $displayName = isset($_POST['display_name']) && !empty(trim($_POST['display_name'])) 
                ? trim($_POST['display_name']) 
                : $heroData['name'];
            
            // Store in session (including images for later use)
            $_SESSION['queueHeroId'] = $_POST['hero_id'];
            $_SESSION['queueStartTime'] = time();
            $_SESSION['queueHeroData'] = $heroData;
            $_SESSION['queueDisplayName'] = $displayName;
            $blessingId = $_POST['blessing_id'] ?? null;
            $_SESSION['queueBlessingId'] = $blessingId;
            
            // Récupérer l'ID utilisateur si connecté
            $userId = User::isLoggedIn() ? User::getCurrentUserId() : null;
            
            $queue = new MatchQueue();
            $result = $queue->findMatch($sessionId, $heroData, $displayName, $userId, $blessingId);
            
            echo json_encode($result);
            break;
        
        // ===== POLL QUEUE (check for match) =====
        case 'poll_queue':
            $queue = new MatchQueue();
            $result = $queue->checkMatchStatus($sessionId);
            echo json_encode($result);
            break;
        
        // ===== LEAVE QUEUE =====
        case 'leave_queue':
            $queue = new MatchQueue();
            $queue->removeFromQueue($sessionId);
            echo json_encode(['status' => 'ok']);
            break;
        
        // ===== JOIN QUEUE 5v5 =====
        case 'join_queue_5v5':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['team']) || count($input['team']) !== 5) {
                throw new Exception("Équipe invalide (5 héros requis)");
            }
            
            $displayName = $input['display_name'] ?? 'Joueur';
            $teamName = $input['team_name'] ?? $displayName; // Nom de l'équipe (peut être différent du display_name)
            $blessingId = $input['blessing_id'] ?? null;
            
            // Stocker dans la session
            $_SESSION['queue5v5StartTime'] = time();
            $_SESSION['queue5v5Team'] = $input['team'];
            $_SESSION['queue5v5DisplayName'] = $displayName;
            $_SESSION['queue5v5TeamName'] = $teamName;
            $_SESSION['queue5v5BlessingId'] = $blessingId;
            $_SESSION['queue5v5Status'] = 'waiting';
            
            // Ajouter à un fichier de queue
            $queueFile = DATA_PATH . '/queue_5v5.json';
            $queue5v5 = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];
            
            $queue5v5[$sessionId] = [
                'session_id' => $sessionId,
                'user_id' => $_SESSION['user_id'] ?? null,
                'team' => $input['team'],
                'display_name' => $displayName,
                'team_name' => $teamName,
                'blessing_id' => $blessingId,
                'joined_at' => time()
            ];
            
            file_put_contents($queueFile, json_encode($queue5v5, JSON_PRETTY_PRINT));
            
            echo json_encode(['status' => 'ok']);
            break;
        
        // ===== POLL QUEUE 5v5 =====
        case 'poll_queue_5v5':
            error_log("poll_queue_5v5: sessionId=$sessionId");
            
            if (!isset($_SESSION['queue5v5Status'])) {
                error_log("poll_queue_5v5: NOT in queue");
                echo json_encode(['status' => 'error', 'message' => 'Pas dans la queue']);
                break;
            }
            
            // Vérifier si un match a été créé
            if (isset($_SESSION['matchId'])) {
                error_log("poll_queue_5v5: Already matched, matchId=" . $_SESSION['matchId']);
                echo json_encode([
                    'status' => 'matched',
                    'match_id' => $_SESSION['matchId']
                ]);
                unset($_SESSION['queue5v5Status']);
                break;
            }
            
            // Vérifier s'il y a d'autres joueurs dans la queue
            $queueFile = DATA_PATH . '/queue_5v5.json';
            if (file_exists($queueFile)) {
                $queue5v5 = json_decode(file_get_contents($queueFile), true);
                
                // NETTOYAGE: Supprimer les entrées obsolètes (plus de 2 minutes)
                $cleanupTimeout = 120; // 2 minutes
                $now = time();
                $cleaned = false;
                foreach ($queue5v5 as $sid => $player) {
                    $joinedAt = $player['joined_at'] ?? 0;
                    if (($now - $joinedAt) > $cleanupTimeout && !isset($player['match_id'])) {
                        error_log("poll_queue_5v5: Removing stale player $sid (joined " . ($now - $joinedAt) . "s ago)");
                        unset($queue5v5[$sid]);
                        $cleaned = true;
                    }
                }
                if ($cleaned) {
                    file_put_contents($queueFile, json_encode($queue5v5, JSON_PRETTY_PRINT));
                }
                
                error_log("poll_queue_5v5: Queue has " . count($queue5v5) . " players");
                
                // D'abord vérifier si ce joueur a déjà été matché
                if (isset($queue5v5[$sessionId]['match_id'])) {
                    $matchId = $queue5v5[$sessionId]['match_id'];
                    error_log("poll_queue_5v5: Player already matched in queue file, matchId=$matchId");
                    $_SESSION['matchId'] = $matchId;
                    unset($_SESSION['queue5v5Status']);
                    
                    // Retirer de la queue
                    unset($queue5v5[$sessionId]);
                    file_put_contents($queueFile, json_encode($queue5v5, JSON_PRETTY_PRINT));
                    
                    echo json_encode([
                        'status' => 'matched',
                        'match_id' => $matchId
                    ]);
                    break;
                }
                
                // Chercher un adversaire
                foreach ($queue5v5 as $sid => $player) {
                    error_log("poll_queue_5v5: Checking player $sid (current=$sessionId, has_match_id=" . (isset($player['match_id']) ? 'yes' : 'no') . ")");
                    
                    if ($sid !== $sessionId && !isset($player['match_id'])) {
                        // Match trouvé ! Créer le match
                        error_log("poll_queue_5v5: MATCH FOUND! Creating match between $sessionId and $sid");
                        $matchId = uniqid('match_5v5_');
                        $now = time();
                        
                        // Récupérer les données du joueur courant depuis la queue (plus fiable que la session)
                        $currentPlayerData = $queue5v5[$sessionId] ?? null;
                        $currentDisplayName = $currentPlayerData['display_name'] ?? $_SESSION['queue5v5DisplayName'] ?? 'Joueur 1';
                        $currentTeamName = $currentPlayerData['team_name'] ?? $_SESSION['queue5v5TeamName'] ?? $currentDisplayName;
                        $currentTeam = $currentPlayerData['team'] ?? $_SESSION['queue5v5Team'] ?? [];
                        $currentBlessingId = $currentPlayerData['blessing_id'] ?? $_SESSION['queue5v5BlessingId'] ?? null;
                        $currentUserId = $currentPlayerData['user_id'] ?? $_SESSION['user_id'] ?? null;
                        $opponentUserId = $player['user_id'] ?? null;
                        $opponentTeamName = $player['team_name'] ?? $player['display_name'] ?? 'Équipe adverse';
                        
                        $matchData = [
                            'id' => $matchId,
                            'created_at' => $now,
                            'status' => 'active',
                            'mode' => '5v5',
                            'turn' => 1,
                            'player1' => [
                                'session' => $sessionId,
                                'display_name' => $currentDisplayName,
                                'team_name' => $currentTeamName,
                                'user_id' => $currentUserId,
                                'team_id' => 1,
                                'heroes' => $currentTeam,
                                'blessing_id' => $currentBlessingId,
                                'last_poll' => $now
                            ],
                            'player2' => [
                                'session' => $sid,
                                'display_name' => $player['display_name'],
                                'team_name' => $opponentTeamName,
                                'user_id' => $opponentUserId,
                                'team_id' => 2,
                                'heroes' => $player['team'],
                                'blessing_id' => $player['blessing_id'],
                                'last_poll' => $now
                            ],
                            'logs' => ["🏆 MATCH 5v5 DÉMARRÉ"],
                            'current_turn_actions' => [],
                            'last_update' => $now
                        ];
                        
                        // Sauvegarder le match
                        $matchesDir = DATA_PATH . '/matches/';
                        if (!is_dir($matchesDir)) {
                            mkdir($matchesDir, 0777, true);
                        }
                        
                        $matchFile = $matchesDir . $matchId . '.json';
                        $saved = file_put_contents($matchFile, json_encode($matchData, JSON_PRETTY_PRINT));
                        error_log("5v5 Match file created: $matchFile (saved: $saved bytes)");
                        
                        // Initialiser le combat
                        $stateFile = $matchesDir . $matchId . '.state';
                        try {
                            $combat = TeamCombat::create($matchData['player1'], $matchData['player2']);
                            if ($combat) {
                                $combat->save($stateFile);
                                error_log("5v5 Combat state created: $stateFile");
                            } else {
                                error_log("ERROR: TeamCombat::create() returned null");
                            }
                        } catch (Exception $e) {
                            error_log("ERROR creating TeamCombat: " . $e->getMessage());
                        }
                        
                        // Marquer les deux joueurs comme matchés AVEC le match_id
                        $queue5v5[$sid]['match_id'] = $matchId;
                        $queue5v5[$sessionId]['match_id'] = $matchId;
                        file_put_contents($queueFile, json_encode($queue5v5, JSON_PRETTY_PRINT));
                        
                        // Stocker dans la session
                        $_SESSION['matchId'] = $matchId;
                        unset($_SESSION['queue5v5Status']);
                        
                        // Retirer le joueur courant de la queue
                        unset($queue5v5[$sessionId]);
                        file_put_contents($queueFile, json_encode($queue5v5, JSON_PRETTY_PRINT));
                        
                        echo json_encode([
                            'status' => 'matched',
                            'match_id' => $matchId
                        ]);
                        break 2; // Sortir du foreach et du switch
                    }
                }
            }
            
            // Toujours en attente
            echo json_encode(['status' => 'waiting']);
            break;
        
        // ===== FORCE BOT MATCH 5v5 =====
        case 'force_bot_match_5v5':
            if (!isset($_SESSION['queue5v5Team'])) {
                echo json_encode(['status' => 'error', 'message' => 'Pas d\'équipe en queue']);
                break;
            }
            
            // Créer une équipe bot aléatoire avec HeroManager
            $heroManager = new HeroManager();
            $allHeroModels = $heroManager->getAll();
            shuffle($allHeroModels);
            $botTeamModels = array_slice($allHeroModels, 0, 5);
            
            // Convertir les Hero en format attendu par TeamCombat
            $botTeam = array_map(function($hero) {
                return $hero->toArray();
            }, $botTeamModels);
            
            // Récupérer les données du joueur depuis la queue ou la session
            $queueFile = DATA_PATH . '/queue_5v5.json';
            $queue5v5 = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : [];
            $currentPlayerData = $queue5v5[$sessionId] ?? null;
            $currentDisplayName = $currentPlayerData['display_name'] ?? $_SESSION['queue5v5DisplayName'] ?? 'Joueur';
            $currentTeam = $currentPlayerData['team'] ?? $_SESSION['queue5v5Team'] ?? [];
            $currentBlessingId = $currentPlayerData['blessing_id'] ?? $_SESSION['queue5v5BlessingId'] ?? null;
            $currentUserId = $currentPlayerData['user_id'] ?? $_SESSION['user_id'] ?? null;
            
            $matchId = uniqid('match_5v5_bot_');
            $now = time();
            
            $matchData = [
                'id' => $matchId,
                'created_at' => $now,
                'status' => 'active',
                'mode' => '5v5',
                'turn' => 1,
                'player1' => [
                    'session' => $sessionId,
                    'display_name' => $currentDisplayName,
                    'user_id' => $currentUserId,
                    'team_id' => 1,
                    'heroes' => $currentTeam,
                    'blessing_id' => $currentBlessingId,
                    'last_poll' => $now
                ],
                'player2' => [
                    'session' => 'bot_' . uniqid(),
                    'display_name' => 'Équipe Bot',
                    'user_id' => null,
                    'is_bot' => true,
                    'team_id' => 2,
                    'heroes' => $botTeam,
                    'blessing_id' => null,
                    'last_poll' => $now
                ],
                'logs' => ["🏆 MATCH 5v5 vs BOT"],
                'current_turn_actions' => [],
                'last_update' => $now
            ];
            
            $matchesDir = DATA_PATH . '/matches/';
            if (!is_dir($matchesDir)) {
                mkdir($matchesDir, 0777, true);
            }
            
            $matchFile = $matchesDir . $matchId . '.json';
            file_put_contents($matchFile, json_encode($matchData, JSON_PRETTY_PRINT));
            
            $stateFile = $matchesDir . $matchId . '.state';
            $combat = TeamCombat::create($matchData['player1'], $matchData['player2']);
            if ($combat) {
                $combat->save($stateFile);
            }
            
            $_SESSION['matchId'] = $matchId;
            unset($_SESSION['queue5v5Status']);
            
            echo json_encode(['status' => 'ok', 'match_id' => $matchId]);
            break;
        
        // ===== LEAVE QUEUE 5v5 =====
        case 'leave_queue_5v5':
            $queueFile = DATA_PATH . '/queue_5v5.json';
            if (file_exists($queueFile)) {
                $queue5v5 = json_decode(file_get_contents($queueFile), true);
                unset($queue5v5[$sessionId]);
                file_put_contents($queueFile, json_encode($queue5v5, JSON_PRETTY_PRINT));
            }
            
            unset($_SESSION['queue5v5Status']);
            unset($_SESSION['queue5v5Team']);
            
            echo json_encode(['status' => 'ok']);
            break;
        
        // ===== POLL COMBAT STATE =====
        case 'poll_status':
            $matchId = $_GET['match_id'] ?? null;
            
            if (!$matchId) {
                throw new Exception("Match ID manquant");
            }
            
            $matchFile = DATA_PATH . '/matches/' . $matchId . '.json';
            if (!file_exists($matchFile)) {
                throw new Exception("Match non trouvé: $matchFile");
            }
            
            // LOCK EXCLUSIF pour mise à jour last_poll
            $fp = fopen($matchFile, 'r+');
            if (!flock($fp, LOCK_EX)) {
                throw new Exception("Impossible de verrouiller le match");
            }
            
            $metaData = json_decode(stream_get_contents($fp), true);
            
            if (!$metaData) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new Exception("Impossible de décoder le match JSON");
            }
            
            // Déterminer le rôle
            $isP1 = ($metaData['player1']['session'] === $sessionId);
            $myRole = $isP1 ? 'p1' : 'p2';
            $oppRole = $isP1 ? 'p2' : 'p1';
            $myPlayerKey = $isP1 ? 'player1' : 'player2';
            $oppPlayerKey = $isP1 ? 'player2' : 'player1';
            
            // Mettre à jour mon last_poll
            $now = time();
            $metaData[$myPlayerKey]['last_poll'] = $now;
            
            // Vérifier si l'adversaire est AFK (60 secondes sans poll) - seulement en mode PvP
            $AFK_TIMEOUT = 60;
            $opponentAFK = false;
            if (($metaData['mode'] ?? '') === 'pvp' && $metaData['status'] === 'active') {
                $oppLastPoll = $metaData[$oppPlayerKey]['last_poll'] ?? $metaData['created_at'];
                if (($now - $oppLastPoll) > $AFK_TIMEOUT) {
                    $opponentAFK = true;
                    $metaData['status'] = 'finished';
                    $metaData['winner'] = $myPlayerKey;
                    $metaData['logs'][] = "⚠️ " . ($metaData[$oppPlayerKey]['display_name'] ?? 'Adversaire') . " est AFK. Victoire par forfait!";
                }
            }
            
            // Sauvegarder les changements
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($metaData, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
            fclose($fp);
            
            // Charger état du combat
            $stateFile = DATA_PATH . '/matches/' . $matchId . '.state';
            $multiCombat = MultiCombat::load($stateFile);
            
            if (!$multiCombat) {
                // Initialiser le combat
                try {
                    // Vérifier si c'est un match 5v5
                    if (($metaData['mode'] ?? '') === '5v5') {
                        $multiCombat = TeamCombat::create($metaData['player1'], $metaData['player2']);
                    } else {
                        $multiCombat = MultiCombat::create($metaData['player1'], $metaData['player2']);
                    }
                    
                    if (!$multiCombat) {
                        throw new Exception("Impossible de créer l'instance de combat");
                    }
                    if (!$multiCombat->save($stateFile)) {
                        throw new Exception("Impossible de sauvegarder l'état initial du combat");
                    }
                } catch (Exception $e) {
                    throw new Exception("Erreur lors de l'initialisation du combat: " . $e->getMessage());
                }
            }
            
            // Construire la réponse
            try {
                $response = $multiCombat->getStateForUser($sessionId, $metaData);
                $response['status'] = 'active';
                $response['turn'] = $metaData['turn'] ?? 1;
                
                // Gérer la fin de partie (combat ou forfait ou abandon)
                $isOver = $multiCombat->isOver() || $metaData['status'] === 'finished';
                $response['isOver'] = $isOver;
                
                // Déterminer le gagnant
                if ($isOver) {
                    // Abandon ou AFK victory - winner stocké dans metaData
                    if (isset($metaData['winner'])) {
                        $winnerKey = $metaData['winner'];
                        $iAmWinner = ($winnerKey === $myPlayerKey);
                        $response['winner'] = $iAmWinner ? 'you' : 'opponent';
                        $response['forfeit'] = true;
                    } else if ($opponentAFK) {
                        $response['winner'] = 'you';
                        $response['forfeit'] = true;
                    }
                    // Combat victory - from MultiCombat
                    else if ($multiCombat->isOver()) {
                        // Le winner est déjà dans response via getStateForUser
                    }
                }
                
                // Déterminer qui doit jouer
                $actions = $metaData['current_turn_actions'] ?? [];
                
                // Vérifier si on est contre un bot
                $oppPlayerData = $isP1 ? $metaData['player2'] : $metaData['player1'];
                $isVsBot = ($metaData['mode'] ?? '') === 'bot' || !empty($oppPlayerData['is_bot']);
                
                $response['waiting_for_me'] = !isset($actions[$myRole]) && !$isOver;
                $response['waiting_for_opponent'] = isset($actions[$myRole]) && !isset($actions[$oppRole]) && !$isVsBot && !$isOver;
                
                error_log("poll_status: sessionId=$sessionId, myRole=$myRole, isVsBot=" . ($isVsBot ? 'true' : 'false') . ", current_turn_actions=" . json_encode($actions) . ", waiting_for_me=" . ($response['waiting_for_me'] ? 'true' : 'false') . ", actions_count=" . count($response['actions']));
                
                echo json_encode($response);
            } catch (Exception $e) {
                throw new Exception("Erreur lors de la construction de la réponse: " . $e->getMessage());
            }
            break;
        
        // ===== SUBMIT ACTION =====
        case 'submit_move':
            $matchId = $_POST['match_id'] ?? null;
            $move = $_POST['move'] ?? null;
            
            if (!$matchId || !$move) throw new Exception("Données manquantes");
            
            $matchFile = DATA_PATH . '/matches/' . $matchId . '.json';
            if (!file_exists($matchFile)) {
                throw new Exception("Match non trouvé");
            }
            
            // LOCK EXCLUSIF
            $fp = fopen($matchFile, 'r+');
            if (!flock($fp, LOCK_EX)) {
                throw new Exception("Impossible de verrouiller le match");
            }
            
            $metaData = json_decode(stream_get_contents($fp), true);
            if (!$metaData) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new Exception("Impossible de décoder le match JSON");
            }
            
            // Déterminer le rôle - AVEC VALIDATION
            $isP1 = ($metaData['player1']['session'] === $sessionId);
            $isP2 = ($metaData['player2']['session'] === $sessionId);
            
            if (!$isP1 && !$isP2) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new Exception("Vous n'êtes pas un joueur de ce match");
            }
            
            $myRole = $isP1 ? 'p1' : 'p2';
            $oppRole = $isP1 ? 'p2' : 'p1';
            
            $metaData['current_turn_actions'][$myRole] = $move;
            
            // Détection si l'adversaire est un bot
            $oppPlayerData = $isP1 ? $metaData['player2'] : $metaData['player1'];
            $isVsBot = ($metaData['mode'] ?? '') === 'bot' || !empty($oppPlayerData['is_bot']);
            
            // Si c'est un match contre bot, générer action bot
            if ($isVsBot) {
                $metaData['current_turn_actions'][$oppRole] = generateBotMove($metaData, $oppRole);
            }
            
            // Vérifier si les deux ont joué
            $p1Played = isset($metaData['current_turn_actions']['p1']);
            $p2Played = isset($metaData['current_turn_actions']['p2']);
            $bothPlayed = $p1Played && $p2Played;
            
            if ($bothPlayed) {
                // Résoudre le tour
                $stateFile = DATA_PATH . '/matches/' . $matchId . '.state';
                $multiCombat = MultiCombat::load($stateFile);
                
                if (!$multiCombat) {
                    throw new Exception("Impossible de charger l'état du combat");
                }
                
                $p1Move = $metaData['current_turn_actions']['p1'];
                $p2Move = $metaData['current_turn_actions']['p2'] ?? 'attack';
                
                $multiCombat->resolveMultiTurn($p1Move, $p2Move);
                
                // Pour le 5v5, vérifier les morts et marquer les switches obligatoires
                if ($multiCombat instanceof TeamCombat) {
                    $multiCombat->checkAndMarkForcedSwitches();
                }
                
                if (!$multiCombat->save($stateFile)) {
                    throw new Exception("Impossible de sauvegarder l'état du combat");
                }
                
                // Avancer le tour
                $metaData['turn'] = ($metaData['turn'] ?? 1) + 1;
                $metaData['current_turn_actions'] = [];
                $metaData['last_update'] = time();
                
                if ($multiCombat->isOver()) {
                    $metaData['status'] = 'finished';
                    
                    // Enregistrer les stats pour PvP et 5v5 (pas pour les bots)
                    $mode = $metaData['mode'] ?? '';
                    $isVsBotMatch = $mode === 'bot' || !empty($metaData['player2']['is_bot']);
                    
                    if (!$isVsBotMatch && ($mode === 'pvp' || $mode === '5v5')) {
                        $winnerId = $multiCombat->getWinnerId(); // 'p1' ou 'p2'
                        
                        // Récupérer les infos utilisateur si disponibles
                        $p1UserId = $metaData['player1']['user_id'] ?? null;
                        $p2UserId = $metaData['player2']['user_id'] ?? null;
                        
                        $userModel = new User();
                        $matchUuid = $matchId; // Utiliser le matchId comme UUID unique
                        
                        if ($mode === '5v5') {
                            // Mode 5v5 : enregistrer tous les héros de chaque équipe
                            $p1Heroes = array_column($metaData['player1']['heroes'] ?? [], 'id');
                            $p2Heroes = array_column($metaData['player2']['heroes'] ?? [], 'id');
                            $p1TeamName = $metaData['player1']['team_name'] ?? 'Équipe 1';
                            $p2TeamName = $metaData['player2']['team_name'] ?? 'Équipe 2';
                            $p1DisplayName = $metaData['player1']['display_name'] ?? 'Joueur 1';
                            $p2DisplayName = $metaData['player2']['display_name'] ?? 'Joueur 2';
                            
                            // Enregistrer pour P1 (tous les héros de son équipe)
                            if ($p1UserId && !empty($p1Heroes)) {
                                $userModel->recordTeamCombat(
                                    $p1UserId,
                                    $p1Heroes,
                                    $winnerId === 'p1',
                                    $p1TeamName,
                                    $p2DisplayName,
                                    $matchUuid
                                );
                            }
                            
                            // Enregistrer pour P2 (tous les héros de son équipe)
                            if ($p2UserId && !empty($p2Heroes)) {
                                $userModel->recordTeamCombat(
                                    $p2UserId,
                                    $p2Heroes,
                                    $winnerId === 'p2',
                                    $p2TeamName,
                                    $p1DisplayName,
                                    $matchUuid
                                );
                            }
                        } else {
                            // Mode 1v1 : ancien comportement
                            $p1HeroId = $metaData['player1']['hero']['id'] ?? null;
                            $p2HeroId = $metaData['player2']['hero']['id'] ?? null;
                            
                            if ($p1UserId && $p1HeroId) {
                                $userModel->recordCombat(
                                    $p1UserId,
                                    $p1HeroId,
                                    $winnerId === 'p1',
                                    $p2HeroId,
                                    'multi'
                                );
                            }
                            
                            if ($p2UserId && $p2HeroId) {
                                $userModel->recordCombat(
                                    $p2UserId,
                                    $p2HeroId,
                                    $winnerId === 'p2',
                                    $p1HeroId,
                                    'multi'
                                );
                            }
                        }
                    }
                }
            }
            
            // Sauvegarder
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($metaData, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
            fclose($fp);
            
            echo json_encode(['status' => 'ok']);
            break;
        
        // ===== SUBMIT FORCED SWITCH (5v5) =====
        case 'submit_forced_switch':
            $matchId = $_POST['match_id'] ?? null;
            $targetHeroIndex = $_POST['target_index'] ?? null;
            
            if (!$matchId || $targetHeroIndex === null) {
                throw new Exception("Données manquantes");
            }
            
            $matchFile = DATA_PATH . '/matches/' . $matchId . '.json';
            if (!file_exists($matchFile)) {
                throw new Exception("Match non trouvé");
            }
            
            // LOCK EXCLUSIF
            $fp = fopen($matchFile, 'r+');
            if (!flock($fp, LOCK_EX)) {
                throw new Exception("Impossible de verrouiller le match");
            }
            
            $metaData = json_decode(stream_get_contents($fp), true);
            if (!$metaData) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new Exception("Impossible de décoder le match JSON");
            }
            
            // Déterminer le rôle
            $isP1 = ($metaData['player1']['session'] === $sessionId);
            $isP2 = ($metaData['player2']['session'] === $sessionId);
            
            if (!$isP1 && !$isP2) {
                flock($fp, LOCK_UN);
                fclose($fp);
                throw new Exception("Vous n'êtes pas un joueur de ce match");
            }
            
            $playerNum = $isP1 ? 1 : 2;
            
            // Charger le combat
            $stateFile = DATA_PATH . '/matches/' . $matchId . '.state';
            $multiCombat = MultiCombat::load($stateFile);
            
            if (!$multiCombat || !($multiCombat instanceof TeamCombat)) {
                throw new Exception("Pas de combat d'équipe trouvé");
            }
            
            // Effectuer le switch obligatoire
            $targetHeroIndex = (int)$targetHeroIndex;
            if (!$multiCombat->performForcedSwitch($playerNum, $targetHeroIndex)) {
                throw new Exception("Switch obligatoire impossible (héros mort ou index invalide)");
            }
            
            // Sauvegarder l'état
            if (!$multiCombat->save($stateFile)) {
                throw new Exception("Impossible de sauvegarder l'état du combat");
            }
            
            // Sauvegarder les métadonnées
            rewind($fp);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($metaData));
            flock($fp, LOCK_UN);
            fclose($fp);
            
            echo json_encode(['status' => 'ok']);
            break;
        
        default:
            throw new Exception("Action inconnue: " . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    error_log("API ERROR: " . $e->getMessage() . " - " . $e->getTraceAsString());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// ===== HELPER FUNCTIONS =====

/**
 * Génère une action aléatoire pour le bot
 * @param array $metaData Les données du match
 * @param string $botRole 'p1' ou 'p2' - le rôle du bot
 */
function generateBotMove($metaData, $botRole = 'p2') {
    $botPlayerKey = $botRole === 'p1' ? 'player1' : 'player2';
    $botPlayer = $metaData[$botPlayerKey];
    
    // Mode 5v5 - charger le combat pour obtenir les vraies actions du héros actif
    if ($metaData['mode'] === '5v5') {
        $matchId = $metaData['id'] ?? null;
        $stateFile = DATA_PATH . '/matches/' . $matchId . '.state';
        
        if ($matchId && file_exists($stateFile)) {
            $combat = MultiCombat::load($stateFile);
            if ($combat && $combat instanceof TeamCombat) {
                // Récupérer le héros actif du bot
                $botChar = $botRole === 'p1' ? $combat->getPlayer() : $combat->getEnemy();
                $allActions = $botChar->getAllActions();
                
                // Filtrer les actions utilisables (avec PP)
                $usableActions = [];
                foreach ($allActions as $key => $action) {
                    if ($botChar->canUseAction($key)) {
                        $usableActions[] = $key;
                    }
                }
                
                // Parfois switch (20% de chance si plus d'un héros vivant)
                if (rand(1, 100) <= 20) {
                    $team = $botRole === 'p1' ? $combat->getPlayer1Team() : $combat->getPlayer2Team();
                    $currentIdx = $botRole === 'p1' ? $combat->getCurrentPlayer1Index() : $combat->getCurrentPlayer2Index();
                    foreach ($team as $idx => $hero) {
                        if ($idx !== $currentIdx && !$hero->isDead()) {
                            return 'switch:' . $idx;
                        }
                    }
                }
                
                // Choisir une action aléatoire parmi les utilisables
                if (!empty($usableActions)) {
                    return $usableActions[array_rand($usableActions)];
                }
                
                return 'attack'; // Fallback
            }
        }
        
        // Fallback si on ne peut pas charger le combat
        return 'attack';
    }
    
    // Mode 1v1 classique - charger les vraies actions aussi
    $matchId = $metaData['id'] ?? null;
    $stateFile = DATA_PATH . '/matches/' . $matchId . '.state';
    
    if ($matchId && file_exists($stateFile)) {
        $combat = MultiCombat::load($stateFile);
        if ($combat) {
            $botChar = $botRole === 'p1' ? $combat->getPlayer() : $combat->getEnemy();
            $allActions = $botChar->getAllActions();
            
            $usableActions = [];
            foreach ($allActions as $key => $action) {
                if ($botChar->canUseAction($key)) {
                    $usableActions[] = $key;
                }
            }
            
            if (!empty($usableActions)) {
                // IA simple: préférer heal si <30% HP
                $healthPct = $botChar->getPv() / $botChar->getBasePv();
                if ($healthPct < 0.3 && in_array('heal', $usableActions)) {
                    return 'heal';
                }
                
                return $usableActions[array_rand($usableActions)];
            }
        }
    }
    
    return 'attack'; // Fallback
}

