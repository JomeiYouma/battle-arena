<?php
/**
 * =============================================================================
 * CLASSE BARBARE - Spécialiste de la force brute
 * =============================================================================
 * 
 * TODO [À RECODER PAR TOI-MÊME] :
 * - Ajouter une mécanique de "berserk" quand les PV sont bas
 * - Implémenter des attaques à deux mains plus puissantes
 * - Créer un système de cri de guerre qui buff l'équipe
 * 
 * =============================================================================
 */

class Barbare extends Personnage {
    
    private $berserkThreshold = 0.3; // 30% PV = mode berserk
    private $berserkBonus = 15;

    public function __construct($pv, $atk, $name, $def = 3) {
        // Le barbare a peu de défense mais beaucoup d'attaque
        parent::__construct($pv, $atk, $name, $def, "Barbare");
    }

    /**
     * Vérifie si le barbare est en mode berserk (PV bas)
     */
    private function isBerserk(): bool {
        return ($this->pv / $this->basePv) <= $this->berserkThreshold;
    }

    /**
     * Liste des actions disponibles pour le Barbare
     * TODO [À RECODER] : Ajoute des attaques de zone, des charges, etc.
     */
    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => '🪓 Coup de hache',
                'description' => 'Frappe puissante à la hache. +' . $this->berserkBonus . ' dégâts si PV < 30%',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'warcry' => [
                'label' => '📢 Cri de guerre',
                'description' => 'Pousse un cri terrifiant ! +8 ATK permanent',
                'method' => 'warcry',
                'needsTarget' => false
            ],
            'heal' => [
                'label' => '🍖 Dévorer',
                'description' => 'Dévore un morceau de viande, +20 PV',
                'method' => 'heal',
                'needsTarget' => false
            ]
        ];
    }

    /**
     * Attaque du Barbare - Plus forte si en mode Berserk
     * TODO [À RECODER] : Ajoute un effet de saignement, des coups critiques
     */
    public function attack(Personnage $target): string {
        $bonusDamage = 0;
        $berserkText = "";
        
        // Bonus berserk si PV bas
        if ($this->isBerserk()) {
            $bonusDamage = $this->berserkBonus;
            $berserkText = " [BERSERK!] ";
        }

        $damage = max(1, $this->atk + $bonusDamage - $target->getDef());
        $newPv = $target->getPv() - $damage;
        
        $target->setPv($newPv);

        if ($target->isDead()) {
            return "déchaîne sa fureur !" . $berserkText . " 🪓 " . $damage . " dégâts ! " . $target->getName() . " est écrasé !";
        } else {
            return "abat sa hache !" . $berserkText . " 🪓 " . $damage . " dégâts à " . $target->getName() . " (" . $target->getPv() . " PV)";
        }
    }

    /**
     * Cri de guerre - Augmente l'ATK de façon permanente
     * TODO [À RECODER] : Faire affecter aussi les alliés en mode multi
     */
    public function warcry(): string {
        $this->atk += 8;
        return "pousse un CRI DE GUERRE terrifiant ! 📢 ATK +" . 8 . " (Total: " . $this->atk . ")";
    }

    /**
     * Dévorer - Soin du barbare
     * TODO [À RECODER] : Ajouter différents types de nourriture avec des effets
     */
    public function heal($x = null): string {
        $oldPv = $this->pv;
        $healValue = $x ?? 20;
        
        $this->setPv($this->pv + $healValue);
        
        $actualHeal = $this->pv - $oldPv;
        return "dévore un morceau de viande ! 🍖 +" . $actualHeal . " PV (" . $this->pv . "/" . $this->basePv . ")";
    }
}
