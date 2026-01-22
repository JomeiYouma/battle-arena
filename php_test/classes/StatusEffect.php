<?php
/**
 * =============================================================================
 * CLASSE ABSTRAITE StatusEffect - Base pour tous les effets de statut
 * =============================================================================
 * 
 * Système POO modulable pour les statuts (Brûlure, Gel, Poison, etc.)
 * Chaque statut hérite de cette classe et définit sa propre logique.
 * 
 * Phases de résolution :
 * - resolveDamage() : Phase de dégâts (brûlure, poison, etc.)
 * - resolveStats()  : Phase de modification de stats (gel, buff, debuff)
 * 
 * =============================================================================
 */

abstract class StatusEffect {
    protected string $name;
    protected string $emoji;
    protected int $duration;
    protected int $turnsDelay;      // Tours avant activation (0 = immédiat)
    protected int $damagePerTurn;   // Dégâts par tour (si applicable)
    
    public function __construct(
        string $name,
        string $emoji,
        int $duration,
        int $turnsDelay = 0,
        int $damagePerTurn = 0
    ) {
        $this->name = $name;
        $this->emoji = $emoji;
        $this->duration = $duration;
        $this->turnsDelay = $turnsDelay;
        $this->damagePerTurn = $damagePerTurn;
    }

    // --- GETTERS ---
    public function getName(): string { return $this->name; }
    public function getEmoji(): string { return $this->emoji; }
    public function getDuration(): int { return $this->duration; }
    public function getTurnsDelay(): int { return $this->turnsDelay; }
    public function getDamagePerTurn(): int { return $this->damagePerTurn; }

    /**
     * L'effet est-il en attente d'activation ?
     */
    public function isPending(): bool {
        return $this->turnsDelay > 0;
    }

    /**
     * L'effet a-t-il expiré ?
     */
    public function isExpired(): bool {
        return $this->duration <= 0 && $this->turnsDelay <= 0;
    }

    /**
     * Décrémente le compteur approprié
     * @return bool True si l'effet vient de s'activer
     */
    public function tick(): bool {
        if ($this->turnsDelay > 0) {
            $this->turnsDelay--;
            return $this->turnsDelay === 0; // Vient de s'activer
        }
        
        if ($this->duration > 0) {
            $this->duration--;
        }
        
        return false;
    }

    /**
     * Phase de résolution des DÉGÂTS
     * Appelée en phases 2-3 du tour
     * 
     * @param Personnage $target La cible de l'effet
     * @return array|null ['log' => string, 'damage' => int, 'emoji' => string] ou null
     */
    abstract public function resolveDamage(Personnage $target): ?array;

    /**
     * Phase de résolution des STATS
     * Appelée en phases 4-5 du tour
     * 
     * @param Personnage $target La cible de l'effet
     * @return array|null ['log' => string, 'statChanges' => [...], 'emoji' => string] ou null
     */
    abstract public function resolveStats(Personnage $target): ?array;

    /**
     * Appelé quand l'effet s'active (sort de pending)
     * @return string Message d'activation
     */
    public function onActivate(Personnage $target): string {
        return $this->emoji . " " . $this->name . " s'active sur " . $target->getName() . " !";
    }

    /**
     * Appelé quand l'effet expire
     * @return string Message d'expiration
     */
    public function onExpire(Personnage $target): string {
        return "✨ " . $this->name . " sur " . $target->getName() . " s'est dissipé.";
    }

    /**
     * Sérialisation pour la session
     */
    public function toArray(): array {
        return [
            'type' => static::class,
            'name' => $this->name,
            'emoji' => $this->emoji,
            'duration' => $this->duration,
            'turnsDelay' => $this->turnsDelay,
            'damagePerTurn' => $this->damagePerTurn
        ];
    }
}
