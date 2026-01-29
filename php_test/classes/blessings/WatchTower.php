<?php
require_once __DIR__ . '/../Blessing.php';

class WatchTower extends Blessing {
    public function __construct() {
        parent::__construct(
            'WatchTower', 
            'La Tour de Garde', 
            'Passif : Attaquer utilise la stat de DEF. La défense utilise la stat d\'ATK + 20% de DEF.', 
            '🏰'
        );
    }

    public function modifyStat(string $stat, int $currentValue, Personnage $owner): int {
        if ($stat === 'atk') {
            // Utilise la DEF pour attaquer
            return $owner->getDef(); 
        }
        return $currentValue;
    }

    public function onReceiveDamage(Personnage $victim, Personnage $attacker, int $damage): int {
        // Réduction des dégâts = ATK de base + 0.2 * DEF
        $rawAtk = $victim->getBaseAtk();
        $reduction = $rawAtk + (int)($victim->getDef() * 0.2);
        
        return max(1, $damage - $reduction);
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
