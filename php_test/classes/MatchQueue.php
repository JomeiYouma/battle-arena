<?php

class MatchQueue {
    private $queueFile;
    private $matchesDir;
    private $timeoutSeconds = 30; // Temps avant de retirer un joueur inactif de la queue

    public function __construct() {
        $this->queueFile = __DIR__ . '/../data/queue.json';
        $this->matchesDir = __DIR__ . '/../data/matches/';
        
        // Assurer que les dossiers existent
        if (!file_exists(dirname($this->queueFile))) {
            mkdir(dirname($this->queueFile), 0777, true);
        }
        if (!file_exists($this->matchesDir)) {
            mkdir($this->matchesDir, 0777, true);
        }
        
        // Initialiser la queue si inexistante
        if (!file_exists($this->queueFile)) {
            file_put_contents($this->queueFile, '[]');
        }
    }

    /**
     * Ajoute un joueur à la queue ou trouve un match existant
     */
    public function findMatch($sessionId, $heroData, $displayName = null, $userId = null) {
        $displayName = $displayName ?? $heroData['name'];
        error_log("MatchQueue::findMatch - Called with sessionId=$sessionId, heroName=" . ($heroData['name'] ?? 'UNKNOWN') . ", displayName=$displayName, userId=$userId");
        $fp = fopen($this->queueFile, 'r+');
        if (flock($fp, LOCK_EX)) { // Verrouillage exclusif
            $content = stream_get_contents($fp);
            $queue = json_decode($content, true) ?: [];
            
            // 1. Nettoyer la queue (joueurs inactifs)
            $now = time();
            $queue = array_filter($queue, function($item) use ($now) {
                return ($now - $item['timestamp']) < $this->timeoutSeconds;
            });

            // 2. Vérifier si on est déjà dans la queue
            $isInQueue = false;
            foreach ($queue as $index => $item) {
                if ($item['sessionId'] === $sessionId) {
                    $queue[$index]['timestamp'] = $now; // Update heartbeat
                    $isInQueue = true;
                    break;
                }
            }

            // 3. Chercher un adversaire
            $opponent = null;
            $opponentIndex = -1;
            foreach ($queue as $index => $item) {
                if ($item['sessionId'] !== $sessionId) {
                    $opponent = $item;
                    $opponentIndex = $index;
                    break;
                }
            }

            if ($opponent) {
                // MATCH TROUVÉ !
                // Retirer l'adversaire de la queue
                array_splice($queue, $opponentIndex, 1);
                // Retirer le joueur actuel s'il y était (pour éviter duplicats)
                $queue = array_filter($queue, function($item) use ($sessionId) {
                    return $item['sessionId'] !== $sessionId;
                });
                
                // Sauvegarder la queue
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode(array_values($queue)));
                flock($fp, LOCK_UN);
                fclose($fp);

                // Créer le fichier de match
                $matchId = uniqid('match_');
                $matchData = [
                    'id' => $matchId,
                    'created_at' => $now,
                    'status' => 'active',
                    'mode' => 'pvp',  // Vrai PvP
                    'turn' => 1,
                    'player1' => [
                        'session' => $opponent['sessionId'],
                        'hero' => $opponent['heroData'],
                        'display_name' => $opponent['displayName'] ?? $opponent['heroData']['name'],
                        'user_id' => $opponent['userId'] ?? null,
                        'hp' => $opponent['heroData']['pv'],
                        'max_hp' => $opponent['heroData']['pv'],
                        'last_poll' => $now
                    ],
                    'player2' => [
                        'session' => $sessionId,
                        'hero' => $heroData,
                        'display_name' => $displayName,
                        'user_id' => $userId,
                        'hp' => $heroData['pv'],
                        'max_hp' => $heroData['pv'],
                        'last_poll' => $now
                    ],
                    'logs' => ["Le combat commence !"],
                    'current_turn_actions' => [],
                    'last_update' => $now
                ];

                file_put_contents($this->matchesDir . $matchId . '.json', json_encode($matchData, JSON_PRETTY_PRINT));
                
                return ['status' => 'matched', 'matchId' => $matchId];

            } else {
                // PAS D'ADVERSAIRE, S'AJOUTER À LA QUEUE
                if (!$isInQueue) {
                    $queue[] = [
                        'sessionId' => $sessionId,
                        'heroData' => $heroData,
                        'displayName' => $displayName,
                        'userId' => $userId,
                        'timestamp' => $now
                    ];
                }
                
                // Sauvegarder
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode(array_values($queue)));
                flock($fp, LOCK_UN);
                fclose($fp);
                
                return ['status' => 'waiting'];
            }

        } else {
            fclose($fp);
            return ['status' => 'error', 'message' => 'Queue locked'];
        }
    }

    /**
     * Vérifie si un match a été créé pour ce joueur (cas où on était dans la queue et qqn nous a matché)
     * Retourne 'matched' si un match joueur existe
     * Retourne 'timeout' et crée un bot si 30 secondes écoulées sans match
     */
    public function checkMatchStatus($sessionId) {
        // 1. Balayer les fichiers matches pour voir si notre ID y est
        $files = glob($this->matchesDir . 'match_*.json');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content) {
                $match = json_decode($content, true);
                if ($match && ($match['player1']['session'] === $sessionId || $match['player2']['session'] === $sessionId)) {
                    if ($match['status'] === 'active') {
                        // Récupérer aussi le compteur de la queue
                        $queueCount = $this->getQueueCount();
                        return ['status' => 'matched', 'matchId' => $match['id'], 'queue_count' => $queueCount];
                    }
                }
            }
        }
        
        // 2. Vérifier si le joueur est dans la queue et si timeout atteint
        $now = time();
        $queueContent = file_get_contents($this->queueFile);
        $queue = json_decode($queueContent, true) ?: [];
        
        error_log("MatchQueue::checkMatchStatus - sessionId=$sessionId, queue_count=" . count($queue) . ", now=$now");
        
        foreach ($queue as $item) {
            if ($item['sessionId'] === $sessionId) {
                $timeInQueue = $now - $item['timestamp'];
                error_log("MatchQueue::checkMatchStatus - Found player in queue. timeInQueue=$timeInQueue, timeout=$this->timeoutSeconds");
                
                // Timeout de 30 secondes
                if ($timeInQueue >= $this->timeoutSeconds) {
                    error_log("MatchQueue::checkMatchStatus - TIMEOUT REACHED! Creating bot match...");
                    // Créer un match bot
                    $matchId = uniqid('match_');
                    $heroData = $item['heroData'];
                    
                    // Sélectionner un ennemi aléatoire
                    $heroes = json_decode(file_get_contents(__DIR__ . '/../heros.json'), true);
                    $potentialEnemies = array_filter($heroes, function($h) use ($heroData) {
                        return $h['id'] !== $heroData['id'];
                    });
                    $enemyData = $potentialEnemies[array_rand($potentialEnemies)];
                    
                    $matchData = [
                        'id' => $matchId,
                        'created_at' => $now,
                        'status' => 'active',
                        'turn' => 1,
                        'mode' => 'bot',  // Marquer comme combat bot
                        'player1' => [
                            'session' => $sessionId,
                            'hero' => $heroData,
                            'display_name' => $item['displayName'] ?? $heroData['name'],
                            'hp' => $heroData['pv'],
                            'max_hp' => $heroData['pv'],
                            'last_poll' => $now
                        ],
                        'player2' => [
                            'session' => 'bot_' . uniqid(),
                            'hero' => $enemyData,
                            'display_name' => $enemyData['name'] . ' (Bot)',
                            'hp' => $enemyData['pv'],
                            'max_hp' => $enemyData['pv'],
                            'is_bot' => true
                        ],
                        'logs' => ["Le bot arrive en renfort !"],
                        'last_update' => $now,
                        'current_turn_actions' => []
                    ];
                    
                    file_put_contents($this->matchesDir . $matchId . '.json', json_encode($matchData, JSON_PRETTY_PRINT));
                    error_log("MatchQueue::checkMatchStatus - Bot match created: $matchId");
                    
                    // Retirer du queue
                    $queue = array_filter($queue, function($item) use ($sessionId) {
                        return $item['sessionId'] !== $sessionId;
                    });
                    file_put_contents($this->queueFile, json_encode(array_values($queue)));
                    
                    return ['status' => 'timeout', 'matchId' => $matchId, 'queue_count' => 0];
                }
                
                // Retourner le compteur
                $queueCount = $this->getQueueCount();
                return ['status' => 'waiting', 'queue_count' => $queueCount];
            }
        }
        
        return ['status' => 'waiting', 'queue_count' => count($queue)];
    }
    
    /**
     * Obtient le nombre de joueurs en queue
     */
    public function getQueueCount() {
        if (!file_exists($this->queueFile)) {
            return 0;
        }
        
        $now = time();
        $content = file_get_contents($this->queueFile);
        $queue = json_decode($content, true) ?: [];
        
        // Nettoyer les entrées expirées
        $queue = array_filter($queue, function($item) use ($now) {
            return ($now - $item['timestamp']) < $this->timeoutSeconds;
        });
        
        return count($queue);
    }
    
    public function removeFromQueue($sessionId) {
        $fp = fopen($this->queueFile, 'r+');
        if (flock($fp, LOCK_EX)) {
            $content = stream_get_contents($fp);
            $queue = json_decode($content, true) ?: [];
            
            $queue = array_filter($queue, function($item) use ($sessionId) {
                return $item['sessionId'] !== $sessionId;
            });
            
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(array_values($queue)));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}
