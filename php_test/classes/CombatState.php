<?php
/**
 * CombatState - Encapsule l'état complet d'un combat
 * 
 * Utilisé pour:
 * - Sérialisation/persistance
 * - Snapshots pour animations
 * - Transmission entre joueurs en multi
 */
class CombatState {
    public Personnage $player;
    public Personnage $enemy;
    public int $turn;
    public array $logs;
    public array $turnActions;
    public bool $isFinished;
    public ?Personnage $winner;
    
    // Multi-spécifique
    public ?string $gameMode; // 'solo', 'multiplayer', etc.
    public ?array $metadata; // {'matchId': ..., 'player1': {...}, 'player2': {...}}
    public ?float $createdAt;
    public ?float $updatedAt;
    
    public function __construct(
        Personnage $player,
        Personnage $enemy,
        int $turn = 1,
        bool $isFinished = false,
        ?Personnage $winner = null,
        ?string $gameMode = 'solo',
        ?array $metadata = null
    ) {
        $this->player = $player;
        $this->enemy = $enemy;
        $this->turn = $turn;
        $this->logs = [];
        $this->turnActions = [];
        $this->isFinished = $isFinished;
        $this->winner = $winner;
        $this->gameMode = $gameMode;
        $this->metadata = $metadata;
        $this->createdAt = microtime(true);
        $this->updatedAt = microtime(true);
    }
    
    /**
     * Crée une copie de l'état actuel
     */
    public function clone(): self {
        $state = new self(
            $this->player,
            $this->enemy,
            $this->turn,
            $this->isFinished,
            $this->winner,
            $this->gameMode,
            $this->metadata
        );
        $state->logs = $this->logs;
        $state->turnActions = $this->turnActions;
        $state->createdAt = $this->createdAt;
        $state->updatedAt = microtime(true);
        return $state;
    }
    
    /**
     * Sérialise l'état complet
     */
    public function serialize(): string {
        return serialize([
            'player' => serialize($this->player),
            'enemy' => serialize($this->enemy),
            'turn' => $this->turn,
            'logs' => $this->logs,
            'turnActions' => $this->turnActions,
            'isFinished' => $this->isFinished,
            'winner' => $this->winner ? serialize($this->winner) : null,
            'gameMode' => $this->gameMode,
            'metadata' => $this->metadata,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt
        ]);
    }
    
    /**
     * Désérialise un état
     */
    public static function deserialize(string $data): ?self {
        try {
            $decoded = unserialize($data);
            if (!$decoded) return null;
            
            $state = new self(
                unserialize($decoded['player']),
                unserialize($decoded['enemy']),
                $decoded['turn'],
                $decoded['isFinished'],
                $decoded['winner'] ? unserialize($decoded['winner']) : null,
                $decoded['gameMode'],
                $decoded['metadata']
            );
            
            $state->logs = $decoded['logs'] ?? [];
            $state->turnActions = $decoded['turnActions'] ?? [];
            $state->createdAt = $decoded['createdAt'];
            $state->updatedAt = $decoded['updatedAt'];
            
            return $state;
        } catch (Exception $e) {
            error_log("CombatState::deserialize - Exception: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Exporte l'état pour JSON
     */
    public function toArray(): array {
        return [
            'turn' => $this->turn,
            'logs' => $this->logs,
            'turnActions' => $this->turnActions,
            'isFinished' => $this->isFinished,
            'winner' => $this->winner ? $this->winner->getName() : null,
            'gameMode' => $this->gameMode,
            'player' => [
                'name' => $this->player->getName(),
                'type' => $this->player->getType(),
                'pv' => $this->player->getPv(),
                'maxPv' => $this->player->getBasePv(),
                'atk' => $this->player->getAtk(),
                'def' => $this->player->getDef(),
                'speed' => $this->player->getSpeed()
            ],
            'enemy' => [
                'name' => $this->enemy->getName(),
                'type' => $this->enemy->getType(),
                'pv' => $this->enemy->getPv(),
                'maxPv' => $this->enemy->getBasePv(),
                'atk' => $this->enemy->getAtk(),
                'def' => $this->enemy->getDef(),
                'speed' => $this->enemy->getSpeed()
            ]
        ];
    }
}
?>
