<?php
require_once __DIR__ . '/../Blessing.php';

class HangedMan extends Blessing {
    public function __construct() {
        parent::__construct(
            'HangedMan', 
            'Corde du Pendu', 
            'Mystérieux...', 
            '🪢'
        );
    }

    public function getExtraActions(): array {
        return [
            'noeud_destin' => [
                'label' => 'Nœud de Destin',
                'description' => 'Lie le destin (Dégâts partagés pendant 3 tours)',
                'emoji' => '♾️',
                'method' => 'actionNoeudDestin',
                'needsTarget' => true,
                'pp' => 1
            ]
        ];
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        if ($actionKey === 'noeud_destin' && $target !== null) {
            return $this->executeNoeud($actor, $target);
        }
        return parent::executeAction($actionKey, $actor, $target);
    }

    private function executeNoeud(Personnage $actor, Personnage $target): string {
        $damage = $actor->roll(15, 30);
        $target->receiveDamage($damage, $actor);
        return "serre le Nœud de Destin ! $damage dégâts !";
    }
}
