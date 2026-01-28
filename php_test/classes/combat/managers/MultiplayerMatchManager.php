<?php
require_once __DIR__ . '/../MatchManager.php';

/**
 * MultiplayerMatchManager - Gestion des stats et persistance en multijoueur
 * 
 * Responsabilités:
 * - Sauvegarder l'état du match
 * - Enregistrer les statistiques de victoire
 * - Calculer les winrates
 */
class MultiplayerMatchManager implements MatchManager {
    
    private ?string $matchId;
    private ?array $metadata;
    private string $matchesDir;
    private ?string $winnerId;
    
    public function __construct(?string $matchId = null, ?array $metadata = null, string $matchesDir = 'data/matches/') {
        $this->matchId = $matchId;
        $this->metadata = $metadata;
        $this->matchesDir = $matchesDir;
        $this->winnerId = null;
    }
    
    public function onMatchStart(): void {
        // Générer un ID si pas déjà présent
        if (!$this->matchId) {
            $this->matchId = uniqid('match_', true);
        }
    }
    
    public function onMatchEnd(Personnage $winner, Personnage $loser): void {
        // Déterminer qui est le gagnant dans la structure (P1 ou P2)
        $this->winnerId = $winner === $this->metadata['player1']['hero'] ?? null ? 'p1' : 'p2';
    }
    
    public function recordStats(Personnage $winner, Personnage $loser, int $turn): void {
        if (!$this->metadata) return;
        
        // Enregistrer la victoire/défaite
        // Format: matchId -> [winner, loser, turns, timestamp]
        $statsFile = $this->matchesDir . 'stats.json';
        
        $stats = [];
        if (file_exists($statsFile)) {
            $stats = json_decode(file_get_contents($statsFile), true) ?? [];
        }
        
        $winnerId = $this->winnerId;
        $loserId = $winnerId === 'p1' ? 'p2' : 'p1';
        
        $stats[$this->matchId] = [
            'winner' => $winnerId,
            'loser' => $loserId,
            'turns' => $turn,
            'timestamp' => time(),
            'player1' => $this->metadata['player1']['display_name'] ?? 'Player 1',
            'player2' => $this->metadata['player2']['display_name'] ?? 'Player 2'
        ];
        
        // Sauvegarder
        if (!is_dir($this->matchesDir)) {
            mkdir($this->matchesDir, 0755, true);
        }
        
        file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    public function save(array $state): bool {
        if (!$this->matchId) {
            return false;
        }
        
        if (!is_dir($this->matchesDir)) {
            mkdir($this->matchesDir, 0755, true);
        }
        
        $filepath = $this->matchesDir . $this->matchId . '.json';
        
        try {
            $jsonState = [
                'matchId' => $this->matchId,
                'metadata' => $this->metadata,
                'combatState' => $state,
                'lastSaved' => time()
            ];
            
            file_put_contents($filepath, json_encode($jsonState, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return true;
        } catch (Exception $e) {
            error_log("MultiplayerMatchManager::save - Exception: " . $e->getMessage());
            return false;
        }
    }
    
    public function load(string $id): ?array {
        $filepath = $this->matchesDir . $id . '.json';
        
        if (!file_exists($filepath)) {
            return null;
        }
        
        try {
            $content = file_get_contents($filepath);
            $data = json_decode($content, true);
            return $data;
        } catch (Exception $e) {
            error_log("MultiplayerMatchManager::load - Exception: " . $e->getMessage());
            return null;
        }
    }
    
    public function getWinnerId(): ?string {
        return $this->winnerId;
    }
    
    public function getMatchId(): ?string {
        return $this->matchId;
    }
}
?>
