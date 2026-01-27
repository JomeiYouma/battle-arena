<?php
require_once __DIR__ . '/../Blessing.php';
require_once __DIR__ . '/../effects/ParalysisEffect.php';

class LoversCharm extends Blessing {
    public function __construct() {
        parent::__construct(
            'LoversCharm', 
            'Charmes Amoureux', 
            'Renvoie 25% des dégâts reçus.', 
            '💘'
        );
    }

    public function onReceiveDamage(Personnage $victim, Personnage $attacker, int $damage): int {
        if ($attacker !== $victim && !$attacker->isDead()) {
            $reflect = (int)($damage * 0.25);
            if ($reflect > 0) {
                $attacker->setPv($attacker->getPv() - $reflect);
            }
        }
        return $damage;
    }

    public function getExtraActions(): array {
        return [
            'foudre_amour' => [
                'label' => 'Foudre de l\'Amour',
                'description' => 'Paralyse l\'ennemi (2 tours)',
                'emoji' => '⚡❤️',
                'method' => 'actionFoudreAmour',
                'needsTarget' => true,
                'pp' => 3
            ]
        ];
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        if ($actionKey === 'foudre_amour' && $target !== null) {
            return $this->executeFoudre($actor, $target);
        }
        return parent::executeAction($actionKey, $actor, $target);
    }

    private function executeFoudre(Personnage $actor, Personnage $target): string {
        $target->addStatusEffect(new ParalysisEffect(2));
        return "lance la Foudre de l'Amour ! " . $target->getName() . " est paralysé !";
    }
}
