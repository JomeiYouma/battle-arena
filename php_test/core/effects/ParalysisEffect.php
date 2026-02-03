<?php
/**
 * ParalysisEffect - Bloque l'action 50% du temps
 */
class ParalysisEffect extends StatusEffect {
    
    public function __construct(int $duration) {
        parent::__construct('Paralysie', '⚡', $duration);
    }

    public function blocksAction(Personnage $target): bool {
        return $target->roll(0, 1) === 0;
    }

    public function resolveDamage(Personnage $target): ?array {
        return null;
    }

    public function resolveStats(Personnage $target): ?array {
        return null;
    }

    public function getDescription(): string {
        return "⚡ Paralysie : 50% de chance de ne pas agir ({$this->duration} tour(s))";
    }
}
