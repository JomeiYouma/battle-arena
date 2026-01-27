<?php
require_once __DIR__ . '/../Blessing.php';

class MoonCall extends Blessing {
    private int $turns = 0;

    public function __construct() {
        parent::__construct(
            'MoonCall', 
            'Appel de la Lune', 
            'Cycle 4 tours: Boost stats mais coût PP doublé.', 
            '🌙'
        );
    }

    public function onTurnStart(Personnage $owner, Combat $combat): void {
        $this->turns++;
    }

    public function isMoonActive(): bool {
        return ($this->turns > 0 && $this->turns % 4 === 0);
    }

    public function modifyStat(string $stat, int $currentValue, Personnage $owner): int {
        if ($this->isMoonActive()) {
            if ($stat === 'speed') return $currentValue * 2;
            if ($stat === 'atk') return (int)($currentValue * 1.5);
            if ($stat === 'def') return (int)($currentValue * 1.5);
        }
        return $currentValue;
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        return parent::executeAction($actionKey, $actor, $target);
    }
}
