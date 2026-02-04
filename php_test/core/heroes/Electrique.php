<?php
/** ELECTRIQUE - Vitesse extrême et paralysie */

class Electrique extends Personnage {
    
    public function __construct($pv, $atk, $name) {
        parent::__construct($pv, $atk, $name, 5, 'Electrique', 15);
    }

    public function getAvailableActions(): array {
        $actions = [
            'etincelle' => [
                'label' => 'Étincelle',
                'description' => 'Attaque de base',
                'method' => 'etincelle',
                'needsTarget' => true,
                'emoji' => '⚡'
            ],
            'acceleration' => [
                'label' => 'Accélération',
                'description' => '+10 SPE (4 tours)',
                'pp' => 5,
                'method' => 'acceleration',
                'needsTarget' => false,
                'emoji' => '⏩'
            ],
            'prise_foudre' => [
                'label' => 'Prise Foudre',
                'description' => 'Paralyse 2 tours, -5 SPE (4 tours)',
                'pp' => 3,
                'method' => 'priseFoudre',
                'needsTarget' => true,
                'emoji' => '🔌'
            ],
            'decharge' => [
                'label' => 'Décharge',
                'description' => 'ATK + 13/action réussie. Usage unique',
                'pp' => 1,
                'method' => 'decharge',
                'needsTarget' => true,
                'emoji' => '💥'
            ]
        ];
        
        if (!empty($this->pp) && $this->getCurrentPP('decharge') <= 0) {
            unset($actions['decharge']);
        }
        
        return $actions;
    }

    public function etincelle(Personnage $target) {
        $dmg = $this->getAtk() + $this->roll(2, 5);
        $dmg = max(1, $dmg - $target->getDef());
        $target->receiveDamage($dmg, $this);
        return "lance une étincelle vive et inflige " . $dmg . " dégâts !";
    }

    public function acceleration() {
        $this->addStatusEffect(new SpeedModEffect(4, 10));
        return "se charge d'électricité statique et accélère ! (+10 SPE)";
    }

    public function priseFoudre(Personnage $target) {
        $dmg = max(1, $this->getAtk() - $target->getDef());
        $target->receiveDamage($dmg, $this);
        $target->addStatusEffect(new ParalysisEffect(2), $this);
        $target->addStatusEffect(new SpeedModEffect(4, -5), $this);
        return "effectue une prise foudre ! " . $dmg . " dégâts et paralyse l'ennemi !";
    }

    public function decharge(Personnage $target) {
        $stacks = $this->getSuccessfulActionsCount();
        $bonus = $stacks * 13;
        $totalDmg = $this->getAtk() + 5 + $bonus; 
        $finalDmg = max(10, $totalDmg - ($target->getDef() / 2));
        $target->receiveDamage((int)$finalDmg, $this);
        $this->resetSuccessfulActions();
        return "libère toute son énergie accumulée ($stacks charges) !! C'est DEVASTATEUR ! " . (int)$finalDmg . " dégâts !";
    }
}
