<?php
/** WATCHTOWER - Bénédiction Tour de Garde */

require_once __DIR__ . '/../Blessing.php';

class WatchTower extends Blessing {
    public function __construct() {
        parent::__construct(
            'WatchTower', 
            'La Tour de Garde', 
            'Passif : ATK = DEF + 20% ATK.', 
            '🏰'
        );
    }

    public function modifyStat(string $stat, int $currentValue, Personnage $owner): int {
        if ($stat === 'atk') {
            // ATK = DEF + 0.2 * ATK de base
            return $owner->getDef() + (int)($owner->getBaseAtk() * 0.2);
        }
        return $currentValue;
    }

    public function onReceiveDamage(Personnage $victim, Personnage $attacker, int $damage): int {
        return $damage;
    }

    public function getExtraActions(): array {
        return [
            'fortifications' => [
                'label' => 'Fortifications',
                'description' => '+5 DEF (4 tours)',
                'emoji' => '🧱',
                'method' => 'actionFortifications',
                'needsTarget' => false,
                'pp' => 2
            ]
        ];
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        if ($actionKey === 'fortifications') {
            return $this->executeFortifications($actor);
        }
        return parent::executeAction($actionKey, $actor, $target);
    }

    private function executeFortifications(Personnage $actor): string {
        $actor->addBuff('Fortifications', 'def', 5, 4);
        return "érige des fortifications ! +5 DEF";
    }
}
