<?php
require_once __DIR__ . '/../Blessing.php';
require_once __DIR__ . '/../effects/DestinyLinkEffect.php';

class HangedMan extends Blessing {
    public function __construct() {
        parent::__construct(
            'HangedMan', 
            'Corde du Pendu', 
            'Passif : -10% SPE, +15% ATK.', 
            '🪢'
        );
    }

    public function modifyStat(string $stat, int $currentValue, Personnage $owner): int {
        if ($stat === 'speed') {
            return (int)($currentValue * 0.9); // -10%
        }
        if ($stat === 'atk') {
            return (int)($currentValue * 1.15); // +15%
        }
        return $currentValue;
    }

    public function getExtraActions(): array {
        return [
            'noeud_destin' => [
                'label' => 'Nœud de Destin',
                'description' => 'Inflige 35% des dégâts reçus à l\'adversaire (4 tours)',
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
        // Créer l'effet de lien avec la cible
        $effect = new DestinyLinkEffect(4, 0.35, $target);
        $actor->addStatusEffect($effect, $actor);
        
        return "lie son destin à " . $target->getName() . " ! Les dégâts seront partagés !";
    }
}
