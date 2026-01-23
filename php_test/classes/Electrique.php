<?php

class Electrique extends Personnage {
    public function __construct($pv, $atk, $name) {
        // PV moyens/bas, ATK moyenne, DEF basse, Speed moyenne
        parent::__construct($pv, $atk, $name, 5, 'Electrique', 15);
    }

    public function getAvailableActions(): array {
        $actions = [
            'etincelle' => [
                'label' => 'Étincelle',
                'description' => 'Attaque électrique rapide et précise.',
                'method' => 'etincelle',
                'needsTarget' => true,
                'emoji' => '⚡'
            ],
            'acceleration' => [
                'label' => 'Accélération',
                'description' => 'Augmente la vitesse pendant 4 tours.',
                'pp' => 5,
                'method' => 'acceleration',
                'needsTarget' => false,
                'emoji' => '⏩'
            ],
            'prise_foudre' => [
                'label' => 'Prise Foudre',
                'description' => 'Paralyse l\'ennemi (50% échec) et réduit sa vitesse (-5) pendant 4 tours.',
                'pp' => 3,
                'method' => 'priseFoudre',
                'needsTarget' => true,
                'emoji' => '🔌'
            ],
            'decharge' => [
                'label' => 'Décharge',
                'description' => 'Libère l\'énergie accumulée. Puissance max si beaucoup d\'actions réussies ! (Usage unique)',
                'pp' => 1,
                'method' => 'decharge',
                'needsTarget' => true,
                'emoji' => '💥'
            ]
        ];
        
        // Retirer Décharge si déjà utilisé (on peut utiliser les PP à 0 pour ça)
        // Mais attention à l'initialisation où PP est vide !
        if (!empty($this->pp) && $this->getCurrentPP('decharge') <= 0) {
            unset($actions['decharge']);
        }
        
        return $actions;
    }

    // --- ACTIONS ---

    public function etincelle(Personnage $target) {
        $dmg = $this->getAtk() + rand(2, 5);
        $dmg = max(1, $dmg - $target->getDef());
        $target->receiveDamage($dmg);
        return "lance une étincelle vive et inflige " . $dmg . " dégâts !";
    }

    public function acceleration() {
        // Buff vitesse +10 pendant 4 tours
        $this->addStatusEffect(new SpeedModEffect(4, 10));
        return "se charge d'électricité statique et accélère ! (+10 Vit)";
    }

    public function priseFoudre(Personnage $target) {
        $dmg = max(1, $this->getAtk() - $target->getDef()); // Dégâts de base
        
        // Appliquer dégâts
        $target->receiveDamage($dmg);
        
        // Appliquer Paralysie (2 tours)
        $target->addStatusEffect(new ParalysisEffect(2));
        
        // Appliquer Ralentissement (-5 Speed, 4 tours)
        $target->addStatusEffect(new SpeedModEffect(4, -5));

        return "effectue une prise foudre ! " . $dmg . " dégâts et paralyse l'ennemi !";
    }

    public function decharge(Personnage $target) {
        // Scaling : Dégâts de base + 15 par action réussie
        $stacks = $this->getSuccessfulActionsCount();
        $bonus = $stacks * 13;
        // Calcul dégâts bruts (atk + base sort + bonus)
        $totalDmg = $this->getAtk() + 5 + $bonus; 
        
        // Ignore partie de la défense
        $finalDmg = max(10, $totalDmg - ($target->getDef() / 2));
        
        $target->receiveDamage((int)$finalDmg);
        
        // Reset des stacks après décharge
        $this->resetSuccessfulActions();
        
        // Consomme le PP (usage unique géré par PP=1)
        
        return "libère toute son énergie accumulée ($stacks charges) !! C'est DEVASTATEUR ! " . (int)$finalDmg . " dégâts !";
    }
}
