<?php
require_once __DIR__ . '/../Blessing.php';

class WheelOfFortune extends Blessing {
    public function __construct() {
        parent::__construct(
            'WheelOfFortune', 
            'Roue de Fortune', 
            'Double la portée des jets aléatoires.', 
            '🎰'
        );
    }

    public function modifyRoll(int $min, int $max): ?array {
        $center = ($min + $max) / 2;
        $span = ($max - $min) / 2;
        $newSpan = $span * 2; // Doubler la portée
        return [
            'min' => floor($center - $newSpan),
            'max' => ceil($center + $newSpan)
        ];
    }

    public function getExtraActions(): array {
        return [
            'concoction_maladroite' => [
                'label' => 'Concoction Maladroite',
                'description' => 'Inflige 25-33% PV à l\'ennemi, 15-25% à soi',
                'emoji' => '🧪',
                'method' => 'actionConcoctionMaladroite',
                'needsTarget' => true,
                'pp' => 2
            ]
        ];
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        if ($actionKey === 'concoction_maladroite' && $target !== null) {
            return $this->executeConcoction($actor, $target);
        }
        return parent::executeAction($actionKey, $actor, $target);
    }

    private function executeConcoction(Personnage $actor, Personnage $target): string {
        $selfDmgPct = $actor->roll(15, 25) / 100;
        $enemyDmgPct = $actor->roll(25, 33) / 100;
        
        $selfDmg = (int)($actor->getBasePv() * $selfDmgPct);
        $enemyDmg = (int)($target->getBasePv() * $enemyDmgPct);
        
        $actor->receiveDamage($selfDmg);
        $target->receiveDamage($enemyDmg, $actor);
        
        return "lance une potion instable ! -$selfDmg PV (Soi) / -$enemyDmg PV (Ennemi)";
    }
}
