<?php
/**
 * =============================================================================
 * CLASSE PYROMANE - Spécialiste du feu et des dégâts magiques
 * =============================================================================
 * 
 * TODO [À RECODER PAR TOI-MÊME] :
 * - Ajouter un système de brûlure (dégâts sur la durée)
 * - Implémenter des sorts de zone (boule de feu AoE)
 * - Créer un système de surchauffe qui augmente les dégâts mais coûte des PV
 * 
 * =============================================================================
 */

class Pyromane extends Personnage {
    
    private $overheatActive = false;
    private $overheatDamageBonus = 20;
    private $overheatCost = 10; // Coût en PV

    public function __construct($pv, $atk, $name, $def = 4) {
        parent::__construct($pv, $atk, $name, $def, "Pyromane");
    }

    /**
     * Liste des actions disponibles pour le Pyromane
     * TODO [À RECODER] : Ajoute des sorts de feu plus variés !
     */
    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => '🔥 Boule de feu',
                'description' => 'Lance une boule de feu enflammée',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'overheat' => [
                'label' => '☀️ Surchauffe',
                'description' => 'Surchauffe le corps ! +' . $this->overheatDamageBonus . ' dégâts, mais coûte ' . $this->overheatCost . ' PV',
                'method' => 'overheat',
                'needsTarget' => false
            ],
            'heal' => [
                'label' => '🌡️ Chaleur vitale',
                'description' => 'Convertit la chaleur en énergie vitale, +15 PV',
                'method' => 'heal',
                'needsTarget' => false
            ]
        ];
    }

    /**
     * Attaque de feu - Utilise le bonus de surchauffe si actif
     * TODO [À RECODER] : Ajouter des effets de brûlure
     */
    public function attack(Personnage $target): string {
        $bonusDamage = 0;
        $overheatText = "";
        
        // Bonus surchauffe si actif
        if ($this->overheatActive) {
            $bonusDamage = $this->overheatDamageBonus;
            $overheatText = " [SURCHAUFFE!] ";
            $this->overheatActive = false;
        }

        // Les attaques magiques ignorent une partie de la défense
        // TODO [À RECODER] : Modifier cette formule selon ton gameplay
        $effectiveDef = max(0, $target->getDef() - 3); // Ignore 3 points de DEF
        $damage = max(1, $this->atk + $bonusDamage - $effectiveDef);
        $newPv = $target->getPv() - $damage;
        
        $target->setPv($newPv);

        if ($target->isDead()) {
            return "déchaîne les flammes !" . $overheatText . " 🔥 " . $damage . " dégâts ! " . $target->getName() . " est calciné !";
        } else {
            return "lance une boule de feu !" . $overheatText . " 🔥 " . $damage . " dégâts à " . $target->getName() . " (" . $target->getPv() . " PV)";
        }
    }

    /**
     * Surchauffe - Boost les dégâts mais coûte des PV
     * TODO [À RECODER] : Faire un système de stacks de surchauffe
     */
    public function overheat(): string {
        $this->overheatActive = true;
        $oldPv = $this->pv;
        $this->setPv($this->pv - $this->overheatCost);
        
        return "entre en SURCHAUFFE ! ☀️ Prochaine attaque +" . $this->overheatDamageBonus . " dégâts ! (Coût: -" . $this->overheatCost . " PV, reste " . $this->pv . " PV)";
    }

    /**
     * Chaleur vitale - Soin du pyromane
     * TODO [À RECODER] : Synergie avec la surchauffe ?
     */
    public function heal($x = null): string {
        $oldPv = $this->pv;
        $healValue = $x ?? 15;
        
        $this->setPv($this->pv + $healValue);
        
        $actualHeal = $this->pv - $oldPv;
        return "absorbe la chaleur ambiante ! 🌡️ +" . $actualHeal . " PV (" . $this->pv . "/" . $this->basePv . ")";
    }
}