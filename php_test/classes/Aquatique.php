<?php
/**
 * =============================================================================
 * CLASSE AQUATIQUE - Spécialiste de l'esquive et de la fluidité
 * =============================================================================
 * 
 * TODO [À RECODER PAR TOI-MÊME] :
 * - Améliorer le système d'esquive (augmenter les chances avec le niveau)
 * - Ajouter des attaques de zone (vagues, tsunami)
 * - Créer des combos d'esquive + contre-attaque
 * 
 * =============================================================================
 */

class Aquatique extends Personnage {
    
    private $dodgeChance = 50; // 50% de chance d'esquiver

    public function __construct($pv, $atk, $name, $def = 3) {
        // L'aquatique a une défense faible mais peut esquiver
        parent::__construct($pv, $atk, $name, $def, "Aquatique");
    }

    /**
     * Liste des actions disponibles pour l'Aquatique
     * TODO [À RECODER] : Ajoute des attaques d'eau, des buffs de vitesse, etc.
     */
    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => '🌊 Jet d\'eau',
                'description' => 'Projette un puissant jet d\'eau sur l\'ennemi',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'dodge' => [
                'label' => '💨 Esquive',
                'description' => $this->dodgeChance . '% de chance d\'esquiver la prochaine attaque ennemie',
                'method' => 'dodge',
                'needsTarget' => false
            ],
            'heal' => [
                'label' => '💧 Régénération',
                'description' => 'Se régénère grâce à l\'eau, restaure 15 PV',
                'method' => 'heal',
                'needsTarget' => false
            ]
        ];
    }

    /**
     * Attaque aquatique - Inflige des dégâts d'eau
     */
    public function attack(Personnage $target): string {
        $damage = max(1, $this->atk - $target->getDef());
        $newPv = $target->getPv() - $damage;
        
        $target->setPv($newPv);

        if ($target->isDead()) {
            return "déchaîne un torrent ! 🌊 " . $damage . " dégâts ! " . $target->getName() . " est submergé !";
        } else {
            return "projette un jet d'eau ! 🌊 " . $damage . " dégâts à " . $target->getName() . " (" . $target->getPv() . " PV)";
        }
    }

    /**
     * Esquive - 50% de chance d'éviter la prochaine attaque
     * TODO [À RECODER] : Augmenter les chances si l'aquatique est sous l'eau, etc.
     */
    public function dodge(): string {
        // Tire au hasard : 1 à 100
        $roll = rand(1, 100);
        
        if ($roll <= $this->dodgeChance) {
            // Esquive réussie !
            $this->setEvading(true);
            return "se liquéfie et devient insaisissable ! 💨 Esquive ACTIVÉE !";
        } else {
            // Esquive ratée
            $this->setEvading(false);
            return "tente de se liquéfier mais... rate son esquive. 😓";
        }
    }

    /**
     * Régénération - Soin basé sur l'eau
     * TODO [À RECODER] : Augmenter le soin si proche de l'eau, etc.
     */
    public function heal($x = null): string {
        $oldPv = $this->pv;
        $healValue = $x ?? 15;
        
        $this->setPv($this->pv + $healValue);
        
        $actualHeal = $this->pv - $oldPv;
        return "absorbe l'humidité ambiante ! 💧 +" . $actualHeal . " PV (" . $this->pv . "/" . $this->basePv . ")";
    }
}
