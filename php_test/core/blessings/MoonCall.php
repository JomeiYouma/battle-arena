<?php
/** MOONCALL - Bénédiction Appel de la Lune */

require_once __DIR__ . '/../Blessing.php';

class MoonCall extends Blessing {
    private int $turns = 0;
    private bool $activatedThisTurn = false;

    public function __construct() {
        parent::__construct(
            'MoonCall', 
            'Appel de la Lune', 
            'Passif : Tous les 4 tours, SPE x 2, DEF & ATK +50%.', 
            '🌙'
        );
    }

    public function onTurnStart(Personnage $owner, Combat $combat): void {
        $this->turns++;
        $this->activatedThisTurn = false;
    }

    public function isMoonActive(): bool {
        return ($this->turns > 0 && $this->turns % 4 === 0);
    }

    public function modifyStat(string $stat, int $currentValue, Personnage $owner): int {
        if ($this->isMoonActive()) {
            $this->activatedThisTurn = true;
            if ($stat === 'speed') return $currentValue * 2;
            if ($stat === 'atk') return (int)($currentValue * 1.5);
            if ($stat === 'def') return (int)($currentValue * 1.5);
        }
        return $currentValue;
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        return parent::executeAction($actionKey, $actor, $target);
    }

    public function getAnimationData(): ?array {
        if ($this->activatedThisTurn && $this->isMoonActive()) {
            return [
                'emoji' => $this->emoji,
                'name' => $this->name,
                'message' => 'Cycle lunaire actif !SPE x 2, DEF & ATK +50%',
                'type' => 'stat_boost',
                'icon' => 'media/blessings/moon.png'
            ];
        }
        return null;
    }
}
