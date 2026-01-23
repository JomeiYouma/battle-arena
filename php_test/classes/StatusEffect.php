<?php
/**
 * StatusEffect - Classe abstraite pour tous les effets de statut (Brûlure, Paralysie, etc.)
 * 
 * Méthodes à implémenter : resolveDamage() et resolveStats()
 */
abstract class StatusEffect {
    protected string $name;
    protected string $emoji;
    protected int $duration;
    protected int $turnsDelay;
    protected int $damagePerTurn;
    
    public function __construct(string $name, string $emoji, int $duration, int $turnsDelay = 0, int $damagePerTurn = 0) {
        $this->name = $name;
        $this->emoji = $emoji;
        $this->duration = $duration;
        $this->turnsDelay = $turnsDelay;
        $this->damagePerTurn = $damagePerTurn;
    }

    public function getName(): string { return $this->name; }
    public function getEmoji(): string { return $this->emoji; }
    public function getDuration(): int { return $this->duration; }
    public function getTurnsDelay(): int { return $this->turnsDelay; }
    public function getDamagePerTurn(): int { return $this->damagePerTurn; }

    public function isPending(): bool {
        return $this->turnsDelay > 0;
    }

    public function isExpired(): bool {
        return $this->duration <= 0 && $this->turnsDelay <= 0;
    }

    public function tick(): bool {
        if ($this->turnsDelay > 0) {
            $this->turnsDelay--;
            return $this->turnsDelay === 0;
        }
        if ($this->duration > 0) {
            $this->duration--;
        }
        return false;
    }

    // Phase dégâts (brûlure, poison) - retourne ['log', 'damage', 'emoji'] ou null
    abstract public function resolveDamage(Personnage $target): ?array;

    // Phase stats (buffs, debuffs) - retourne ['log', 'statChanges', 'emoji'] ou null
    abstract public function resolveStats(Personnage $target): ?array;

    public function onActivate(Personnage $target): string {
        return $this->emoji . " " . $this->name . " s'active sur " . $target->getName() . " !";
    }

    public function onExpire(Personnage $target): string {
        return "✨ " . $this->name . " sur " . $target->getName() . " s'est dissipé.";
    }

    public function blocksAction(): bool {
        return false;
    }

    public function getStatModifiers(): array {
        return [];
    }

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
