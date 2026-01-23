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
    if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
        require_once __DIR__ . '/classes/effects/' . $classe . '.php';
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
            
            // Récupérer stats du héros
            $heroes = json_decode(file_get_contents('heros.json'), true);
            $heroData = null;
            foreach ($heroes as $h) {
                if ($h['id'] === $_POST['hero_id']) {
                    $heroData = $h;
                    break;
                }
            }
            
            if (!$heroData) throw new Exception("Héros invalide");
            
            // Store in session (including images for later use)
            $_SESSION['queueHeroId'] = $_POST['hero_id'];
            $_SESSION['queueStartTime'] = time();
            $_SESSION['queueHeroData'] = $heroData;
            
            error_log("API join_queue - sessionId=$sessionId, hero=" . $heroData['name']);
            
            $queue = new MatchQueue();
            $result = $queue->findMatch($sessionId, $heroData);
            
            error_log("API join_queue - result=" . json_encode($result));
            echo json_encode($result);
            break;
        
        // ===== POLL QUEUE (check for match) =====
        case 'poll_queue':
            $queue = new MatchQueue();
            $result = $queue->checkMatchStatus($sessionId);
            echo json_encode($result);
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
            
            // Lire le fichier avec lock
            $fp = fopen($matchFile, 'r');
            flock($fp, LOCK_SH);
            $metaData = json_decode(stream_get_contents($fp), true);
            flock($fp, LOCK_UN);
            fclose($fp);
            
            if (!$metaData) {
                throw new Exception("Impossible de décoder le match JSON");
            }
            
            // Déterminer le rôle
            $isP1 = ($metaData['player1']['session'] === $sessionId);
            $myRole = $isP1 ? 'p1' : 'p2';
            $oppRole = $isP1 ? 'p2' : 'p1';
            
            // Charger état du combat
            $stateFile = __DIR__ . '/data/matches/' . $matchId . '.state';
            $multiCombat = MultiCombat::load($stateFile);
            
            if (!$multiCombat) {
                // Initialiser le combat
                try {
                    $multiCombat = MultiCombat::create($metaData['player1']['hero'], $metaData['player2']['hero']);
                    if (!$multiCombat->save($stateFile)) {
                        throw new Exception("Impossible de sauvegarder l'état initial du combat");
                    }
                } catch (Exception $e) {
                    error_log("Error creating MultiCombat: " . $e->getMessage());
                    throw new Exception("Erreur lors de l'initialisation du combat: " . $e->getMessage());
                }
            }
            
            // Construire la réponse
            try {
                $response = $multiCombat->getStateForUser($sessionId, $metaData);
                $response['status'] = 'active';
                $response['turn'] = $metaData['turn'] ?? 1;
                $response['isOver'] = $multiCombat->isOver();
                
                // Déterminer qui doit jouer
                $actions = $metaData['current_turn_actions'] ?? [];
                $response['waiting_for_me'] = !isset($actions[$myRole]);
                $response['waiting_for_opponent'] = isset($actions[$myRole]) && !isset($actions[$oppRole]) && ($metaData['mode'] ?? '') !== 'bot';
                
                echo json_encode($response);
            } catch (Exception $e) {
                error_log("Error building response: " . $e->getMessage());
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
            
            // Déterminer le rôle
            $isP1 = ($metaData['player1']['session'] === $sessionId);
            $myRole = $isP1 ? 'p1' : 'p2';
            $oppRole = $isP1 ? 'p2' : 'p1';
            
            // Enregistrer l'action
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

