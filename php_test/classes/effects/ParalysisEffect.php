<?php
/**
 * ParalysisEffect - Bloque l'action 50% du temps
 */
class ParalysisEffect extends StatusEffect {
    
    public function __construct(int $duration) {
        parent::__construct('Paralysie', '⚡', $duration);
    }

    public function blocksAction(): bool {
        return rand(0, 1) === 0;
    }

    public function resolveDamage(Personnage $target): ?array {
        return null;
    }

    public function resolveStats(Personnage $target): ?array {
        return null;
    }
}
