<?php

class ParalysisEffect extends StatusEffect {
    public function __construct(int $duration) {
        parent::__construct('Paralysie', '⚡', $duration);
    }

    public function applyEffect(Personnage $target): array {
        return [];
    }
    
    // Sera appelé par Combat ou Personnage pour vérifier le blocage
    public function blocksAction(): bool {
        // 50% de chance de bloquer
        return rand(0, 1) === 0;
    }

    public function resolveDamage(Personnage $target): ?array {
        return null;
    }

    public function resolveStats(Personnage $target): ?array {
        return null;
    }
}
