<?php
/** STRENGTHFAVOR - Bénédiction Faveur de Force */

require_once __DIR__ . '/../Blessing.php';
require_once __DIR__ . '/../effects/ImmunityEffect.php';

class StrengthFavor extends Blessing {
    public function __construct() {
        parent::__construct(
            'StrengthFavor', 
            'Faveur de Force', 
            '-75% DEF, +33% ATK.', 
            '💪'
        );
    }

    public function modifyStat(string $stat, int $currentValue, Personnage $owner): int {
        if ($stat === 'atk') {
            return (int)($currentValue * 1.33);
        }
        if ($stat === 'def') {
            return (int)($currentValue * 0.25); // -75% means 25% remains
        }
        return $currentValue;
    }

    public function getExtraActions(): array {
        return [
            'transe_guerriere' => [
                'label' => 'Transe Guerrière',
                'description' => 'Immunise contre les effets de statuts (3 tours)',
                'emoji' => '🥋',
                'method' => 'actionTranseGuerriere',
                'needsTarget' => false,
                'pp' => 2
            ]
        ];
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        if ($actionKey === 'transe_guerriere') {
            return $this->executeTranse($actor);
        }
        return parent::executeAction($actionKey, $actor, $target);
    }

    private function executeTranse(Personnage $actor): string {
        $actor->addStatusEffect(new ImmunityEffect(3));
        return "entre en Transe Guerrière ! Immunisé aux effets négatifs !";
    }
}
