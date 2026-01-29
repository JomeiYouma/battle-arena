<?php
/**
 * MULTIPLAYER API - Queue & Combat
 */

// Disable error display output (prevent HTML in JSON responses)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Autoloader
function chargerClasse($classe) {
    if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/Models/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/Models/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/Services/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/Services/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/effects/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/heroes/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/heroes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/blessings/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/blessings/' . $classe . '.php';
        return;
    }
}
spl_autoload_register('chargerClasse');

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
            require_once __DIR__ . '/classes/Services/HeroManager.php';
            require_once __DIR__ . '/classes/Models/Hero.php';
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
        
        // ===== POLL COMBAT STATE =====
        case 'poll_status':
            $matchId = $_GET['match_id'] ?? null;
            
            if (!$matchId) {
                throw new Exception("Match ID manquant");
            }
            
            $matchFile = __DIR__ . '/data/matches/' . $matchId . '.json';
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
            $stateFile = __DIR__ . '/data/matches/' . $matchId . '.state';
            $multiCombat = MultiCombat::load($stateFile);
            
            if (!$multiCombat) {
                // Initialiser le combat
                try {
                    $multiCombat = MultiCombat::create($metaData['player1'], $metaData['player2']);
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
                $response['waiting_for_me'] = !isset($actions[$myRole]) && !$isOver;
                $response['waiting_for_opponent'] = isset($actions[$myRole]) && !isset($actions[$oppRole]) && ($metaData['mode'] ?? '') !== 'bot' && !$isOver;
                
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
            
            $matchFile = __DIR__ . '/data/matches/' . $matchId . '.json';
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
            
            // Si c'est un match bot et que c'est le joueur qui joue, générer action bot
            if (($metaData['mode'] ?? '') === 'bot' && $myRole === 'p1') {
                $metaData['current_turn_actions'][$oppRole] = generateBotMove($metaData);
            }
            
            // Vérifier si les deux ont joué
            $isBotMatch = ($metaData['mode'] ?? '') === 'bot';
            $p1Played = isset($metaData['current_turn_actions']['p1']);
            $p2Played = isset($metaData['current_turn_actions']['p2']);
            $bothPlayed = $p1Played && ($p2Played || $isBotMatch);
            
            if ($bothPlayed) {
                // Résoudre le tour
                $stateFile = __DIR__ . '/data/matches/' . $matchId . '.state';
                $multiCombat = MultiCombat::load($stateFile);
                
                if (!$multiCombat) {
                    throw new Exception("Impossible de charger l'état du combat");
                }
                
                $p1Move = $metaData['current_turn_actions']['p1'];
                $p2Move = $metaData['current_turn_actions']['p2'] ?? 'attack';
                
                $multiCombat->resolveMultiTurn($p1Move, $p2Move);
                if (!$multiCombat->save($stateFile)) {
                    throw new Exception("Impossible de sauvegarder l'état du combat");
                }
                
                // Avancer le tour
                $metaData['turn'] = ($metaData['turn'] ?? 1) + 1;
                $metaData['current_turn_actions'] = [];
                $metaData['last_update'] = time();
                
                if ($multiCombat->isOver()) {
                    $metaData['status'] = 'finished';
                    
                    // Enregistrer les stats pour PvP seulement (pas pour les bots)
                    if (($metaData['mode'] ?? '') === 'pvp') {
                        $winnerId = $multiCombat->getWinnerId(); // 'p1' ou 'p2'
                        
                        // Récupérer les infos utilisateur si disponibles
                        $p1UserId = $metaData['player1']['user_id'] ?? null;
                        $p2UserId = $metaData['player2']['user_id'] ?? null;
                        
                        if ($p1UserId || $p2UserId) {
                            $p1HeroId = $metaData['player1']['hero']['id'] ?? null;
                            $p2HeroId = $metaData['player2']['hero']['id'] ?? null;
                            $userModel = new User();
                            
                            // Enregistrer pour P1
                            if ($p1UserId && $p1HeroId) {
                                $userModel->recordCombat(
                                    $p1UserId,
                                    $p1HeroId,
                                    $winnerId === 'p1',
                                    $p2HeroId,
                                    'multi'
                                );
                            }
                            
                            // Enregistrer pour P2
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
 */
function generateBotMove($metaData) {
    $botHero = $metaData['player2']['hero'];
    $botHp = $metaData['player2']['hp'] ?? $botHero['pv'];
    
    // IA simple: heal si <30%, sinon random
    $healthPct = $botHp / $botHero['pv'];
    if ($healthPct < 0.3) {
        return 'heal';
    }
    
    // Sinon random entre attack et heal
    $actions = ['attack', 'heal'];
    return $actions[array_rand($actions)];
}

