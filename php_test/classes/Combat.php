<?php
/**
 * COMBAT - Gestion du système de combat tour par tour
 * 
 * Système de vitesse : le plus rapide agit en premier
 */

class Combat {
    private Personnage $player;
    private Personnage $enemy;
    private array $logs = [];
    private int $turn = 1;

    // Actions du dernier tour (pour animations séquentielles)
    private array $turnActions = [];
    
    // Tracking des emojis d'action
    private ?string $lastPlayerActionEmoji = null;
    private ?string $lastEnemyActionEmoji = null;
    private bool $lastPlayerActionNeedsTarget = false;
    private bool $lastEnemyActionNeedsTarget = false;

    public function __construct(Personnage $player, Personnage $enemy) {
        $this->player = $player;
        $this->enemy = $enemy;
        $this->logs[] = "⚔️ Combat : " . $player->getName() . " VS " . $enemy->getName();
        $this->logs[] = "⚡ Vitesse : " . $player->getName() . " (" . $player->getSpeed() . ") vs " . $enemy->getName() . " (" . $enemy->getSpeed() . ")";
    }

    public function getPlayer(): Personnage { return $this->player; }
    public function getEnemy(): Personnage { return $this->enemy; }
    public function getLogs(): array { return $this->logs; }
    public function getTurn(): int { return $this->turn; }
    public function getTurnActions(): array { return $this->turnActions; }
    
    public function getLastPlayerActionEmoji(): ?string { return $this->lastPlayerActionEmoji; }
    public function getLastEnemyActionEmoji(): ?string { return $this->lastEnemyActionEmoji; }
    public function getLastPlayerActionNeedsTarget(): bool { return $this->lastPlayerActionNeedsTarget; }
    public function getLastEnemyActionNeedsTarget(): bool { return $this->lastEnemyActionNeedsTarget; }

    public function getPlayerActions(): array {
        return $this->player->getAvailableActions();
    }

    /**
     * Détermine qui est le plus rapide
     */
    public function playerIsFaster(): bool {
        // En cas d'égalité, le joueur agit en premier
        return $this->player->getSpeed() >= $this->enemy->getSpeed();
    }

    /**
     * Phase de résolution des effets (brûlure, poison, etc.)
     */
    private function resolveEffectsPhase(): void {
        $playerEffects = $this->player->resolveEffects();
        foreach ($playerEffects['logs'] as $log) {
            $this->logs[] = $log;
        }
        if ($this->player->isDead()) {
            $this->logs[] = "💀 " . $this->player->getName() . " succombe aux effets !";
            return;
        }

        $enemyEffects = $this->enemy->resolveEffects();
        foreach ($enemyEffects['logs'] as $log) {
            $this->logs[] = $log;
        }
        if ($this->enemy->isDead()) {
            $this->logs[] = "🏆 " . $this->enemy->getName() . " succombe aux effets !";
            return;
        }

        // Décrémenter les buffs
        foreach ($this->player->decrementBuffDurations() as $log) $this->logs[] = $log;
        foreach ($this->enemy->decrementBuffDurations() as $log) $this->logs[] = $log;
    }

    /**
     * Exécute l'action du joueur
     */
    private function doPlayerAction(string $actionKey): bool {
        $actions = $this->player->getAvailableActions();
        if (!isset($actions[$actionKey]) || !$this->player->canUseAction($actionKey)) {
            return false;
        }

        $action = $actions[$actionKey];
        $this->player->usePP($actionKey);
        
        $this->lastPlayerActionEmoji = $action['emoji'] ?? null;
        $this->lastPlayerActionNeedsTarget = $action['needsTarget'] ?? false;

        $method = $action['method'];
        $result = ($action['needsTarget'] ?? false) 
            ? $this->player->$method($this->enemy) 
            : $this->player->$method();
        
        $this->logs[] = "🎮 " . $this->player->getName() . " : " . $result;
        
        // Ajouter à la liste des actions du tour
        $this->turnActions[] = [
            'actor' => 'player',
            'emoji' => $action['emoji'] ?? '⚔️',
            'needsTarget' => $action['needsTarget'] ?? false,
            'label' => $action['label']
        ];

        return $this->enemy->isDead();
    }

    /**
     * Exécute l'action de l'ennemi (IA)
     */
    private function doEnemyAction(): bool {
        if ($this->enemy->isDead()) return false;

        // Esquive active ?
        if ($this->player->isEvading()) {
            $this->logs[] = "💨 " . $this->player->getName() . " esquive !";
            $this->player->setEvading(false);
            return false;
        }

        // IA : choisir une action
        $actions = $this->enemy->getAvailableActions();
        $available = array_filter($actions, fn($k) => $this->enemy->canUseAction($k), ARRAY_FILTER_USE_KEY);
        if (empty($available)) $available = ['attack' => $actions['attack']];

        // Priorité heal si PV bas
        $healthPct = $this->enemy->getPv() / $this->enemy->getBasePv();
        if ($healthPct < 0.3 && isset($available['heal'])) {
            $selectedKey = 'heal';
        } else {
            $keys = array_keys($available);
            $selectedKey = $keys[array_rand($keys)];
        }
        
        $action = $available[$selectedKey];
        $this->enemy->usePP($selectedKey);
        
        $this->lastEnemyActionEmoji = $action['emoji'] ?? null;
        $this->lastEnemyActionNeedsTarget = $action['needsTarget'] ?? false;

        $method = $action['method'];
        $result = ($action['needsTarget'] ?? false) 
            ? $this->enemy->$method($this->player) 
            : $this->enemy->$method();
        
        $this->logs[] = "🤖 " . $this->enemy->getName() . " : " . $result;
        
        // Ajouter à la liste des actions du tour
        $this->turnActions[] = [
            'actor' => 'enemy',
            'emoji' => $action['emoji'] ?? '⚔️',
            'needsTarget' => $action['needsTarget'] ?? false,
            'label' => $action['label']
        ];

        return $this->player->isDead();
    }

    /**
     * Exécute un tour complet basé sur la vitesse
     */
    public function executePlayerAction(string $actionKey): void {
        $actions = $this->player->getAvailableActions();
        
        if (!isset($actions[$actionKey])) {
            $this->logs[] = "❌ Action invalide !";
            return;
        }
        if (!$this->player->canUseAction($actionKey)) {
            $this->logs[] = "❌ Plus de PP !";
            return;
        }

        // Reset
        $this->lastPlayerActionEmoji = null;
        $this->lastEnemyActionEmoji = null;
        $this->turnActions = [];

        $this->logs[] = "--- Tour " . $this->turn . " ---";

        // Résolution des effets (à partir du tour 2)
        if ($this->turn > 1) {
            $this->resolveEffectsPhase();
            if ($this->isOver()) return;
        }

        // Déterminer l'ordre selon la vitesse
        $playerFirst = $this->playerIsFaster();
        
        if ($playerFirst) {
            $this->logs[] = "⚡ " . $this->player->getName() . " agit en premier !";
            
            // Joueur agit
            if ($this->doPlayerAction($actionKey)) {
                $this->logs[] = "🏆 " . $this->player->getName() . " remporte le combat !";
                return;
            }
            
            // Ennemi agit
            if ($this->doEnemyAction()) {
                $this->logs[] = "💀 " . $this->player->getName() . " a été vaincu...";
                return;
            }
        } else {
            $this->logs[] = "⚡ " . $this->enemy->getName() . " agit en premier !";
            
            // Ennemi agit d'abord
            if ($this->doEnemyAction()) {
                $this->logs[] = "💀 " . $this->player->getName() . " a été vaincu...";
                return;
            }
            
            // Joueur agit ensuite
            if ($this->doPlayerAction($actionKey)) {
                $this->logs[] = "🏆 " . $this->player->getName() . " remporte le combat !";
                return;
            }
        }

        $this->turn++;
    }

    public function isOver(): bool {
        return $this->player->isDead() || $this->enemy->isDead();
    }

    public function getWinner(): ?Personnage {
        if ($this->enemy->isDead()) return $this->player;
        if ($this->player->isDead()) return $this->enemy;
        return null;
    }
}
