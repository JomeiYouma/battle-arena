<?php
require_once __DIR__ . '/Database.php';

/**
 * MatchQueue - Gestion de la file d'attente multijoueur via BDD
 * Remplace l'ancien système basé sur queue.json
 */
class MatchQueue {
    private PDO $db;
    private string $matchesDir;
    private int $timeoutSeconds = 30;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->matchesDir = __DIR__ . '/../data/matches/';
        
        if (!file_exists($this->matchesDir)) {
            mkdir($this->matchesDir, 0777, true);
        }
    }

    /**
     * Ajoute un joueur à la queue ou trouve un match existant
     */
    public function findMatch($sessionId, $heroData, $displayName = null, $userId = null, $blessingId = null) {
        $displayName = $displayName ?? $heroData['name'];
        $now = time();
        
        // 1. Nettoyer les entrées expirées
        $this->cleanExpiredEntries();
        
        // 2. Vérifier si on est déjà dans la queue (et update timestamp)
        $stmt = $this->db->prepare("SELECT id FROM match_queue WHERE session_id = :session_id");
        $stmt->execute(['session_id' => $sessionId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Mise à jour du heartbeat
            $stmt = $this->db->prepare("UPDATE match_queue SET joined_at = NOW() WHERE session_id = :session_id");
            $stmt->execute(['session_id' => $sessionId]);
        }
        
        // 3. Chercher un adversaire (le premier qui n'est pas nous)
        $stmt = $this->db->prepare("
            SELECT * FROM match_queue 
            WHERE session_id != :session_id 
            ORDER BY joined_at ASC 
            LIMIT 1
        ");
        $stmt->execute(['session_id' => $sessionId]);
        $opponent = $stmt->fetch();
        
        if ($opponent) {
            // MATCH TROUVÉ !
            $opponentHeroData = json_decode($opponent['hero_data'], true);
            
            // Retirer l'adversaire de la queue
            $stmt = $this->db->prepare("DELETE FROM match_queue WHERE id = :id");
            $stmt->execute(['id' => $opponent['id']]);
            
            // Retirer le joueur actuel de la queue
            $stmt = $this->db->prepare("DELETE FROM match_queue WHERE session_id = :session_id");
            $stmt->execute(['session_id' => $sessionId]);
            
            // Créer le fichier de match
            $matchId = uniqid('match_');
            $matchData = [
                'id' => $matchId,
                'created_at' => $now,
                'status' => 'active',
                'mode' => 'pvp',
                'turn' => 1,
                'player1' => [
                    'session' => $opponent['session_id'],
                    'hero' => $opponentHeroData,
                    'display_name' => $opponent['display_name'],
                    'user_id' => $opponent['user_id'],
                    'hp' => $opponentHeroData['pv'],
                    'max_hp' => $opponentHeroData['pv'],
                    'blessing_id' => $opponent['blessing_id'],
                    'last_poll' => $now
                ],
                'player2' => [
                    'session' => $sessionId,
                    'hero' => $heroData,
                    'display_name' => $displayName,
                    'user_id' => $userId,
                    'hp' => $heroData['pv'],
                    'max_hp' => $heroData['pv'],
                    'blessing_id' => $blessingId,
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
            if (!$existing) {
                $stmt = $this->db->prepare("
                    INSERT INTO match_queue (session_id, user_id, hero_data, display_name, blessing_id)
                    VALUES (:session_id, :user_id, :hero_data, :display_name, :blessing_id)
                ");
                $stmt->execute([
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'hero_data' => json_encode($heroData),
                    'display_name' => $displayName,
                    'blessing_id' => $blessingId
                ]);
            }
            
            return ['status' => 'waiting'];
        }
    }

    /**
     * Vérifie si un match a été créé pour ce joueur
     */
    public function checkMatchStatus($sessionId) {
        $now = time();
        
        // 1. Balayer les fichiers matches pour voir si notre session y est
        $files = glob($this->matchesDir . 'match_*.json');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content) {
                $match = json_decode($content, true);
                if ($match && ($match['player1']['session'] === $sessionId || $match['player2']['session'] === $sessionId)) {
                    if ($match['status'] === 'active') {
                        $queueCount = $this->getQueueCount();
                        return ['status' => 'matched', 'matchId' => $match['id'], 'queue_count' => $queueCount];
                    }
                }
            }
        }
        
        // 2. Vérifier si le joueur est dans la queue et si timeout atteint
        $stmt = $this->db->prepare("
            SELECT *, TIMESTAMPDIFF(SECOND, joined_at, NOW()) as time_in_queue 
            FROM match_queue 
            WHERE session_id = :session_id
        ");
        $stmt->execute(['session_id' => $sessionId]);
        $entry = $stmt->fetch();
        
        if ($entry) {
            $timeInQueue = (int)$entry['time_in_queue'];
            
            if ($timeInQueue >= $this->timeoutSeconds) {
                // Créer un match bot
                $matchId = uniqid('match_');
                $heroData = json_decode($entry['hero_data'], true);
                
                // Sélectionner un ennemi aléatoire depuis la BDD
                require_once __DIR__ . '/Services/HeroManager.php';
                require_once __DIR__ . '/Models/Hero.php';
                $heroManager = new HeroManager();
                $allHeroes = $heroManager->getAll();
                $potentialEnemies = array_filter($allHeroes, function($h) use ($heroData) {
                    return $h->getHeroId() !== $heroData['id'];
                });
                $enemyHero = $potentialEnemies[array_rand($potentialEnemies)];
                $enemyData = $enemyHero->toArray();
                
                $matchData = [
                    'id' => $matchId,
                    'created_at' => $now,
                    'status' => 'active',
                    'turn' => 1,
                    'mode' => 'bot',
                    'player1' => [
                        'session' => $sessionId,
                        'hero' => $heroData,
                        'display_name' => $entry['display_name'],
                        'hp' => $heroData['pv'],
                        'max_hp' => $heroData['pv'],
                        'blessing_id' => $entry['blessing_id'],
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
                
                // Retirer de la queue
                $stmt = $this->db->prepare("DELETE FROM match_queue WHERE session_id = :session_id");
                $stmt->execute(['session_id' => $sessionId]);
                
                return ['status' => 'timeout', 'matchId' => $matchId, 'queue_count' => 0];
            }
            
            $queueCount = $this->getQueueCount();
            return ['status' => 'waiting', 'queue_count' => $queueCount];
        }
        
        return ['status' => 'waiting', 'queue_count' => $this->getQueueCount()];
    }
    
    /**
     * Obtient le nombre de joueurs en queue
     */
    public function getQueueCount(): int {
        $this->cleanExpiredEntries();
        
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM match_queue");
        $result = $stmt->fetch();
        
        return (int)$result['count'];
    }
    
    /**
     * Retire un joueur de la queue
     */
    public function removeFromQueue($sessionId): void {
        $stmt = $this->db->prepare("DELETE FROM match_queue WHERE session_id = :session_id");
        $stmt->execute(['session_id' => $sessionId]);
    }
    
    /**
     * Nettoie les entrées expirées (plus vieilles que timeoutSeconds * 2)
     */
    private function cleanExpiredEntries(): void {
        $stmt = $this->db->prepare("
            DELETE FROM match_queue 
            WHERE TIMESTAMPDIFF(SECOND, joined_at, NOW()) > :timeout
        ");
        $stmt->execute(['timeout' => $this->timeoutSeconds * 2]);
    }
    
    /**
     * Vide complètement la queue (pour debug)
     */
    public function clearQueue(): void {
        $this->db->exec("TRUNCATE TABLE match_queue");
    }
}
